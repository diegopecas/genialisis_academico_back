<?php
/**
 * Servicio de Contactos del Portal para GENIALISIS
 * Permite al equipo gestionar los contactos que llegan desde el portal web
 */
class ContactosPortal
{
    /**
     * Obtener todos los contactos del portal
     */
    public static function getAll()
    {
        try {
            // Verificar que exista la conexión al portal
            if (!defined('DB_PORTAL_DSN')) {
                Flight::json([
                    'error' => true,
                    'message' => 'Este jardín no tiene portal web configurado'
                ], 404);
                return;
            }

            $db = Flight::db_portal();
            $sentence = $db->prepare("
                SELECT 
                    c.id,
                    c.nombre_padre,
                    c.email,
                    c.telefono,
                    c.edad_nino,
                    c.mensaje,
                    c.como_conocio_detalle,
                    c.id_tipo_consulta,
                    tc.nombre AS tipo_consulta,
                    c.id_como_conocio,
                    tcc.nombre AS como_conocio,
                    c.id_programa_interes,
                    pi.nombre AS programa_interes,
                    c.id_estado,
                    ec.nombre AS estado,
                    ec.color AS estado_color,
                    c.notas_internas,
                    c.fecha_cita,
                    c.cita_estado,
                    c.calendly_event_uri,
                    c.calendly_invitee_uri,
                    c.calendly_event_type,
                    c.created_at,
                    c.updated_at,
                    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i') AS fecha_registro_formato,
                    DATE_FORMAT(c.fecha_cita, '%Y-%m-%d %H:%i') AS fecha_cita_formato
                FROM contactos c
                LEFT JOIN tipos_consulta tc ON c.id_tipo_consulta = tc.id
                LEFT JOIN tipos_como_conocio tcc ON c.id_como_conocio = tcc.id
                LEFT JOIN programas_interes pi ON c.id_programa_interes = pi.id
                LEFT JOIN estados_contacto ec ON c.id_estado = ec.id
                ORDER BY c.created_at DESC
            ");
            
            $sentence->execute();
            $response = $sentence->fetchAll();
            
            Flight::json($response);
            
        } catch (Exception $e) {
            error_log("Error en ContactosPortal::getAll: " . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener contactos del portal'
            ], 500);
        }
    }

    /**
     * Obtener contacto por ID
     */
    public static function getById($id)
    {
        try {
            if (!defined('DB_PORTAL_DSN')) {
                Flight::json([
                    'error' => true,
                    'message' => 'Este jardín no tiene portal web configurado'
                ], 404);
                return;
            }

            $db = Flight::db_portal();
            $sentence = $db->prepare("
                SELECT 
                    c.id,
                    c.nombre_padre,
                    c.email,
                    c.telefono,
                    c.edad_nino,
                    c.mensaje,
                    c.como_conocio_detalle,
                    c.id_tipo_consulta,
                    tc.nombre AS tipo_consulta,
                    c.id_como_conocio,
                    tcc.nombre AS como_conocio,
                    c.id_programa_interes,
                    pi.nombre AS programa_interes,
                    c.id_estado,
                    ec.nombre AS estado,
                    ec.color AS estado_color,
                    c.notas_internas,
                    c.fecha_cita,
                    c.cita_estado,
                    c.calendly_event_uri,
                    c.calendly_invitee_uri,
                    c.calendly_event_type,
                    c.ip_address,
                    c.user_agent,
                    c.created_at,
                    c.updated_at,
                    DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i') AS fecha_registro_formato,
                    DATE_FORMAT(c.fecha_cita, '%Y-%m-%d %H:%i') AS fecha_cita_formato
                FROM contactos c
                LEFT JOIN tipos_consulta tc ON c.id_tipo_consulta = tc.id
                LEFT JOIN tipos_como_conocio tcc ON c.id_como_conocio = tcc.id
                LEFT JOIN programas_interes pi ON c.id_programa_interes = pi.id
                LEFT JOIN estados_contacto ec ON c.id_estado = ec.id
                WHERE c.id = :id
            ");
            
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll();
            
            Flight::json($response);
            
        } catch (Exception $e) {
            error_log("Error en ContactosPortal::getById: " . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener contacto'
            ], 500);
        }
    }

