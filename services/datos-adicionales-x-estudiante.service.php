<?php
class DatosAdicionalesXEstudiante
{
    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dae.id, dae.id_estudiante, dae.id_dato_adicional,
            dae.valor_numero, dae.valor_texto, dae.valor_parrafo, dae.valor_fecha, dae.observacion,
            da.nombre, da.es_numero, da.es_texto, da.es_parrafo, da.es_fecha, da.opciones, da.orden AS orden_dato,
            da.id_tipo_dato_adicional, tda.nombre AS nombre_tipo, tda.icono AS icono_tipo, tda.orden AS orden_tipo
            FROM datos_adicionales_x_estudiante dae
            INNER JOIN datos_adicionales da ON dae.id_dato_adicional = da.id
            INNER JOIN tipos_datos_adicionales tda ON da.id_tipo_dato_adicional = tda.id
            WHERE dae.id_estudiante = :id_estudiante AND dae.id_tenant = :id_tenant
            ORDER BY tda.orden, da.orden");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function guardarPorEstudiante()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $db = Flight::db();
            $db->beginTransaction();

            $id_estudiante = Flight::request()->data['id_estudiante'];
            $datos = Flight::request()->data['datos'];

            // Eliminar datos anteriores del estudiante
            $sentence = $db->prepare("DELETE FROM datos_adicionales_x_estudiante WHERE id_estudiante = :id_estudiante AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Insertar nuevos datos
            $insertados = 0;
            foreach ($datos as $dato) {
                $id_dato_adicional = $dato['id_dato_adicional'];
                $valor_numero = isset($dato['valor_numero']) && $dato['valor_numero'] !== '' ? $dato['valor_numero'] : null;
                $valor_texto = isset($dato['valor_texto']) && $dato['valor_texto'] !== '' ? $dato['valor_texto'] : null;
                $valor_parrafo = isset($dato['valor_parrafo']) && $dato['valor_parrafo'] !== '' ? $dato['valor_parrafo'] : null;
                $valor_fecha = isset($dato['valor_fecha']) && $dato['valor_fecha'] !== '' ? $dato['valor_fecha'] : null;
                $observacion = isset($dato['observacion']) && $dato['observacion'] !== '' ? $dato['observacion'] : null;

                // Solo insertar si tiene algún valor
                if ($valor_numero !== null || $valor_texto !== null || $valor_parrafo !== null || $valor_fecha !== null || $observacion !== null) {
                    $sentence = $db->prepare("INSERT INTO datos_adicionales_x_estudiante 
                        (id_tenant, id_estudiante, id_dato_adicional, valor_numero, valor_texto, valor_parrafo, valor_fecha, observacion) 
                        VALUES (:id_tenant, :id_estudiante, :id_dato_adicional, :valor_numero, :valor_texto, :valor_parrafo, :valor_fecha, :observacion)");
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_estudiante', $id_estudiante);
                    $sentence->bindParam(':id_dato_adicional', $id_dato_adicional);
                    $sentence->bindParam(':valor_numero', $valor_numero);
                    $sentence->bindParam(':valor_texto', $valor_texto);
                    $sentence->bindParam(':valor_parrafo', $valor_parrafo);
                    $sentence->bindParam(':valor_fecha', $valor_fecha);
                    $sentence->bindParam(':observacion', $observacion);
                    $sentence->execute();
                    $insertados++;
                }
            }

            $db->commit();
            Flight::json(array('success' => true, 'insertados' => $insertados));

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en guardarPorEstudiante (adicionales): " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}