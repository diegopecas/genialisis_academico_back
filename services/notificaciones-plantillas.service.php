<?php
class NotificacionesPlantillas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                p.id,
                p.nombre,
                p.descripcion,
                p.id_categoria,
                c.nombre  AS categoria_nombre,
                c.icono   AS categoria_icono,
                c.color   AS categoria_color,
                p.titulo,
                p.cuerpo,
                p.id_respuesta_tipo,
                rt.nombre AS respuesta_tipo_nombre,
                p.incluir_whatsapp,
                p.variables_llenado,
                p.veces_usada,
                p.activo,
                p.fecha_actualizacion
            FROM notificaciones_plantillas p
            LEFT JOIN notificaciones_categorias c ON c.id = p.id_categoria
            LEFT JOIN notificaciones_respuestas_tipos rt ON rt.id = p.id_respuesta_tipo
            WHERE p.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(self::decodificarVariables($sentence->fetchAll()));
    }

    /**
     * Plantillas activas para el modal de seleccion del formulario de envio.
     * Se ordenan por uso para que las de siempre queden arriba.
     */
    public static function getActivas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                p.id,
                p.nombre,
                p.descripcion,
                p.id_categoria,
                c.nombre  AS categoria_nombre,
                c.icono   AS categoria_icono,
                c.color   AS categoria_color,
                p.titulo,
                p.cuerpo,
                p.id_respuesta_tipo,
                p.incluir_whatsapp,
                p.variables_llenado,
                p.veces_usada
            FROM notificaciones_plantillas p
            LEFT JOIN notificaciones_categorias c ON c.id = p.id_categoria
            WHERE p.id_tenant = :id_tenant AND p.activo = 1
            ORDER BY p.veces_usada DESC, p.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(self::decodificarVariables($sentence->fetchAll()));
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM notificaciones_plantillas WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(self::decodificarVariables($sentence->fetchAll()));
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuario = $userData->id ?? null;

            $nombre = Flight::request()->data['nombre'] ?? null;
            $titulo = Flight::request()->data['titulo'] ?? null;
            $cuerpo = Flight::request()->data['cuerpo'] ?? null;

            if (!$nombre || !$titulo || !$cuerpo) {
                Flight::json(array('error' => 'El nombre, el título y el mensaje son obligatorios'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO notificaciones_plantillas
                    (id, id_tenant, nombre, descripcion, id_categoria, titulo, cuerpo,
                     id_respuesta_tipo, incluir_whatsapp, variables_llenado, id_usuario_creo)
                VALUES
                    (:id, :id_tenant, :nombre, :descripcion, :id_categoria, :titulo, :cuerpo,
                     :id_respuesta_tipo, :incluir_whatsapp, :variables_llenado, :id_usuario_creo)
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindValue(':descripcion', Flight::request()->data['descripcion'] ?? null);
            $sentence->bindValue(':id_categoria', self::valorONulo(Flight::request()->data['id_categoria'] ?? null));
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':cuerpo', $cuerpo);
            $sentence->bindValue(':id_respuesta_tipo', self::valorONulo(Flight::request()->data['id_respuesta_tipo'] ?? null));
            $sentence->bindValue(':incluir_whatsapp', !empty(Flight::request()->data['incluir_whatsapp']) ? 1 : 0, PDO::PARAM_INT);
            $sentence->bindValue(':variables_llenado', self::codificarVariables(Flight::request()->data['variables_llenado'] ?? null));
            $sentence->bindValue(':id_usuario_creo', $idUsuario);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesPlantillas::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear la plantilla'), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id     = Flight::request()->data['id'] ?? null;
            $nombre = Flight::request()->data['nombre'] ?? null;
            $titulo = Flight::request()->data['titulo'] ?? null;
            $cuerpo = Flight::request()->data['cuerpo'] ?? null;

            if (!$id || !$nombre || !$titulo || !$cuerpo) {
                Flight::json(array('error' => 'ID, nombre, título y mensaje son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE notificaciones_plantillas
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    id_categoria = :id_categoria,
                    titulo = :titulo,
                    cuerpo = :cuerpo,
                    id_respuesta_tipo = :id_respuesta_tipo,
                    incluir_whatsapp = :incluir_whatsapp,
                    variables_llenado = :variables_llenado,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindValue(':descripcion', Flight::request()->data['descripcion'] ?? null);
            $sentence->bindValue(':id_categoria', self::valorONulo(Flight::request()->data['id_categoria'] ?? null));
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':cuerpo', $cuerpo);
            $sentence->bindValue(':id_respuesta_tipo', self::valorONulo(Flight::request()->data['id_respuesta_tipo'] ?? null));
            $sentence->bindValue(':incluir_whatsapp', !empty(Flight::request()->data['incluir_whatsapp']) ? 1 : 0, PDO::PARAM_INT);
            $sentence->bindValue(':variables_llenado', self::codificarVariables(Flight::request()->data['variables_llenado'] ?? null));
            $sentence->bindValue(':activo', isset(Flight::request()->data['activo']) ? (int)Flight::request()->data['activo'] : 1, PDO::PARAM_INT);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesPlantillas::replace: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar la plantilla'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            // Si ya se uso para enviar, se desactiva en vez de borrarse: las
            // notificaciones historicas la referencian.
            $verificar = $db->prepare("SELECT COUNT(*) AS usos FROM notificaciones WHERE id_plantilla = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $id);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();
            $usos = $verificar->fetch();

            if ($usos && (int)$usos['usos'] > 0) {
                $desactivar = $db->prepare("UPDATE notificaciones_plantillas SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
                $desactivar->bindParam(':id', $id);
                $desactivar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $desactivar->execute();

                Flight::json(array('id' => $id, 'desactivada' => true));
                return;
            }

            $sentence = $db->prepare("DELETE FROM notificaciones_plantillas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesPlantillas::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar la plantilla'), 500);
        }
    }

    /**
     * Suma un uso a la plantilla. Lo llama el servicio de notificaciones al
     * enviar, para poder ordenar las mas usadas de primero en el modal.
     *
     * @param PDO    $db
     * @param string $idPlantilla
     */
    public static function registrarUso(PDO $db, $idPlantilla)
    {
        if (empty($idPlantilla)) {
            return;
        }

        try {
            $sentence = $db->prepare("UPDATE notificaciones_plantillas SET veces_usada = veces_usada + 1 WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $idPlantilla);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
        } catch (Exception $e) {
            // El contador es informativo: si falla no debe tumbar el envio.
            error_log("Error en NotificacionesPlantillas::registrarUso: " . $e->getMessage());
        }
    }

    /**
     * Convierte variables_llenado de texto JSON a arreglo, para que el front
     * lo reciba listo y no tenga que parsearlo.
     */
    private static function decodificarVariables(array $filas)
    {
        foreach ($filas as &$fila) {
            if (!array_key_exists('variables_llenado', $fila)) {
                continue;
            }
            $decodificado = json_decode($fila['variables_llenado'] ?? '', true);
            $fila['variables_llenado'] = is_array($decodificado) ? $decodificado : array();
        }
        return $filas;
    }

    /**
     * Normaliza las variables de llenado antes de guardarlas. Solo se aceptan
     * pares variable + etiqueta, y el nombre de la variable se limpia para que
     * siempre coincida con el marcador del texto.
     */
    private static function codificarVariables($variables)
    {
        if (empty($variables) || !is_array($variables)) {
            return null;
        }

        $limpias = array();

        foreach ($variables as $variable) {
            $nombre = is_array($variable) ? ($variable['variable'] ?? '') : '';
            $nombre = trim(str_replace(array('{', '}'), '', $nombre));

            if ($nombre === '') {
                continue;
            }

            $limpias[] = array(
                'variable' => $nombre,
                'etiqueta' => is_array($variable) ? trim($variable['etiqueta'] ?? $nombre) : $nombre,
            );
        }

        return count($limpias) > 0 ? json_encode($limpias, JSON_UNESCAPED_UNICODE) : null;
    }

    private static function valorONulo($valor)
    {
        return ($valor === '' || $valor === null) ? null : $valor;
    }
}
