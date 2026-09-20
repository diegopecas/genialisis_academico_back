<?php
/**
 * Como se pinta el portal publico de inscripcion. Una fila por tenant.
 *
 * getPublico() es el unico metodo que se expone sin sesion: lo consume
 * la pagina publica para saber colores, fuentes, logo y textos antes de
 * pintar nada. Por eso devuelve solo lo visual y nunca datos internos.
 */
class ConfiguracionPortalPublico
{
    public static function getByTenant()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM configuracion_portal_publico WHERE id_tenant = :id_tenant LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Version publica: sin token. Solo lo necesario para pintar la pagina.
     * Si el portal esta apagado devuelve activo = 0 y la pagina muestra el
     * aviso de no disponible en lugar del formulario.
     */
    public static function getPublico()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT nombre_mostrar, logo, favicon, url_sitio_web,
        color_primario, color_secundario, color_fondo, color_texto, color_boton, color_texto_boton,
        fuente_titulos, fuente_texto,
        titulo_portada, mensaje_bienvenida, imagen_portada, texto_pie,
        telefono, correo, whatsapp,
        terminos_condiciones, politica_datos, activo
        FROM configuracion_portal_publico
        WHERE id_tenant = :id_tenant LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            Flight::json(array('activo' => 0));
            return;
        }

        Flight::json($fila);
    }

    /**
     * Solo actualiza: la fila de cada tenant la crea el script de
     * instalacion, asi que aqui nunca se inserta.
     */
    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $campos = [
                'nombre_mostrar', 'logo', 'favicon', 'url_sitio_web',
                'color_primario', 'color_secundario', 'color_fondo',
                'color_texto', 'color_boton', 'color_texto_boton',
                'fuente_titulos', 'fuente_texto',
                'titulo_portada', 'mensaje_bienvenida', 'imagen_portada', 'texto_pie',
                'telefono', 'correo', 'whatsapp',
                'terminos_condiciones', 'politica_datos', 'activo'
            ];

            $sets = [];
            foreach ($campos as $campo) {
                $sets[] = "`$campo` = :$campo";
            }

            $sentence = $db->prepare("UPDATE configuracion_portal_publico SET " .
                implode(', ', $sets) . " WHERE id_tenant = :id_tenant");

            foreach ($campos as $campo) {
                $valor = isset($data[$campo]) ? $data[$campo] : null;
                if ($campo === 'activo') {
                    $sentence->bindValue(':' . $campo, (int) $valor, PDO::PARAM_INT);
                } else {
                    if ($valor === '') {
                        $valor = null;
                    }
                    $sentence->bindValue(':' . $campo, $valor);
                }
            }
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                // Puede ser que no hubiera cambios, o que falte la fila del
                // tenant porque no se corrio el script de instalacion.
                $verif = $db->prepare("SELECT id FROM configuracion_portal_publico WHERE id_tenant = :id_tenant");
                $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $verif->execute();

                if (!$verif->fetch()) {
                    Flight::json(array('error' => 'No existe la configuración del portal para este jardín.'), 404);
                    return;
                }
            }

            self::getByTenant();
        } catch (Exception $e) {
            error_log("Error en la ejecución del método replace de configuracion portal publico: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al guardar la configuración del portal.'), 500);
        }
    }
}
