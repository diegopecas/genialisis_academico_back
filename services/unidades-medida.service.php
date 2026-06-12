<?php
class UnidadesMedida
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, abreviatura, id_tipo_unidad, activo
        FROM unidades_medida
        ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, abreviatura, id_tipo_unidad
        FROM unidades_medida
        WHERE activo = 1
        ORDER BY nombre");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, abreviatura, id_tipo_unidad, activo
        FROM unidades_medida
        WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $nombre = Flight::request()->data['nombre'];
            $abreviatura = Flight::request()->data['abreviatura'];
            $id_tipo_unidad = Flight::request()->data['id_tipo_unidad'] ?? 1;

            error_log("Datos recibidos para crear unidad de medida: nombre=$nombre, abreviatura=$abreviatura");

            $sentence = $db->prepare("INSERT INTO unidades_medida(
                nombre,
                abreviatura,
                id_tipo_unidad,
                activo
            ) VALUES (
                :nombre,
                :abreviatura,
                :id_tipo_unidad,
                1
            )");

            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':abreviatura', $abreviatura);
            $sentence->bindParam(':id_tipo_unidad', $id_tipo_unidad);
            $sentence->execute();

            $id = $db->lastInsertId();

            if ($id == 0) {
                error_log("Error: El ID insertado es 0.");
                Flight::json(array('error' => 'No se pudo crear la unidad de medida.'), 500);
                return;
            }

            error_log("ID unidad de medida insertado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de unidades de medida: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $abreviatura = Flight::request()->data['abreviatura'];
        $id_tipo_unidad = Flight::request()->data['id_tipo_unidad'] ?? 1;
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE unidades_medida SET 
                                nombre = :nombre,
                                abreviatura = :abreviatura,
                                id_tipo_unidad = :id_tipo_unidad,
                                activo = :activo
                                WHERE id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':abreviatura', $abreviatura);
        $sentence->bindParam(':id_tipo_unidad', $id_tipo_unidad);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM unidades_medida WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}