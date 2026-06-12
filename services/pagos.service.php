<?php 
class Pagos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada, referencia, id_persona_reporta, id_conceptos_pago, fecha, valor, estado from pagos");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada, referencia, id_persona_reporta, id_conceptos_pago, fecha, valor, estado from pagos where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByIdPersonaInvolucrada($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada, referencia, id_persona_reporta, id_conceptos_pago, fecha, valor, estado from pagos where id_persona_involucrada = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function new()
    {
        $db = Flight::db();
        $id_persona_involucrada = Flight::request()->data['id_persona_involucrada'];
        $referencia = Flight::request()->data['referencia'];
        $id_persona_reporta = Flight::request()->data['id_persona_reporta'];
        $id_conceptos_pago = Flight::request()->data['id_conceptos_pago'];
        $fecha = Flight::request()->data['fecha'];
        $valor = Flight::request()->data['valor'];
        $sentence = $db->prepare("insert into pagos(id_persona_involucrada, referencia, id_persona_reporta, id_conceptos_pago, fecha, valor) values (:id_persona_involucrada, :referencia, :id_persona_reporta, :id_conceptos_pago, :fecha, :valor)");
        $sentence->bindParam(':id_persona_involucrada', $id_persona_involucrada);
        $sentence->bindParam(':referencia', $referencia);
        $sentence->bindParam(':id_persona_reporta', $id_persona_reporta);
        $sentence->bindParam(':id_conceptos_pago', $id_conceptos_pago);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':valor', $valor);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

}
