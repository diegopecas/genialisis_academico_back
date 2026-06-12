<?php
class AutorizacionesHabeasData
{
    /**
     * Obtiene la versión actual de la política desde configuracion_global
     */
    private static function getVersionActual()
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = 'habeas_data_version_actual' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['valor_texto'] : '1.0';
    }

    public static function getByUsuario($id_usuario)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM autorizaciones_habeas_data 
                                      WHERE id_usuario = :id_usuario 
                                      ORDER BY fecha_aceptacion DESC 
                                      LIMIT 1");
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->execute();
            $response = $sentence->fetch();

            if ($response) {
                Flight::json($response);
            } else {
                Flight::json(null);
            }
        } catch (Exception $e) {
            error_log("Error en AutorizacionesHabeasData::getByUsuario: " . $e->getMessage());
            Flight::json(array('error' => 'Error al consultar autorización'), 500);
        }
    }

    public static function verificar($id_usuario)
    {
        try {
            $db = Flight::db();

            // Leer versión desde configuracion_global
            $version_actual = self::getVersionActual();
            // Consultar todos los registros del usuario para debug
            $stmtDebug = $db->prepare("SELECT id, id_usuario, version_politica FROM autorizaciones_habeas_data WHERE id_usuario = :id_usuario");
            $stmtDebug->bindParam(':id_usuario', $id_usuario);
            $stmtDebug->execute();
            $registros = $stmtDebug->fetchAll();
            

            // Comparar string directo
            $sentence = $db->prepare("SELECT id FROM autorizaciones_habeas_data 
                                  WHERE id_usuario = :id_usuario 
                                  AND version_politica = :version
                                  LIMIT 1");
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->bindParam(':version', $version_actual);
            $sentence->execute();
            $response = $sentence->fetch();
            Flight::json(array(
                'autorizado' => $response ? true : false,
                'version_actual' => $version_actual,
                'debug' => array(
                    'id_usuario' => $id_usuario,
                    'version_buscada' => $version_actual,
                    'tipo_version' => gettype($version_actual),
                    'registros_usuario' => $registros,
                    'match' => $response ? true : false
                )
            ));
        } catch (Exception $e) {
            error_log("HABEAS_DEBUG - ERROR: " . $e->getMessage());
            Flight::json(array('error' => 'Error al verificar autorización'), 500);
        }
    }

    public static function getPlantilla()
    {
        try {
            $db = Flight::db();

            // Leer versión desde configuracion_global
            $version_actual = self::getVersionActual();

            // Buscar todas las plantillas de habeas data
            $sentence = $db->prepare("SELECT contenido FROM plantillas WHERE clave = 'politica_habeas_data'");
            $sentence->execute();
            $plantillas = $sentence->fetchAll();

            $plantillaEncontrada = null;

            // Buscar la plantilla cuyo JSON tenga la versión actual
            foreach ($plantillas as $p) {
                $contenido = json_decode($p['contenido'], true);
                if ($contenido && isset($contenido['version']) && strval($contenido['version']) === $version_actual) {
                    $plantillaEncontrada = $p['contenido'];
                    break;
                }
            }

            if (!$plantillaEncontrada) {
                Flight::json(array(
                    'error' => 'No existe una plantilla de política para la versión ' . $version_actual,
                    'version_requerida' => $version_actual
                ), 404);
                return;
            }

            if ($plantillaEncontrada) {
                // Obtener variables de configuración global
                $stmtConfig = $db->prepare("SELECT clave, valor_texto FROM configuracion_global 
                                            WHERE clave IN (
                                                'institucion_nombre', 'institucion_nit', 'institucion_direccion',
                                                'institucion_telefono', 'institucion_email', 'institucion_web'
                                            )");
                $stmtConfig->execute();
                $configs = $stmtConfig->fetchAll();

                $variables = [];
                foreach ($configs as $config) {
                    $variables['{{' . $config['clave'] . '}}'] = $config['valor_texto'] ?? '';
                }

                // Reemplazar variables en el contenido
                foreach ($variables as $key => $value) {
                    $plantillaEncontrada = str_replace($key, $value, $plantillaEncontrada);
                }

                Flight::json(array(
                    'contenido' => json_decode($plantillaEncontrada, true),
                    'version' => $version_actual
                ));
            } else {
                Flight::json(array('error' => 'Plantilla no encontrada'), 404);
            }
        } catch (Exception $e) {
            error_log("Error en AutorizacionesHabeasData::getPlantilla: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener plantilla'), 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_usuario = Flight::request()->data['id_usuario'];
            $id_persona = Flight::request()->data['id_persona'];
            $version_politica = self::getVersionActual();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if (!$id_usuario || !$id_persona) {
                Flight::json(array('error' => 'Faltan datos requeridos'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO autorizaciones_habeas_data 
                (id_usuario, id_persona, version_politica, ip_address, user_agent)
                VALUES (:id_usuario, :id_persona, :version_politica, :ip_address, :user_agent)");

            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':version_politica', $version_politica);
            $sentence->bindParam(':ip_address', $ip_address);
            $sentence->bindParam(':user_agent', $user_agent);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Autorización registrada correctamente'
            ));
        } catch (Exception $e) {
            error_log("Error en AutorizacionesHabeasData::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al registrar autorización'), 500);
        }
    }
}
