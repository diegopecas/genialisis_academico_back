<?php 
class GradosXGrupo
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select gxg.id, gxg.id_grado, gxg.id_grupo, g.nombre nombre_grado, gr.nombre nombre_grupo 
        from grados_x_grupo gxg
        inner join grados g on gxg.id_grado = g.id
        inner join grupos gr on gxg.id_grupo = gr.id
        where gxg.id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select gxg.id, gxg.id_grado, gxg.id_grupo, g.nombre nombre_grado, gr.nombre nombre_grupo 
        from grados_x_grupo gxg
        inner join grados g on gxg.id_grado = g.id
        inner join grupos gr on gxg.id_grupo = gr.id
        where gxg.id = :id and gxg.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_grado = Flight::request()->data['id_grado'];
        $id_grupo = Flight::request()->data['id_grupo'];        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into grados_x_grupo(id, id_tenant, id_grado, id_grupo) values (:id, :id_tenant, :id_grado, :id_grupo)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_grado', $id_grado);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_grado = Flight::request()->data['id_grado'];
        $id_grupo = Flight::request()->data['id_grupo'];
        $sentence = $db->prepare("update grados_x_grupo set id_grado = :id_grado, id_grupo = :id_grupo where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id_grado', $id_grado);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from grados_x_grupo where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    // Obtener grados asociados a un grupo
    public static function getByGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT gxg.id, gxg.id_grado, gxg.id_grupo, g.nombre nombre_grado 
            FROM grados_x_grupo gxg
            INNER JOIN grados g ON gxg.id_grado = g.id
            WHERE gxg.id_grupo = :id_grupo AND gxg.id_tenant = :id_tenant
            ORDER BY g.nombre
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}