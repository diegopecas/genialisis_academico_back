<?php
class ConceptosNomina {
    
    /**
     * Obtener todos los conceptos de nómina
     */
    public static function getAll() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    es_suma,
                    orden,
                    activo
                FROM conceptos_nomina
                ORDER BY orden ASC, nombre ASC
            ");
            
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener conceptos de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener conceptos activos (para selección en nóminas)
     */
    public static function getActivos() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    es_suma,
                    orden,
                    activo
                FROM conceptos_nomina
                WHERE activo = 1
                ORDER BY orden ASC, nombre ASC
            ");
            
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener conceptos activos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener un concepto por ID
     */
    public static function getById($id) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    es_suma,
                    orden,
                    activo
                FROM conceptos_nomina
                WHERE id = :id
            ");
            
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener concepto',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear nuevo concepto
     */
    public static function new() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['codigo']) || !isset($data['nombre'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                INSERT INTO conceptos_nomina (
                    codigo,
                    nombre,
                    es_suma,
                    orden,
                    activo
                ) VALUES (
                    :codigo,
                    :nombre,
                    :es_suma,
                    :orden,
                    :activo
                )
            ");
            
            $stmt->execute([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'es_suma' => $data['es_suma'] ?? 1,
                'orden' => $data['orden'] ?? 0,
                'activo' => $data['activo'] ?? 1
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Concepto creado correctamente',
                'id' => $db->lastInsertId()
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear concepto',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar concepto
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
                UPDATE conceptos_nomina 
                SET 
                    codigo = :codigo,
                    nombre = :nombre,
                    es_suma = :es_suma,
                    orden = :orden,
                    activo = :activo
                WHERE id = :id
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'es_suma' => $data['es_suma'],
                'orden' => $data['orden'],
                'activo' => $data['activo']
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Concepto actualizado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar concepto',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar concepto
     */
    public static function delete() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM conceptos_nomina WHERE id = :id");
            $stmt->execute(['id' => $data['id']]);
            
            Flight::json([
                'success' => true,
                'message' => 'Concepto eliminado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar concepto',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
?>
