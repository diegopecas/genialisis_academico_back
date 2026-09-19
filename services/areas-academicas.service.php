<?php 
class AreasAcademicas
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color, es_extracurricular from areas_academicas where id_tenant = :id_tenant order by nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getAllList()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, es_extracurricular from areas_academicas where id_tenant = :id_tenant order by nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color, es_extracurricular from areas_academicas where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'] ?? '#FFFFFF';
        // Opcional: los formularios viejos no lo envian y el area queda regular.
        $es_extracurricular = Flight::request()->data['es_extracurricular'] ?? 0;
        
        $sentence = $db->prepare("insert into areas_academicas(id, id_tenant, nombre, icono, color, es_extracurricular) values (:id, :id_tenant, :nombre, :icono, :color, :es_extracurricular)");
        $idNew = Uuid::generar();
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindValue(':es_extracurricular', $es_extracurricular, PDO::PARAM_INT);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'] ?? '#FFFFFF';
        // Opcional: los formularios viejos no lo envian y el area queda regular.
        $es_extracurricular = Flight::request()->data['es_extracurricular'] ?? 0;
        
        $sentence = $db->prepare("update areas_academicas set nombre = :nombre, icono = :icono, color = :color, es_extracurricular = :es_extracurricular where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindValue(':es_extracurricular', $es_extracurricular, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from areas_academicas where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }
    
    /**
     * Areas marcadas como extracurriculares.
     * Alimenta el selector de area del curso extracurricular; las areas
     * regulares no se ofrecen ahi.
     */
    public static function getExtracurriculares()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color from areas_academicas where id_tenant = :id_tenant and es_extracurricular = 1 order by nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Areas regulares (no extracurriculares).
     * Se usa donde antes se listaban todas: asignacion de areas a grupos y
     * selectores de la malla academica normal.
     */
    public static function getRegulares()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color from areas_academicas where id_tenant = :id_tenant and es_extracurricular = 0 order by nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            select g.id id_grupo, g.nombre nombre_grupo, a.id id_area_academica, a.nombre nombre_area_academica, axg.id_docente, a.icono, a.color
            from area_academica_x_grupo axg 
            inner join areas_academicas a on axg.id_area_academica = a.id
            inner join grupos g on axg.id_grupo = g.id 
            where g.id = :id
            and axg.id_tenant = :id_tenant
            ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getDisponiblesPorGrupo($id_grupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, icono, color
            FROM areas_academicas 
            WHERE es_extracurricular = 0
            AND id NOT IN (
                SELECT id_area_academica 
                FROM area_academica_x_grupo 
                WHERE id_grupo = :id_grupo
            )
            AND id_tenant = :id_tenant
            ORDER BY nombre
        ");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}