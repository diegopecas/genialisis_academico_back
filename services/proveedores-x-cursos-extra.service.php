<?php 
class ProveedoresXCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pxce.id, pxce.id_proveedor, pxce.id_curso_extra, pxce.activo, pxce.fecha_registro,
        CASE 
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo,
        ce.nombre AS nombre_curso
        FROM proveedores_x_cursos_extra pxce
        INNER JOIN proveedores pr ON pxce.id_proveedor = pr.id
        INNER JOIN personas p ON pr.id_persona = p.id
        INNER JOIN cursos_extra ce ON pxce.id_curso_extra = ce.id
        WHERE pxce.id_tenant = :id_tenant
        ORDER BY nombre_completo");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pxce.id, pxce.id_proveedor, pxce.id_curso_extra, pxce.activo, pxce.fecha_registro,
        CASE 
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo
        FROM proveedores_x_cursos_extra pxce
        INNER JOIN proveedores pr ON pxce.id_proveedor = pr.id
        INNER JOIN personas p ON pr.id_persona = p.id
        WHERE pxce.id = :id AND pxce.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pxce.id, pxce.id_proveedor, pxce.id_curso_extra, pxce.activo, pxce.fecha_registro,
        CASE 
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo
        FROM proveedores_x_cursos_extra pxce
        INNER JOIN proveedores pr ON pxce.id_proveedor = pr.id
        INNER JOIN personas p ON pr.id_persona = p.id
        WHERE pxce.id_curso_extra = :id_curso_extra AND pxce.id_tenant = :id_tenant
        ORDER BY nombre_completo");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_proveedor = Flight::request()->data['id_proveedor'];
        $id_curso_extra = Flight::request()->data['id_curso_extra'];

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO proveedores_x_cursos_extra(id, id_tenant, id_proveedor, id_curso_extra, activo, fecha_registro) 
        VALUES (:id, :id_tenant, :id_proveedor, :id_curso_extra, 1, NOW())");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_proveedor', $id_proveedor);
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE proveedores_x_cursos_extra SET activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM proveedores_x_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

}