<?php 
class Medidas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                m.id, m.nombre, m.codigo, m.unidad,
                m.id_categoria, m.id_unidad, m.id_tipo_valor, m.orden,
                cm.nombre AS categoria_nombre,
                cm.icono AS categoria_icono,
                umc.abreviatura AS unidad_abreviatura,
                umc.nombre AS unidad_nombre,
                tvm.nombre AS tipo_valor
            FROM medidas m
            LEFT JOIN categorias_medidas cm ON cm.id = m.id_categoria
            LEFT JOIN unidades_medidas_corporales umc ON umc.id = m.id_unidad
            LEFT JOIN tipos_valor_medida tvm ON tvm.id = m.id_tipo_valor
            WHERE m.id_tenant = :id_tenant
            ORDER BY m.id_categoria, m.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                m.id, m.nombre, m.codigo, m.unidad,
                m.id_categoria, m.id_unidad, m.id_tipo_valor, m.orden,
                umc.abreviatura AS unidad_abreviatura,
                tvm.nombre AS tipo_valor
            FROM medidas m
            LEFT JOIN unidades_medidas_corporales umc ON umc.id = m.id_unidad
            LEFT JOIN tipos_valor_medida tvm ON tvm.id = m.id_tipo_valor
            WHERE m.id = :id AND m.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];       
        $unidad = isset(Flight::request()->data['unidad']) ? Flight::request()->data['unidad'] : null;
        $id_categoria = isset(Flight::request()->data['id_categoria']) ? Flight::request()->data['id_categoria'] : null;
        $id_unidad = isset(Flight::request()->data['id_unidad']) ? Flight::request()->data['id_unidad'] : null;
        $id_tipo_valor = isset(Flight::request()->data['id_tipo_valor']) ? Flight::request()->data['id_tipo_valor'] : 1;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO medidas(id, id_tenant, nombre, unidad, id_categoria, id_unidad, id_tipo_valor, orden) 
            VALUES (:id, :id_tenant, :nombre, :unidad, :id_categoria, :id_unidad, :id_tipo_valor, :orden)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':unidad', $unidad);
        $sentence->bindParam(':id_categoria', $id_categoria);
        $sentence->bindParam(':id_unidad', $id_unidad);
        $sentence->bindParam(':id_tipo_valor', $id_tipo_valor);
        $sentence->bindParam(':orden', $orden);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $unidad = isset(Flight::request()->data['unidad']) ? Flight::request()->data['unidad'] : null;
        $id_categoria = isset(Flight::request()->data['id_categoria']) ? Flight::request()->data['id_categoria'] : null;
        $id_unidad = isset(Flight::request()->data['id_unidad']) ? Flight::request()->data['id_unidad'] : null;
        $id_tipo_valor = isset(Flight::request()->data['id_tipo_valor']) ? Flight::request()->data['id_tipo_valor'] : 1;
        $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;

        $sentence = $db->prepare("
            UPDATE medidas 
            SET nombre = :nombre, unidad = :unidad, id_categoria = :id_categoria, 
                id_unidad = :id_unidad, id_tipo_valor = :id_tipo_valor, orden = :orden 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':unidad', $unidad);
        $sentence->bindParam(':id_categoria', $id_categoria);
        $sentence->bindParam(':id_unidad', $id_unidad);
        $sentence->bindParam(':id_tipo_valor', $id_tipo_valor);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM medidas WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }
}