<?php
/**
 * Catalogo de tipos de institucion cliente (Colegio, Escuela, Jardin
 * Infantil, Universidad). Es por tenant y se administra desde la pantalla
 * de Datos Maestros.
 *
 * getAll trae solo los activos porque es el que alimenta el selector del
 * formulario de instituciones cliente. El listado de administracion usa
 * getTodos, que trae activos e inactivos.
 */
class TiposInstitucion
{
    /**
     * Normaliza un campo de texto antes de guardarlo: quita espacios
     * sobrantes y convierte la cadena vacia en NULL.
     */
    private static function normalizarTexto($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * Tipo que ya tiene ese nombre en el tenant, o null. El indice unico
     * uq_tipos_institucion es (id_tenant, nombre), asi que no puede haber
     * dos con el mismo nombre dentro del mismo tenant.
     *
     * @param  PDO    $db
     * @param  string $nombre
     * @param  string $id_excluir Id que no cuenta, para el caso de editar
     * @return array|null
     */
    private static function buscarPorNombre(PDO $db, $nombre, $id_excluir = null)
    {
        if (empty($nombre)) {
            return null;
        }

        $sql = "SELECT id, nombre, activo
                FROM tipos_institucion
                WHERE nombre = :nombre
                  AND id_tenant = :id_tenant";

        if (!empty($id_excluir)) {
            $sql .= " AND id <> :id_excluir";
        }

        $sentence = $db->prepare($sql . " LIMIT 1");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if (!empty($id_excluir)) {
            $sentence->bindParam(':id_excluir', $id_excluir);
        }

        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    // Solo los activos: alimenta el selector del formulario de instituciones
    // cliente. Se conserva el nombre getAll para no romper a quien ya lo llama.
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_institucion WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Activos e inactivos, para el listado de administracion. Trae ademas
    // cuantas instituciones usan cada tipo, para que la pantalla pueda
    // advertir antes de intentar eliminar uno que esta en uso.
    public static function getTodos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT ti.id,
                   ti.nombre,
                   ti.descripcion,
                   ti.activo,
                   (SELECT COUNT(*)
                      FROM instituciones_cliente ic
                     WHERE ic.id_tipo_institucion = ti.id
                       AND ic.id_tenant = ti.id_tenant) AS total_instituciones
            FROM tipos_institucion ti
            WHERE ti.id_tenant = :id_tenant
            ORDER BY ti.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM tipos_institucion WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $nombre = self::normalizarTexto(isset($data['nombre']) ? $data['nombre'] : null);
            $descripcion = self::normalizarTexto(isset($data['descripcion']) ? $data['descripcion'] : null);
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;

            if (empty($nombre)) {
                Flight::json(array('error' => 'El nombre es obligatorio'), 400);
                return;
            }

            // El indice unico ya lo impide, pero reventaria con un error de
            // base. Aqui sale un mensaje que se entiende.
            if (self::buscarPorNombre($db, $nombre)) {
                Flight::json(array('error' => 'Ya existe un tipo de institución con el nombre ' . $nombre . '.'), 400);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO tipos_institucion (
                    id, id_tenant, nombre, descripcion, activo
                ) VALUES (
                    :id, :id_tenant, :nombre, :descripcion, :activo
                )
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $ok = $sentence->execute();

            if (!$ok) {
                error_log("Error: no se pudo insertar el tipo de institucion.");
                Flight::json(array('error' => 'No se pudo crear el tipo de institución.'), 500);
                return;
            }

            error_log("ID tipo institucion insertado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de tipos institucion: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $id = isset($data['id']) ? $data['id'] : null;
            $nombre = self::normalizarTexto(isset($data['nombre']) ? $data['nombre'] : null);
            $descripcion = self::normalizarTexto(isset($data['descripcion']) ? $data['descripcion'] : null);
            $activo = isset($data['activo']) ? (int) $data['activo'] : 1;

            if (!$id || empty($nombre)) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Se excluye el propio tipo: editarlo sin cambiarle el nombre no
            // puede chocar consigo mismo.
            if (self::buscarPorNombre($db, $nombre, $id)) {
                Flight::json(array('error' => 'Ya existe un tipo de institución con el nombre ' . $nombre . '.'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE tipos_institucion SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    activo = :activo
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en la ejecución del método replace de tipos institucion: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar el tipo de institución.'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID del tipo de institución a eliminar'), 400);
                return;
            }

            // La llave foranea de instituciones_cliente ya lo impide, pero el
            // error de base no le dice nada al usuario. Se valida antes para
            // poder explicarle por que no se puede borrar.
            $verif = $db->prepare("SELECT COUNT(*) AS total FROM instituciones_cliente
                                   WHERE id_tipo_institucion = :id AND id_tenant = :id_tenant");
            $verif->bindParam(':id', $id);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();
            $enUso = $verif->fetch();

            if ($enUso && (int) $enUso['total'] > 0) {
                Flight::json(array(
                    'error' => 'No se puede eliminar: hay ' . $enUso['total'] . ' institución(es) con este tipo. Desactívelo en lugar de eliminarlo.'
                ), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM tipos_institucion WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el tipo de institución con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'message' => 'Tipo de institución eliminado correctamente'));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método delete de tipos institucion: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar el tipo de institución.'), 500);
        }
    }
}