    /**
     * Actualizar contacto (solo campos editables desde GENIALISIS)
     */
    public static function replace()
    {
        try {
            if (!defined('DB_PORTAL_DSN')) {
                Flight::json([
                    'error' => true,
                    'message' => 'Este jardín no tiene portal web configurado'
                ], 404);
                return;
            }

            $db = Flight::db_portal();
            $id = Flight::request()->data['id'];
            $id_estado = Flight::request()->data['id_estado'] ?? null;
            $notas_internas = Flight::request()->data['notas_internas'] ?? null;
            $fecha_cita = Flight::request()->data['fecha_cita'] ?? null;
            $cita_estado = Flight::request()->data['cita_estado'] ?? null;

            // Construir query dinámicamente solo con los campos que se pueden editar
            $sentence = $db->prepare("
                UPDATE contactos SET 
                    id_estado = :id_estado,
                    notas_internas = :notas_internas,
                    fecha_cita = :fecha_cita,
                    cita_estado = :cita_estado,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':notas_internas', $notas_internas);
            $sentence->bindParam(':fecha_cita', $fecha_cita);
            $sentence->bindParam(':cita_estado', $cita_estado);
            $sentence->execute();

            error_log("✅ Contacto actualizado: ID=$id");
            
            // Devolver el contacto actualizado
            self::getById($id);
            
        } catch (Exception $e) {
            error_log("Error en ContactosPortal::replace: " . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al actualizar contacto'
            ], 500);
        }
    }

    /**
     * Obtener catálogos para el formulario
     */
    public static function getCatalogos()
    {
        try {
            if (!defined('DB_PORTAL_DSN')) {
                Flight::json([
                    'error' => true,
                    'message' => 'Este jardín no tiene portal web configurado'
                ], 404);
                return;
            }

            $db = Flight::db_portal();
            
            $catalogos = [
                'estados_contacto' => [],
                'tipos_consulta' => [],
                'tipos_como_conocio' => [],
                'programas_interes' => [],
                'estados_cita' => [
                    ['id' => 'pendiente', 'nombre' => 'Pendiente'],
                    ['id' => 'confirmada', 'nombre' => 'Confirmada'],
                    ['id' => 'cancelada', 'nombre' => 'Cancelada'],
                    ['id' => 'completada', 'nombre' => 'Completada']
                ]
            ];
            
            // Estados de contacto
            $stmt = $db->query("SELECT id, nombre, color FROM estados_contacto WHERE activo = 1 ORDER BY orden");
            $catalogos['estados_contacto'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tipos de consulta
            $stmt = $db->query("SELECT id, nombre FROM tipos_consulta WHERE activo = 1 ORDER BY orden");
            $catalogos['tipos_consulta'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tipos cómo conoció
            $stmt = $db->query("SELECT id, nombre FROM tipos_como_conocio WHERE activo = 1 ORDER BY id");
            $catalogos['tipos_como_conocio'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Programas de interés
            $stmt = $db->query("SELECT id, nombre, descripcion FROM programas_interes WHERE activo = 1 ORDER BY orden");
            $catalogos['programas_interes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($catalogos);
            
        } catch (Exception $e) {
            error_log("Error en ContactosPortal::getCatalogos: " . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener catálogos'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de contactos
     */
    public static function getEstadisticas()
    {
        try {
            if (!defined('DB_PORTAL_DSN')) {
                Flight::json([
                    'error' => true,
                    'message' => 'Este jardín no tiene portal web configurado'
                ], 404);
                return;
            }

            $db = Flight::db_portal();
            
            // Total de contactos
            $stmt = $db->query("SELECT COUNT(*) as total FROM contactos");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Contactos por estado
            $stmt = $db->query("
                SELECT 
                    ec.nombre,
                    ec.color,
                    COUNT(*) as cantidad
                FROM contactos c
                LEFT JOIN estados_contacto ec ON c.id_estado = ec.id
                GROUP BY c.id_estado, ec.nombre, ec.color
                ORDER BY cantidad DESC
            ");
            $porEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contactos del mes actual
            $stmt = $db->query("
                SELECT COUNT(*) as total 
                FROM contactos 
                WHERE YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $esteMes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Citas programadas
            $stmt = $db->query("
                SELECT COUNT(*) as total 
                FROM contactos 
                WHERE fecha_cita IS NOT NULL 
                AND fecha_cita >= CURDATE()
                AND cita_estado != 'cancelada'
            ");
            $citasProgramadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            Flight::json([
                'total' => $total,
                'por_estado' => $porEstado,
                'este_mes' => $esteMes,
                'citas_programadas' => $citasProgramadas
            ]);
            
        } catch (Exception $e) {
            error_log("Error en ContactosPortal::getEstadisticas: " . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
}