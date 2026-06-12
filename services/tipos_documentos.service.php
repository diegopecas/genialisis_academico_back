<?php
class TiposDocumentos
{
    // Obtener todos los tipos de documentos
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, requiere_vencimiento, dias_alerta_vencimiento, 
                   permite_multiples, descripcion, activo, modificable_acudientes, requiere_firma
            FROM tipos_documentos
            ORDER BY nombre
        ");
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
                tpd.obligatorio,
                tpd.orden
            FROM tipos_documentos td
            INNER JOIN tipos_personas_documentos tpd ON td.id = tpd.id_tipo_documento
            INNER JOIN tipos_personas tp ON tpd.id_tipo_persona = tp.id
            WHERE tp.codigo = :codigo_tipo_persona
              AND td.activo = 1
            ORDER BY tpd.orden, td.nombre
        ");
        $sentence->bindParam(':codigo_tipo_persona', $codigoTipoPersona);
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
                   permite_multiples, descripcion, activo, modificable_acudientes, requiere_firma
            FROM tipos_documentos
            WHERE id = :id
        ");
        $sentence->bindParam(':id', $id);
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

        $sentence = $db->prepare("
            INSERT INTO tipos_documentos (
                codigo, nombre, descripcion, requiere_vencimiento, 
                dias_alerta_vencimiento, permite_multiples, requiere_firma, 
                modificable_acudientes, activo
            ) VALUES (
                :codigo, :nombre, :descripcion, :requiere_vencimiento, 
                :dias_alerta_vencimiento, :permite_multiples, :requiere_firma, 
                :modificable_acudientes, :activo
            )
        ");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':requiere_vencimiento', $requiere_vencimiento);
        $sentence->bindParam(':dias_alerta_vencimiento', $dias_alerta_vencimiento);
        $sentence->bindParam(':permite_multiples', $permite_multiples);
        $sentence->bindParam(':requiere_firma', $requiere_firma);
        $sentence->bindParam(':modificable_acudientes', $modificable_acudientes);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        $id = $db->lastInsertId();
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
                activo = :activo
            WHERE id = :id
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
        $sentence->execute();

        self::getById($id);
    }

    // Eliminar un tipo de documento
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tipos_documentos WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
}