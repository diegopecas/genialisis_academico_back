<?php
/**
 * Catálogo de tipos de cobro de un producto (MATRICULA, PENSION, OTRO).
 * Vive en productos_servicios.id_tipo_cobro: un producto es matrícula o es
 * pensión, no depende del grupo ni del contrato.
 * Es un catálogo GLOBAL: no tiene id_tenant, las mismas tres filas valen para
 * todos los jardines, igual que periodicidad_cobro.
 * La comparación siempre se hace por `codigo`, nunca por id, igual que
 * roles_colaborador con 'DOCENTE' en colaboradores.service.php.
 */
class TiposCobroProducto
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tt.id, tt.codigo, tt.nombre, tt.activo
            FROM tipos_cobro_producto tt
            ORDER BY tt.nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tt.id, tt.codigo, tt.nombre, tt.activo
            FROM tipos_cobro_producto tt
            WHERE tt.activo = 1
            ORDER BY tt.nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tt.id, tt.codigo, tt.nombre, tt.activo
            FROM tipos_cobro_producto tt
            WHERE tt.id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCodigo($codigo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tt.id, tt.codigo, tt.nombre, tt.activo
            FROM tipos_cobro_producto tt
            WHERE tt.codigo = :codigo
        ");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    /**
     * Devuelve el codigo del tipo a partir de su id. Uso interno de otros
     * servicios que necesitan clasificar una fila sin exponer el id.
     */
    public static function codigoPorId($db, $idTipo)
    {
        if (empty($idTipo)) {
            return null;
        }

        $sentence = $db->prepare("
            SELECT codigo FROM tipos_cobro_producto
            WHERE id = :id
        ");
        $sentence->bindParam(':id', $idTipo);
        $sentence->execute();
        $row = $sentence->fetch();

        return $row ? $row['codigo'] : null;
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $codigo = Flight::request()->data['codigo'];
            $nombre = Flight::request()->data['nombre'];
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO tipos_cobro_producto
                                      (id, codigo, nombre, activo)
                                      VALUES (:id, :codigo, :nombre, :activo)");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':activo', $activo);

            $sentence->execute();

            Flight::json(array('id' => $idNew));
        } catch (Exception $e) {
            error_log("Error en TiposCobroProducto::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $codigo = Flight::request()->data['codigo'];
            $nombre = Flight::request()->data['nombre'];
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE tipos_cobro_producto SET
                                      codigo = :codigo,
                                      nombre = :nombre,
                                      activo = :activo
                                      WHERE id = :id");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':activo', $activo);

            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en TiposCobroProducto::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM tipos_cobro_producto WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
