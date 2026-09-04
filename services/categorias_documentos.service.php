<?php
class CategoriasDocumentos
{
    // Obtener todas las categorias del tenant
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, icono, orden, activo
            FROM categorias_documentos
            WHERE id_tenant = :id_tenant
            ORDER BY orden, nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Solo las activas, para los selectores
    public static function getActivas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, icono, orden
            FROM categorias_documentos
            WHERE id_tenant = :id_tenant
            AND activo = 1
            ORDER BY orden, nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Obtener una categoria por ID
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, icono, orden, activo
            FROM categorias_documentos
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Crear una categoria
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $codigo = $data['codigo'];
        $nombre = $data['nombre'];
        $icono = isset($data['icono']) ? $data['icono'] : null;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO categorias_documentos (
                id, id_tenant, codigo, nombre, icono, orden, activo
            ) VALUES (
                :id, :id_tenant, :codigo, :nombre, :icono, :orden, :activo
            )
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':codigo', $codigo);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    // Actualizar una categoria
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $codigo = $data['codigo'];
        $nombre = $data['nombre'];
        $icono = isset($data['icono']) ? $data['icono'] : null;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        $activo = isset($data['activo']) ? $data['activo'] : 1;

        $sentence = $db->prepare("
            UPDATE categorias_documentos SET
                codigo = :codigo,
                nombre = :nombre,
                icono = :icono,
                orden = :orden,
                activo = :activo
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':codigo', $codigo);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Eliminar una categoria.
     *
     * Se bloquea si hay tipos de documentos usandola: borrarla los dejaria
     * sueltos en "Otros" sin que nadie se entere. Primero hay que reasignarlos.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $verificar = $db->prepare("
            SELECT COUNT(*) AS total
            FROM tipos_documentos
            WHERE id_categoria = :id
            AND id_tenant = :id_tenant
        ");
        $verificar->bindParam(':id', $id);
        $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $fila = $verificar->fetch();

        if ($fila && (int) $fila['total'] > 0) {
            Flight::json(array(
                'error' => 'No se puede eliminar: hay ' . $fila['total'] .
                           ' tipo(s) de documento usando esta categoría.'
            ), 400);
            return;
        }

        $sentence = $db->prepare("
            DELETE FROM categorias_documentos
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
