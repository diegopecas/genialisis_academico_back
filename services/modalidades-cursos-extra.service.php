<?php
/**
 * Catálogo de modalidades de los cursos extracurriculares (GRUPAL,
 * PERSONALIZADO). La modalidad dice cómo funciona el curso: el grupal tiene
 * horario fijo y un grupo inscrito por un periodo; el personalizado no tiene
 * horario y cada sesión se agenda cuando se pide.
 * Es distinto de tipos_cursos_extracurriculares, que es la categoría del curso
 * (Deportes, Artes...) y la define cada jardín.
 * Es un catálogo GLOBAL: no tiene id_tenant, las mismas filas valen para todos
 * los jardines, igual que tipos_cobro_producto.
 * La comparación siempre se hace por `codigo`, nunca por id ni por nombre.
 */
class ModalidadesCursosExtra
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT m.id, m.codigo, m.nombre, m.descripcion, m.orden, m.activo
            FROM modalidades_cursos_extra m
            ORDER BY m.orden, m.nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT m.id, m.codigo, m.nombre, m.descripcion, m.orden, m.activo
            FROM modalidades_cursos_extra m
            WHERE m.activo = 1
            ORDER BY m.orden, m.nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT m.id, m.codigo, m.nombre, m.descripcion, m.orden, m.activo
            FROM modalidades_cursos_extra m
            WHERE m.id = :id
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
            SELECT m.id, m.codigo, m.nombre, m.descripcion, m.orden, m.activo
            FROM modalidades_cursos_extra m
            WHERE m.codigo = :codigo
        ");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    /**
     * Devuelve el id de la modalidad a partir de su codigo. Uso interno de
     * otros servicios, por ejemplo para dejar GRUPAL cuando el curso llega
     * sin modalidad.
     */
    public static function idPorCodigo($db, $codigo)
    {
        $sentence = $db->prepare("
            SELECT id FROM modalidades_cursos_extra
            WHERE codigo = :codigo
        ");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $row = $sentence->fetch();

        return $row ? $row['id'] : null;
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $codigo = Flight::request()->data['codigo'];
            $nombre = Flight::request()->data['nombre'];
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO modalidades_cursos_extra
                                      (id, codigo, nombre, descripcion, orden, activo)
                                      VALUES (:id, :codigo, :nombre, :descripcion, :orden, :activo)");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindValue(':descripcion', $descripcion);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindParam(':activo', $activo);

            $sentence->execute();

            Flight::json(array('id' => $idNew));
        } catch (Exception $e) {
            error_log("Error en ModalidadesCursosExtra::new: " . $e->getMessage());
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
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;
            $orden = isset(Flight::request()->data['orden']) ? Flight::request()->data['orden'] : 0;
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;

            $sentence = $db->prepare("UPDATE modalidades_cursos_extra SET
                                      codigo = :codigo,
                                      nombre = :nombre,
                                      descripcion = :descripcion,
                                      orden = :orden,
                                      activo = :activo
                                      WHERE id = :id");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindValue(':descripcion', $descripcion);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindParam(':activo', $activo);

            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en ModalidadesCursosExtra::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM modalidades_cursos_extra WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
