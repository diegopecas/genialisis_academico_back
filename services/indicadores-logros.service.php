<?php
class IndicadoresLogros
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT
            il.id,
            il.nombre,
            il.id_logro,
            l.nombre AS nombre_logro,
            l.id_grado,
            gr.nombre AS nombre_grado,
            l.id_area_academica,
            aa.nombre AS area_academica_nombre,
            l.id_eje_curricular,
            ejc.nombre AS eje_curricular_nombre,
            l.id_esfera_desarrollo,
            esd.nombre AS esfera_desarrollo_nombre,
            l.id_competencia_cognitiva,
            ccg.nombre AS competencia_cognitiva_nombre,
            l.id_estandar_basico,
            estb.nombre AS estandar_basico_nombre,
            l.id_corte_academico,
            ca.nombre AS corte_academico_nombre,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', g.id, 'nombre', g.nombre))
             FROM grados_x_grupo gxg
             INNER JOIN grupos g ON gxg.id_grupo = g.id
             WHERE gxg.id_grado = l.id_grado) AS grupos_json
        FROM indicadores_logros il
        LEFT JOIN logros l
            ON il.id_logro = l.id
        LEFT JOIN grados gr
            ON l.id_grado = gr.id
        LEFT JOIN areas_academicas aa
            ON l.id_area_academica = aa.id
        LEFT JOIN ejes_curriculares ejc
            ON l.id_eje_curricular = ejc.id
        LEFT JOIN esferas_desarrollo esd
            ON l.id_esfera_desarrollo = esd.id
        LEFT JOIN competencias_cognitivas ccg
            ON l.id_competencia_cognitiva = ccg.id
        LEFT JOIN estandares_basicos estb
            ON l.id_estandar_basico = estb.id
        LEFT JOIN cortes_academicos ca
            ON l.id_corte_academico = ca.id
        WHERE il.id_tenant = :id_tenant
        ORDER BY il.id DESC
    ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }


    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT
            il.id,
            il.nombre,
            il.id_logro,
            l.nombre AS nombre_logro,
            l.id_grado,
            gr.nombre AS nombre_grado,
            l.id_area_academica,
            aa.nombre AS area_academica_nombre,
            l.id_eje_curricular,
            ejc.nombre AS eje_curricular_nombre,
            l.id_esfera_desarrollo,
            esd.nombre AS esfera_desarrollo_nombre,
            l.id_competencia_cognitiva,
            ccg.nombre AS competencia_cognitiva_nombre,
            l.id_estandar_basico,
            estb.nombre AS estandar_basico_nombre,
            l.id_corte_academico,
            ca.nombre AS corte_academico_nombre,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', g.id, 'nombre', g.nombre))
             FROM grados_x_grupo gxg
             INNER JOIN grupos g ON gxg.id_grupo = g.id
             WHERE gxg.id_grado = l.id_grado) AS grupos_json
        FROM indicadores_logros il
        LEFT JOIN logros l
            ON il.id_logro = l.id
        LEFT JOIN grados gr
            ON l.id_grado = gr.id
        LEFT JOIN areas_academicas aa
            ON l.id_area_academica = aa.id
        LEFT JOIN ejes_curriculares ejc
            ON l.id_eje_curricular = ejc.id
        LEFT JOIN esferas_desarrollo esd
            ON l.id_esfera_desarrollo = esd.id
        LEFT JOIN competencias_cognitivas ccg
            ON l.id_competencia_cognitiva = ccg.id
        LEFT JOIN estandares_basicos estb
            ON l.id_estandar_basico = estb.id
        LEFT JOIN cortes_academicos ca
            ON l.id_corte_academico = ca.id
        WHERE il.id = :id AND il.id_tenant = :id_tenant
    ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }


    public static function getByLogro($id_logro)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM indicadores_logros WHERE id_logro = :id_logro AND id_tenant = :id_tenant ORDER BY id");
        $sentence->bindParam(':id_logro', $id_logro);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $nombre = Flight::request()->data['nombre'];
            $id_logro = Flight::request()->data['id_logro'];

            error_log("Creando indicador de logro: nombre=$nombre, id_logro=$id_logro");

            // Validar datos requeridos
            if (!$nombre || !$id_logro) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO indicadores_logros (id, id_tenant, nombre, id_logro) VALUES (:id, :id_tenant, :nombre, :id_logro)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':id_logro', $id_logro);
            $sentence->execute();

            $id = $idNew;
            error_log("Indicador de logro creado con ID: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al crear indicador de logro: " . $e->getMessage());
            Flight::json(array('error' => 'Error al crear el indicador de logro: ' . $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $nombre = Flight::request()->data['nombre'];
            $id_logro = Flight::request()->data['id_logro'];

            error_log("Actualizando indicador de logro ID: $id");

            if (!$id || !$nombre || !$id_logro) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Verificar que el registro exista antes de actualizar
            $check = $db->prepare("SELECT id FROM indicadores_logros WHERE id = :id AND id_tenant = :id_tenant");
            $check->bindParam(':id', $id);
            $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $check->execute();
            if ($check->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el indicador de logro con el ID especificado'), 404);
                return;
            }

            $sentence = $db->prepare("UPDATE indicadores_logros SET nombre = :nombre, id_logro = :id_logro WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':id_logro', $id_logro);
            $sentence->execute();

            error_log("Indicador de logro actualizado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al actualizar indicador de logro: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar el indicador de logro: ' . $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Eliminando indicador de logro ID: $id");

            $sentence = $db->prepare("DELETE FROM indicadores_logros WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error al eliminar indicador de logro: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el indicador de logro: ' . $e->getMessage()), 500);
        }
    }
}