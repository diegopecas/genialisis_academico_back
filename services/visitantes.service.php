<?php
class Visitantes
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    v.*,
                    tp.nombre as nombre_parentesco,
                    vis.fecha as fecha_visita,
                    vis.hora as hora_visita
                FROM visitantes v
                INNER JOIN tipos_parentesco tp ON v.id_parentesco = tp.id
                INNER JOIN visitas vis ON v.id_visita = vis.id
                ORDER BY vis.fecha DESC, vis.hora DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitantes getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    v.*,
                    tp.nombre as nombre_parentesco,
                    vis.fecha as fecha_visita,
                    vis.hora as hora_visita
                FROM visitantes v
                INNER JOIN tipos_parentesco tp ON v.id_parentesco = tp.id
                INNER JOIN visitas vis ON v.id_visita = vis.id
                WHERE v.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitantes getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    v.*,
                    tp.nombre as nombre_parentesco
                FROM visitantes v
                INNER JOIN tipos_parentesco tp ON v.id_parentesco = tp.id
                WHERE v.id_visita = :id_visita
                ORDER BY v.es_contacto_principal DESC, v.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitantes getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Leer los datos correctamente
            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $requestBody = Flight::request()->getBody();
                $data = json_decode($requestBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
                }
            }

            error_log("📥 Datos recibidos para crear visitante: " . json_encode($data));

            $sentence = $db->prepare("
            INSERT INTO visitantes (
                id_visita, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
                id_tipo_identificacion, numero_identificacion, id_genero, id_ciudad,
                telefono, email, id_parentesco, parentesco_otro, es_contacto_principal,
                asistio, perfil_disc_calculado
            ) VALUES (
                :id_visita, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido,
                :id_tipo_identificacion, :numero_identificacion, :id_genero, :id_ciudad,
                :telefono, :email, :id_parentesco, :parentesco_otro, :es_contacto_principal,
                :asistio, :perfil_disc_calculado
            )
        ");

            // ✅ Convertir booleanos
            $es_contacto_principal = isset($data['es_contacto_principal']) ? ($data['es_contacto_principal'] ? 1 : 0) : 0;
            $asistio = isset($data['asistio']) ? ($data['asistio'] ? 1 : 0) : 1;

            // ✅ Manejar NULL para campos de FK opcionales
            $id_tipo_identificacion = isset($data['id_tipo_identificacion']) && $data['id_tipo_identificacion'] !== '' && $data['id_tipo_identificacion'] !== 'null'
                ? $data['id_tipo_identificacion']
                : null;

            $id_genero = isset($data['id_genero']) && $data['id_genero'] !== '' && $data['id_genero'] !== 'null'
                ? $data['id_genero']
                : null;

            $id_ciudad = isset($data['id_ciudad']) && $data['id_ciudad'] !== '' && $data['id_ciudad'] !== 'null'
                ? $data['id_ciudad']
                : null;

            $id_parentesco = isset($data['id_parentesco']) && $data['id_parentesco'] !== '' && $data['id_parentesco'] !== 'null'
                ? $data['id_parentesco']
                : null;

            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':primer_nombre', $data['primer_nombre']);
            $sentence->bindParam(':segundo_nombre', $data['segundo_nombre']);
            $sentence->bindParam(':primer_apellido', $data['primer_apellido']);
            $sentence->bindParam(':segundo_apellido', $data['segundo_apellido']);
            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion, PDO::PARAM_INT);
            $sentence->bindParam(':numero_identificacion', $data['numero_identificacion']);
            $sentence->bindParam(':id_genero', $id_genero, PDO::PARAM_INT);
            $sentence->bindParam(':id_ciudad', $id_ciudad, PDO::PARAM_INT);
            $sentence->bindParam(':telefono', $data['telefono']);
            $sentence->bindParam(':email', $data['email']);
            $sentence->bindParam(':id_parentesco', $id_parentesco, PDO::PARAM_INT);
            $sentence->bindParam(':parentesco_otro', $data['parentesco_otro']);
            $sentence->bindParam(':es_contacto_principal', $es_contacto_principal);
            $sentence->bindParam(':asistio', $asistio);
            $sentence->bindParam(':perfil_disc_calculado', $data['perfil_disc_calculado']);

            $sentence->execute();
            $id = $db->lastInsertId();

            // ✅ Si se llamó desde otro método, retornar el ID
            if ($dataParam !== null) {
                return $id;
            }

            // ✅ Si se llamó desde endpoint, devolver el ID
            error_log("📤 Devolviendo ID al frontend: " . $id);
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("❌ Error en visitantes new: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());

            // ✅ Si se llamó desde otro método, lanzar excepción
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

            // ✅ Validar que exista el ID
            if (!isset($data['id']) || empty($data['id'])) {
                throw new Exception('ID de visitante requerido para actualizar');
            }

            $sentence = $db->prepare("
            UPDATE visitantes SET
                primer_nombre = :primer_nombre,
                segundo_nombre = :segundo_nombre,
                primer_apellido = :primer_apellido,
                segundo_apellido = :segundo_apellido,
                id_tipo_identificacion = :id_tipo_identificacion,
                numero_identificacion = :numero_identificacion,
                id_genero = :id_genero,
                id_ciudad = :id_ciudad,
                id_parentesco = :id_parentesco,
                parentesco_otro = :parentesco_otro,
                es_contacto_principal = :es_contacto_principal,
                telefono = :telefono,
                email = :email,
                asistio = :asistio,
                perfil_disc_calculado = :perfil_disc_calculado
            WHERE id = :id
        ");

            // ✅ Manejar conversión de booleanos
            $es_contacto_principal = isset($data['es_contacto_principal']) ? ($data['es_contacto_principal'] ? 1 : 0) : 0;
            $asistio = isset($data['asistio']) ? ($data['asistio'] ? 1 : 0) : 1;

            // ✅ Bind con variables temporales para manejar valores NULL
            $id = $data['id'];
            $primer_nombre = $data['primer_nombre'] ?? null;
            $segundo_nombre = $data['segundo_nombre'] ?? null;
            $primer_apellido = $data['primer_apellido'] ?? null;
            $segundo_apellido = $data['segundo_apellido'] ?? null;
            $id_tipo_identificacion = $data['id_tipo_identificacion'] ?? null;
            $numero_identificacion = $data['numero_identificacion'] ?? null;
            $id_genero = $data['id_genero'] ?? null;
            // ✅ Manejar null para id_ciudad
            $id_ciudad = isset($data['id_ciudad']) && $data['id_ciudad'] !== '' && $data['id_ciudad'] !== 'null'
                ? $data['id_ciudad']
                : null;
            $id_parentesco = $data['id_parentesco'] ?? null;
            $parentesco_otro = $data['parentesco_otro'] ?? null;
            $telefono = $data['telefono'] ?? null;
            $email = $data['email'] ?? null;
            $perfil_disc_calculado = $data['perfil_disc_calculado'] ?? null;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':primer_nombre', $primer_nombre);
            $sentence->bindParam(':segundo_nombre', $segundo_nombre);
            $sentence->bindParam(':primer_apellido', $primer_apellido);
            $sentence->bindParam(':segundo_apellido', $segundo_apellido);
            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion);
            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindParam(':id_genero', $id_genero);
            $sentence->bindParam(':id_ciudad', $id_ciudad, PDO::PARAM_INT);
            $sentence->bindParam(':id_parentesco', $id_parentesco);
            $sentence->bindParam(':parentesco_otro', $parentesco_otro);
            $sentence->bindParam(':es_contacto_principal', $es_contacto_principal);
            $sentence->bindParam(':telefono', $telefono);
            $sentence->bindParam(':email', $email);
            $sentence->bindParam(':asistio', $asistio);
            $sentence->bindParam(':perfil_disc_calculado', $perfil_disc_calculado);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar true, sino usar self::getById
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitantes replace: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción, sino usar Flight::json
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

            $sentence = $db->prepare("DELETE FROM visitantes WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitantes delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para actualizar el perfil DISC calculado
    public static function actualizarPerfilDisc()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE visitantes SET
                    perfil_disc_calculado = :perfil_disc_calculado
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':perfil_disc_calculado', $data['perfil_disc_calculado']);
            $sentence->execute();

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitantes actualizarPerfilDisc: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
