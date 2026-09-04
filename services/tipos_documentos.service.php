<?php
class TiposDocumentos
{
    // Obtener todos los tipos de documentos
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT td.id, td.codigo, td.nombre, td.requiere_vencimiento, td.dias_alerta_vencimiento, 
                   td.permite_multiples, td.descripcion, td.activo, td.modificable_acudientes, td.requiere_firma,
                   td.id_categoria, cd.nombre AS categoria_nombre, cd.icono AS categoria_icono, cd.orden AS categoria_orden
            FROM tipos_documentos td
            LEFT JOIN categorias_documentos cd ON cd.id = td.id_categoria
            WHERE td.id_tenant = :id_tenant
            ORDER BY td.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Obtener tipos de documentos por tipo de persona
    public static function getByTipoPersona($codigoTipoPersona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                td.id,
                td.codigo,
                td.nombre,
                td.requiere_vencimiento,
                td.dias_alerta_vencimiento,
                td.permite_multiples,
                td.descripcion,
                td.modificable_acudientes,
                td.requiere_firma,
                td.id_categoria,
                cd.nombre AS categoria_nombre,
                cd.icono AS categoria_icono,
                cd.orden AS categoria_orden,
                tpd.obligatorio,
                tpd.orden
            FROM tipos_documentos td
            INNER JOIN tipos_personas_documentos tpd ON td.id = tpd.id_tipo_documento
            INNER JOIN tipos_personas tp ON tpd.id_tipo_persona = tp.id
            LEFT JOIN categorias_documentos cd ON cd.id = td.id_categoria
            WHERE tp.codigo = :codigo_tipo_persona
              AND td.activo = 1
              AND td.id_tenant = :id_tenant
            ORDER BY tpd.orden, td.nombre
        ");
        $sentence->bindParam(':codigo_tipo_persona', $codigoTipoPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Obtener un tipo de documento por ID
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, requiere_vencimiento, dias_alerta_vencimiento,
                   permite_multiples, descripcion, activo, modificable_acudientes, requiere_firma,
                   id_categoria
            FROM tipos_documentos
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Crear un nuevo tipo de documento
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $codigo = $data['codigo'];
        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
        $requiere_vencimiento = isset($data['requiere_vencimiento']) ? $data['requiere_vencimiento'] : 0;
        $dias_alerta_vencimiento = isset($data['dias_alerta_vencimiento']) ? $data['dias_alerta_vencimiento'] : null;
        $permite_multiples = isset($data['permite_multiples']) ? $data['permite_multiples'] : 1;
        $requiere_firma = isset($data['requiere_firma']) ? $data['requiere_firma'] : 0;
        $modificable_acudientes = isset($data['modificable_acudientes']) ? $data['modificable_acudientes'] : 1;
        $activo = isset($data['activo']) ? $data['activo'] : 1;
        $id_categoria = isset($data['id_categoria']) && $data['id_categoria'] !== '' ? $data['id_categoria'] : null;

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO tipos_documentos (
                id, id_tenant, codigo, nombre, descripcion, requiere_vencimiento, 
                dias_alerta_vencimiento, permite_multiples, requiere_firma, 
                modificable_acudientes, activo, id_categoria
            ) VALUES (
                :id, :id_tenant, :codigo, :nombre, :descripcion, :requiere_vencimiento, 
                :dias_alerta_vencimiento, :permite_multiples, :requiere_firma, 
                :modificable_acudientes, :activo, :id_categoria
            )
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':codigo', $codigo);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':requiere_vencimiento', $requiere_vencimiento);
        $sentence->bindParam(':dias_alerta_vencimiento', $dias_alerta_vencimiento);
        $sentence->bindParam(':permite_multiples', $permite_multiples);
        $sentence->bindParam(':requiere_firma', $requiere_firma);
        $sentence->bindParam(':modificable_acudientes', $modificable_acudientes);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id_categoria', $id_categoria);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    // Actualizar un tipo de documento
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $codigo = $data['codigo'];
        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
        $requiere_vencimiento = isset($data['requiere_vencimiento']) ? $data['requiere_vencimiento'] : 0;
        $dias_alerta_vencimiento = isset($data['dias_alerta_vencimiento']) ? $data['dias_alerta_vencimiento'] : null;
        $permite_multiples = isset($data['permite_multiples']) ? $data['permite_multiples'] : 1;
        $requiere_firma = isset($data['requiere_firma']) ? $data['requiere_firma'] : 0;
        $modificable_acudientes = isset($data['modificable_acudientes']) ? $data['modificable_acudientes'] : 1;
        $activo = isset($data['activo']) ? $data['activo'] : 1;
        $id_categoria = isset($data['id_categoria']) && $data['id_categoria'] !== '' ? $data['id_categoria'] : null;

        $sentence = $db->prepare("
            UPDATE tipos_documentos SET 
                codigo = :codigo, 
                nombre = :nombre, 
                descripcion = :descripcion, 
                requiere_vencimiento = :requiere_vencimiento,
                dias_alerta_vencimiento = :dias_alerta_vencimiento, 
                permite_multiples = :permite_multiples, 
                requiere_firma = :requiere_firma,
                modificable_acudientes = :modificable_acudientes, 
                activo = :activo,
                id_categoria = :id_categoria
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':codigo', $codigo);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':requiere_vencimiento', $requiere_vencimiento);
        $sentence->bindParam(':dias_alerta_vencimiento', $dias_alerta_vencimiento);
        $sentence->bindParam(':permite_multiples', $permite_multiples);
        $sentence->bindParam(':requiere_firma', $requiere_firma);
        $sentence->bindParam(':modificable_acudientes', $modificable_acudientes);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id_categoria', $id_categoria);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    // Eliminar un tipo de documento
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tipos_documentos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }
}