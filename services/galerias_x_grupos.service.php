<?php
class GaleriasXGrupos
{
    /**
     * Obtener todas las relaciones
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT gxg.id, gxg.id_galeria, gxg.id_grupo,
                   g.nombre as galeria_nombre,
                   gr.nombre as grupo_nombre
            FROM galerias_x_grupos gxg
            INNER JOIN galerias g ON gxg.id_galeria = g.id
            INNER JOIN grupos gr ON gxg.id_grupo = gr.id
            WHERE gxg.id_tenant = :id_tenant
            ORDER BY gxg.id_galeria, gr.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener grupos por galería
     */
    public static function getByGaleria($id_galeria)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT gxg.id, gxg.id_galeria, gxg.id_grupo,
                   gr.nombre as grupo_nombre, gr.icono, gr.color
            FROM galerias_x_grupos gxg
            INNER JOIN grupos gr ON gxg.id_grupo = gr.id
            WHERE gxg.id_galeria = :id_galeria AND gxg.id_tenant = :id_tenant
            ORDER BY gr.orden
        ");
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener galerías por grupo
     */
    public static function getByGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT gxg.id, gxg.id_galeria, gxg.id_grupo,
                   g.nombre as galeria_nombre, g.descripcion, g.fecha
            FROM galerias_x_grupos gxg
            INNER JOIN galerias g ON gxg.id_galeria = g.id
            WHERE gxg.id_grupo = :id_grupo
            AND g.activo = 1
            AND gxg.id_tenant = :id_tenant
            ORDER BY g.fecha DESC
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Crear nueva relación
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO galerias_x_grupos (id, id_tenant, id_galeria, id_grupo) 
            VALUES (:id, :id_tenant, :id_galeria, :id_grupo)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_galeria', $data['id_galeria']);
        $sentence->bindParam(':id_grupo', $data['id_grupo']);
        $sentence->execute();
        
        $id = $idNew;
        Flight::json(['id' => $id]);
    }

    /**
     * Asignar múltiples grupos a una galería
     */
    public static function assignGrupos()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        $id_galeria = $data['id_galeria'];
        $grupos = $data['grupos']; // Array de id_grupo
        
        // Eliminar asignaciones anteriores
        $deleteStmt = $db->prepare("DELETE FROM galerias_x_grupos WHERE id_galeria = :id_galeria AND id_tenant = :id_tenant");
        $deleteStmt->bindParam(':id_galeria', $id_galeria);
        $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $deleteStmt->execute();
        
        // Insertar nuevas asignaciones
        $insertStmt = $db->prepare("
            INSERT INTO galerias_x_grupos (id_tenant, id_galeria, id_grupo) 
            VALUES (:id_tenant, :id_galeria, :id_grupo)
        ");
        $insertStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        
        foreach ($grupos as $id_grupo) {
            $insertStmt->bindParam(':id_galeria', $id_galeria);
            $insertStmt->bindParam(':id_grupo', $id_grupo);
            $insertStmt->execute();
        }
        
        Flight::json(['success' => true, 'id_galeria' => $id_galeria, 'grupos_asignados' => count($grupos)]);
    }

    /**
     * Eliminar relación por ID
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM galerias_x_grupos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        Flight::json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Eliminar todas las relaciones de una galería
     */
    public static function deleteByGaleria($id_galeria)
    {
        $db = Flight::db();
        
        $sentence = $db->prepare("DELETE FROM galerias_x_grupos WHERE id_galeria = :id_galeria AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_galeria', $id_galeria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        Flight::json(['deleted' => true, 'id_galeria' => $id_galeria]);
    }
}