<?php
class ProtocoloContenidoPerfil
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT pcp.*, pp.nombre as nombre_paso 
            FROM protocolo_contenido_perfil pcp
            INNER JOIN protocolo_pasos pp ON pcp.id_protocolo_paso = pp.id
            ORDER BY pp.orden, pcp.perfil
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM protocolo_contenido_perfil WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPaso($id_paso)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM protocolo_contenido_perfil WHERE id_protocolo_paso = :id_paso ORDER BY perfil");
        $sentence->bindParam(':id_paso', $id_paso);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPasoYPerfil($id_paso, $perfil)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM protocolo_contenido_perfil WHERE id_protocolo_paso = :id_paso AND perfil = :perfil");
        $sentence->bindParam(':id_paso', $id_paso);
        $sentence->bindParam(':perfil', $perfil);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_protocolo_paso = Flight::request()->data['id_protocolo_paso'];
            $perfil = isset(Flight::request()->data['perfil']) ? Flight::request()->data['perfil'] : null;
            $puntos_enfatizar = isset(Flight::request()->data['puntos_enfatizar']) ? Flight::request()->data['puntos_enfatizar'] : null;
            $frases_efectivas = isset(Flight::request()->data['frases_efectivas']) ? Flight::request()->data['frases_efectivas'] : null;
            $que_mostrar = isset(Flight::request()->data['que_mostrar']) ? Flight::request()->data['que_mostrar'] : null;
            $que_evitar = isset(Flight::request()->data['que_evitar']) ? Flight::request()->data['que_evitar'] : null;

            $sentence = $db->prepare("INSERT INTO protocolo_contenido_perfil (id_protocolo_paso, perfil, puntos_enfatizar, frases_efectivas, que_mostrar, que_evitar) VALUES (:id_protocolo_paso, :perfil, :puntos_enfatizar, :frases_efectivas, :que_mostrar, :que_evitar)");
            $sentence->bindParam(':id_protocolo_paso', $id_protocolo_paso);
            $sentence->bindParam(':perfil', $perfil);
            $sentence->bindParam(':puntos_enfatizar', $puntos_enfatizar);
            $sentence->bindParam(':frases_efectivas', $frases_efectivas);
            $sentence->bindParam(':que_mostrar', $que_mostrar);
            $sentence->bindParam(':que_evitar', $que_evitar);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en protocolo_contenido_perfil new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_protocolo_paso = Flight::request()->data['id_protocolo_paso'];
            $perfil = isset(Flight::request()->data['perfil']) ? Flight::request()->data['perfil'] : null;
            $puntos_enfatizar = isset(Flight::request()->data['puntos_enfatizar']) ? Flight::request()->data['puntos_enfatizar'] : null;
            $frases_efectivas = isset(Flight::request()->data['frases_efectivas']) ? Flight::request()->data['frases_efectivas'] : null;
            $que_mostrar = isset(Flight::request()->data['que_mostrar']) ? Flight::request()->data['que_mostrar'] : null;
            $que_evitar = isset(Flight::request()->data['que_evitar']) ? Flight::request()->data['que_evitar'] : null;

            $sentence = $db->prepare("UPDATE protocolo_contenido_perfil SET id_protocolo_paso = :id_protocolo_paso, perfil = :perfil, puntos_enfatizar = :puntos_enfatizar, frases_efectivas = :frases_efectivas, que_mostrar = :que_mostrar, que_evitar = :que_evitar WHERE id = :id");
            $sentence->bindParam(':id_protocolo_paso', $id_protocolo_paso);
            $sentence->bindParam(':perfil', $perfil);
            $sentence->bindParam(':puntos_enfatizar', $puntos_enfatizar);
            $sentence->bindParam(':frases_efectivas', $frases_efectivas);
            $sentence->bindParam(':que_mostrar', $que_mostrar);
            $sentence->bindParam(':que_evitar', $que_evitar);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en protocolo_contenido_perfil replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM protocolo_contenido_perfil WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en protocolo_contenido_perfil delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    public static function getByPerfil($perfil)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT pcp.*, pp.nombre as nombre_paso, pp.orden
            FROM protocolo_contenido_perfil pcp
            INNER JOIN protocolo_pasos pp ON pcp.id_protocolo_paso = pp.id
            WHERE pcp.perfil = :perfil
            ORDER BY pp.orden
        ");
        $sentence->bindParam(':perfil', $perfil);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }
}
