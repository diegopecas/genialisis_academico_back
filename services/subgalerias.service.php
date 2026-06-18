<?php
class Subgalerias
{
    /**
     * Obtener todas las subgalerías
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT s.id, s.id_galeria, s.nombre, s.orden, g.nombre as galeria_nombre
            FROM subgalerias s
            INNER JOIN galerias g ON s.id_galeria = g.id
            WHERE s.id_tenant = :id_tenant
            ORDER BY s.id_galeria, s.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener subgalerías por galería
     */
    public static function getByGaleria($id_galeria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_galeria, nombre, orden 
            FROM subgalerias 
            WHERE id_galeria = :id_galeria 
            AND id_tenant = :id_tenant
            ORDER BY orden
        ");
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener subgalería por ID
     */
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, id_galeria, nombre, orden 
            FROM subgalerias 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    /**
     * Crear nueva subgalería
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("
            INSERT INTO subgalerias (id, id_tenant, id_galeria, nombre, orden) 
            VALUES (:id, :id_tenant, :id_galeria, :nombre, :orden)
        ");
        $idNew = Uuid::generar();
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_galeria', $data['id_galeria']);
        $sentence->bindParam(':nombre', $data['nombre']);
        $sentence->bindParam(':orden', $data['orden']);
        $sentence->execute();
        
        $id = $idNew;
        Flight::json(['id' => $id]);
    }

    /**
     * Actualizar subgalería
     */
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $sentence = $db->prepare("
            UPDATE subgalerias 
            SET id_galeria = :id_galeria, 
                nombre = :nombre, 
                orden = :orden 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $data['id']);
        $sentence->bindParam(':id_galeria', $data['id_galeria']);
        $sentence->bindParam(':nombre', $data['nombre']);
        $sentence->bindParam(':orden', $data['orden']);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        self::getById($data['id']);
    }

    /**
     * Eliminar subgalería
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM subgalerias WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        Flight::json(['deleted' => true, 'id' => $id]);
    }
}