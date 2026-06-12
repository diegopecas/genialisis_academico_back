<?php
class EstadosNomina {
    
    /**
     * Obtener todos los estados de nómina
     */
    public static function getAll() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    nombre,
                    activo
                FROM estados_nomina
                ORDER BY id ASC
            ");
            
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener estados de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estados activos
     */
    public static function getActivos() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    nombre,
                    activo
                FROM estados_nomina
                WHERE activo = 1
                ORDER BY id ASC
            ");
            
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener estados activos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener un estado por ID
     */
    public static function getById($id) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    nombre,
                    activo
                FROM estados_nomina
                WHERE id = :id
            ");
            
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener estado',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear nuevo estado
     */
    public static function new() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['nombre'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                INSERT INTO estados_nomina (
                    nombre,
                    activo
                ) VALUES (
                    :nombre,
                    :activo
                )
            ");
            
            $stmt->execute([
                'nombre' => $data['nombre'],
                'activo' => $data['activo'] ?? 1
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Estado creado correctamente',
                'id' => $db->lastInsertId()
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear estado',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar estado
     */
    public static function replace() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                UPDATE estados_nomina 
                SET 
                    nombre = :nombre,
                    activo = :activo
                WHERE id = :id
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'nombre' => $data['nombre'],
                'activo' => $data['activo']
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Estado actualizado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar estado',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar estado
     */
    public static function delete() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM estados_nomina WHERE id = :id");
            $stmt->execute(['id' => $data['id']]);
            
            Flight::json([
                'success' => true,
                'message' => 'Estado eliminado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar estado',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
?>
