<?php
/**
 * Catalogo de tipos de novedad de los espacios (tipos_novedad_espacio):
 * lluvia, mantenimiento de la cancha, cancha ocupada por el club...
 * Lo usan las novedades de espacios (NovedadesEspacios).
 */
class TiposNovedadEspacio
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT t.id, t.nombre, t.descripcion, t.activo,
            (SELECT COUNT(*) FROM novedades_espacios n
             WHERE n.id_tipo_novedad_espacio = t.id AND n.id_tenant = t.id_tenant) AS total_novedades
            FROM tipos_novedad_espacio t
            WHERE t.id_tenant = :id_tenant
            ORDER BY t.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    // Solo los activos: es lo que se ofrece al registrar una novedad.
    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion
            FROM tipos_novedad_espacio
            WHERE id_tenant = :id_tenant AND activo = 1
            ORDER BY nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion, activo
            FROM tipos_novedad_espacio
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $nombre = isset($data['nombre']) ? trim((string) $data['nombre']) : '';
        if ($nombre === '') {
            Flight::json(array('error' => 'El nombre es obligatorio.'), 400);
            return;
        }
        if (self::nombreRepetido($db, $nombre, null)) {
            Flight::json(array('error' => 'Ya existe un tipo de novedad con ese nombre.'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO tipos_novedad_espacio (id, id_tenant, nombre, descripcion, activo)
            VALUES (:id, :id_tenant, :nombre, :descripcion, :activo)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':nombre', mb_substr($nombre, 0, 100));
        $sentence->bindValue(':descripcion', self::texto($data, 'descripcion', 300));
        $sentence->bindValue(':activo', isset($data['activo']) && !$data['activo'] ? 0 : 1, PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $nombre = isset($data['nombre']) ? trim((string) $data['nombre']) : '';
        if ($nombre === '') {
            Flight::json(array('error' => 'El nombre es obligatorio.'), 400);
            return;
        }
        if (self::nombreRepetido($db, $nombre, $id)) {
            Flight::json(array('error' => 'Ya existe un tipo de novedad con ese nombre.'), 400);
            return;
        }

        $sentence = $db->prepare("
            UPDATE tipos_novedad_espacio SET
                nombre = :nombre,
                descripcion = :descripcion,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':nombre', mb_substr($nombre, 0, 100));
        $sentence->bindValue(':descripcion', self::texto($data, 'descripcion', 300));
        $sentence->bindValue(':activo', isset($data['activo']) && !$data['activo'] ? 0 : 1, PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Se bloquea si hay novedades con el tipo: la FK lo rechazaria de todas
     * formas, pero asi el mensaje dice cuantas hay. Si ya no se usa, se
     * inactiva.
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $verificar = $db->prepare("
            SELECT COUNT(*) AS total FROM novedades_espacios
            WHERE id_tipo_novedad_espacio = :id AND id_tenant = :id_tenant
        ");
        $verificar->bindParam(':id', $id);
        $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $verificar->execute();
        $fila = $verificar->fetch();
        if ($fila && (int) $fila['total'] > 0) {
            Flight::json(array('error' => 'No se puede eliminar: hay ' . $fila['total'] .
                ' novedad(es) con este tipo. Si ya no se usa, inactívalo.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM tipos_novedad_espacio WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    private static function nombreRepetido($db, $nombre, $idExcluir)
    {
        $sentence = $db->prepare("SELECT id FROM tipos_novedad_espacio
            WHERE id_tenant = :id_tenant AND nombre = :nombre AND (:id_excluir IS NULL OR id <> :id_excluir2) LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':nombre', $nombre);
        $sentence->bindValue(':id_excluir', $idExcluir);
        $sentence->bindValue(':id_excluir2', $idExcluir);
        $sentence->execute();
        return (bool) $sentence->fetch();
    }

    private static function texto($data, $clave, $largo)
    {
        if (!isset($data[$clave]) || !is_scalar($data[$clave])) {
            return null;
        }
        $texto = trim((string) $data[$clave]);
        return $texto !== '' ? mb_substr($texto, 0, $largo) : null;
    }
}
