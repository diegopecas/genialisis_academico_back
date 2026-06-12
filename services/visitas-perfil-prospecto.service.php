<?php
class VisitasPerfilProspecto
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpp.*,
                    tpe.nombre as nombre_perfil_economico,
                    tne.nombre as nombre_nivel_exigencia,
                    tsc.nombre as nombre_semaforo,
                    tsc.color as color_semaforo,
                    v.fecha as fecha_visita
                FROM visitas_perfil_prospecto vpp
                LEFT JOIN tipos_perfil_economico tpe ON vpp.id_perfil_economico = tpe.id
                LEFT JOIN tipos_nivel_exigencia tne ON vpp.id_nivel_exigencia = tne.id
                LEFT JOIN tipos_semaforo_cliente tsc ON vpp.id_semaforo_cliente = tsc.id
                INNER JOIN visitas v ON vpp.id_visita = v.id
                ORDER BY v.fecha DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpp.*,
                    tpe.nombre as nombre_perfil_economico,
                    tne.nombre as nombre_nivel_exigencia,
                    tsc.nombre as nombre_semaforo,
                    tsc.color as color_semaforo
                FROM visitas_perfil_prospecto vpp
                LEFT JOIN tipos_perfil_economico tpe ON vpp.id_perfil_economico = tpe.id
                LEFT JOIN tipos_nivel_exigencia tne ON vpp.id_nivel_exigencia = tne.id
                LEFT JOIN tipos_semaforo_cliente tsc ON vpp.id_semaforo_cliente = tsc.id
                WHERE vpp.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vpp.*,
                    tpe.nombre as nombre_perfil_economico,
                    tne.nombre as nombre_nivel_exigencia,
                    tsc.nombre as nombre_semaforo,
                    tsc.color as color_semaforo
                FROM visitas_perfil_prospecto vpp
                LEFT JOIN tipos_perfil_economico tpe ON vpp.id_perfil_economico = tpe.id
                LEFT JOIN tipos_nivel_exigencia tne ON vpp.id_nivel_exigencia = tne.id
                LEFT JOIN tipos_semaforo_cliente tsc ON vpp.id_semaforo_cliente = tsc.id
                WHERE vpp.id_visita = :id_visita
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            INSERT INTO visitas_perfil_prospecto (
                id_visita, id_perfil_economico, id_nivel_exigencia, id_semaforo_cliente,
                senales_comportamiento, es_cliente_ideal, justificacion_cliente_ideal
            ) VALUES (
                :id_visita, :id_perfil_economico, :id_nivel_exigencia, :id_semaforo_cliente,
                :senales_comportamiento, :es_cliente_ideal, :justificacion_cliente_ideal
            )
        ");

            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':id_perfil_economico', $data['id_perfil_economico']);
            $sentence->bindParam(':id_nivel_exigencia', $data['id_nivel_exigencia']);
            $sentence->bindParam(':id_semaforo_cliente', $data['id_semaforo_cliente']);
            $sentence->bindParam(':senales_comportamiento', $data['senales_comportamiento']);
            $sentence->bindParam(':es_cliente_ideal', $data['es_cliente_ideal']);
            $sentence->bindParam(':justificacion_cliente_ideal', $data['justificacion_cliente_ideal']);

            $sentence->execute();
            $id = $db->lastInsertId();

            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto new: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace($dataParam = null)
    {
        try {
            $db = Flight::db();
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            UPDATE visitas_perfil_prospecto SET
                id_perfil_economico = :id_perfil_economico,
                id_nivel_exigencia = :id_nivel_exigencia,
                id_semaforo_cliente = :id_semaforo_cliente,
                senales_comportamiento = :senales_comportamiento,
                es_cliente_ideal = :es_cliente_ideal,
                justificacion_cliente_ideal = :justificacion_cliente_ideal
            WHERE id = :id
        ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_perfil_economico', $data['id_perfil_economico']);
            $sentence->bindParam(':id_nivel_exigencia', $data['id_nivel_exigencia']);
            $sentence->bindParam(':id_semaforo_cliente', $data['id_semaforo_cliente']);
            $sentence->bindParam(':senales_comportamiento', $data['senales_comportamiento']);
            $sentence->bindParam(':es_cliente_ideal', $data['es_cliente_ideal']);
            $sentence->bindParam(':justificacion_cliente_ideal', $data['justificacion_cliente_ideal']);

            $sentence->execute();

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto replace: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas_perfil_prospecto WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Guardar o actualizar (upsert)
    public static function guardar($dataParam = null)
    {
        try {
            $db = Flight::db();

            $data = $dataParam ?? Flight::request()->data;
            $id_visita = $data['id_visita'];

            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id FROM visitas_perfil_prospecto WHERE id_visita = :id_visita");
            $stmt->execute(['id_visita' => $id_visita]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existe) {
                $data['id'] = $existe['id'];
                self::replace($data);
            } else {
                self::new($data);
            }

            if ($dataParam !== null) {
                return ['success' => true];
            }

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en visitas_perfil_prospecto guardar: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
