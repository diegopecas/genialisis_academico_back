<?php
class VisitasCompromisos
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tc.nombre as nombre_compromiso,
                    v.fecha as fecha_visita,
                    v.hora as hora_visita
                FROM visitas_compromisos vc
                INNER JOIN tipos_compromisos tc ON vc.id_tipo_compromiso = tc.id
                INNER JOIN visitas v ON vc.id_visita = v.id
                ORDER BY v.fecha DESC, vc.fecha_compromiso ASC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tc.nombre as nombre_compromiso,
                    v.fecha as fecha_visita,
                    v.hora as hora_visita
                FROM visitas_compromisos vc
                INNER JOIN tipos_compromisos tc ON vc.id_tipo_compromiso = tc.id
                INNER JOIN visitas v ON vc.id_visita = v.id
                WHERE vc.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tc.nombre as nombre_compromiso
                FROM visitas_compromisos vc
                INNER JOIN tipos_compromisos tc ON vc.id_tipo_compromiso = tc.id
                WHERE vc.id_visita = :id_visita
                ORDER BY vc.fecha_compromiso ASC, vc.hora_compromiso ASC
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $sentence = $db->prepare("
                INSERT INTO visitas_compromisos (
                    id_visita, id_tipo_compromiso, 
                    fecha_compromiso, hora_compromiso, detalle_especifico
                ) VALUES (
                    :id_visita, :id_tipo_compromiso, 
                    :fecha_compromiso, :hora_compromiso, :detalle_especifico
                )
            ");

            $id_visita = $data['id_visita'] ?? null;
            $id_tipo_compromiso = $data['id_tipo_compromiso'] ?? null;
            $fecha_compromiso = $data['fecha_compromiso'] ?? null;
            $hora_compromiso = $data['hora_compromiso'] ?? null;
            $detalle_especifico = $data['detalle_especifico'] ?? null;

            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindParam(':id_tipo_compromiso', $id_tipo_compromiso);
            $sentence->bindParam(':fecha_compromiso', $fecha_compromiso);
            $sentence->bindParam(':hora_compromiso', $hora_compromiso);
            $sentence->bindParam(':detalle_especifico', $detalle_especifico);

            $sentence->execute();
            $id = $db->lastInsertId();

            // ✅ Si se llamó con parámetro, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos new: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
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

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $sentence = $db->prepare("
                UPDATE visitas_compromisos SET
                    id_tipo_compromiso = :id_tipo_compromiso,
                    fecha_compromiso = :fecha_compromiso,
                    hora_compromiso = :hora_compromiso,
                    detalle_especifico = :detalle_especifico
                WHERE id = :id
            ");

            $id = $data['id'];
            $id_tipo_compromiso = $data['id_tipo_compromiso'] ?? null;
            $fecha_compromiso = $data['fecha_compromiso'] ?? null;
            $hora_compromiso = $data['hora_compromiso'] ?? null;
            $detalle_especifico = $data['detalle_especifico'] ?? null;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_tipo_compromiso', $id_tipo_compromiso);
            $sentence->bindParam(':fecha_compromiso', $fecha_compromiso);
            $sentence->bindParam(':hora_compromiso', $hora_compromiso);
            $sentence->bindParam(':detalle_especifico', $detalle_especifico);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos replace: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
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

            $sentence = $db->prepare("DELETE FROM visitas_compromisos WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para guardar múltiples compromisos de una vez
    public static function guardarMultiples($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            $id_visita = $data['id_visita'];
            $compromisos = $data['compromisos'] ?? []; // Array de objetos

            // Primero eliminar los existentes
            $stmt = $db->prepare("DELETE FROM visitas_compromisos WHERE id_visita = :id_visita");
            $stmt->execute(['id_visita' => $id_visita]);

            // Insertar los nuevos usando el método new()
            if (is_array($compromisos) && count($compromisos) > 0) {
                foreach ($compromisos as $comp) {
                    $comp['id_visita'] = $id_visita;
                    self::new($comp);
                }
            }

            // ✅ Si se llamó con parámetro, retornar true
            if ($dataParam !== null) {
                return true;
            }

            Flight::json(array('success' => true, 'count' => count($compromisos)));
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos guardarMultiples: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para obtener compromisos pendientes (próximos)
    public static function getProximosCompromisos()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tc.nombre as nombre_compromiso,
                    v.fecha as fecha_visita,
                    (SELECT CONCAT(primer_nombre, ' ', primer_apellido) FROM visitantes WHERE id_visita = v.id AND es_contacto_principal = 1 LIMIT 1) as nombre_contacto,
                    (SELECT telefono FROM visitantes WHERE id_visita = v.id AND es_contacto_principal = 1 LIMIT 1) as telefono_contacto
                FROM visitas_compromisos vc
                INNER JOIN tipos_compromisos tc ON vc.id_tipo_compromiso = tc.id
                INNER JOIN visitas v ON vc.id_visita = v.id
                WHERE vc.fecha_compromiso >= CURDATE()
                ORDER BY vc.fecha_compromiso ASC, vc.hora_compromiso ASC
                LIMIT 50
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos getProximosCompromisos: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para obtener compromisos vencidos (no cumplidos)
    public static function getCompromisosVencidos()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vc.*,
                    tc.nombre as nombre_compromiso,
                    v.fecha as fecha_visita,
                    (SELECT CONCAT(primer_nombre, ' ', primer_apellido) FROM visitantes WHERE id_visita = v.id AND es_contacto_principal = 1 LIMIT 1) as nombre_contacto,
                    (SELECT telefono FROM visitantes WHERE id_visita = v.id AND es_contacto_principal = 1 LIMIT 1) as telefono_contacto
                FROM visitas_compromisos vc
                INNER JOIN tipos_compromisos tc ON vc.id_tipo_compromiso = tc.id
                INNER JOIN visitas v ON vc.id_visita = v.id
                WHERE vc.fecha_compromiso < CURDATE()
                ORDER BY vc.fecha_compromiso DESC
                LIMIT 50
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_compromisos getCompromisosVencidos: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}