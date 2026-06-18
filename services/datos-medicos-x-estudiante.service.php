<?php
class DatosMedicosXEstudiante
{
    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT dme.id, dme.id_estudiante, dme.id_dato_medico,
            dme.valor_numero, dme.valor_texto, dme.valor_parrafo, dme.valor_fecha, dme.observacion,
            dm.nombre, dm.es_numero, dm.es_texto, dm.es_parrafo, dm.es_fecha, dm.opciones, dm.orden AS orden_dato,
            dm.id_tipo_dato_medico, tdm.nombre AS nombre_tipo, tdm.icono AS icono_tipo, tdm.orden AS orden_tipo
            FROM datos_medicos_x_estudiante dme
            INNER JOIN datos_medicos dm ON dme.id_dato_medico = dm.id
            INNER JOIN tipos_datos_medicos tdm ON dm.id_tipo_dato_medico = tdm.id
            WHERE dme.id_estudiante = :id_estudiante AND dme.id_tenant = :id_tenant
            ORDER BY tdm.orden, dm.orden");
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
            $sentence = $db->prepare("DELETE FROM datos_medicos_x_estudiante WHERE id_estudiante = :id_estudiante AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Insertar nuevos datos
            $insertados = 0;
            foreach ($datos as $dato) {
                $id_dato_medico = $dato['id_dato_medico'];
                $valor_numero = isset($dato['valor_numero']) && $dato['valor_numero'] !== '' ? $dato['valor_numero'] : null;
                $valor_texto = isset($dato['valor_texto']) && $dato['valor_texto'] !== '' ? $dato['valor_texto'] : null;
                $valor_parrafo = isset($dato['valor_parrafo']) && $dato['valor_parrafo'] !== '' ? $dato['valor_parrafo'] : null;
                $valor_fecha = isset($dato['valor_fecha']) && $dato['valor_fecha'] !== '' ? $dato['valor_fecha'] : null;
                $observacion = isset($dato['observacion']) && $dato['observacion'] !== '' ? $dato['observacion'] : null;

                // Solo insertar si tiene algún valor
                if ($valor_numero !== null || $valor_texto !== null || $valor_parrafo !== null || $valor_fecha !== null || $observacion !== null) {
                    $sentence = $db->prepare("INSERT INTO datos_medicos_x_estudiante 
                        (id_tenant, id_estudiante, id_dato_medico, valor_numero, valor_texto, valor_parrafo, valor_fecha, observacion) 
                        VALUES (:id_tenant, :id_estudiante, :id_dato_medico, :valor_numero, :valor_texto, :valor_parrafo, :valor_fecha, :observacion)");
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_estudiante', $id_estudiante);
                    $sentence->bindParam(':id_dato_medico', $id_dato_medico);
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
            error_log("Error en guardarPorEstudiante (médicos): " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}