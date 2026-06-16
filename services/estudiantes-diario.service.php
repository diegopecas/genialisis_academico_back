<?php 
class EstudiantesDiario
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select * from estudiantes_diario");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();    
        $nombre_estudiante = Flight::request()->data['nombre_estudiante'];
        $id_tipo_identificacion_est = Flight::request()->data['id_tipo_identificacion_est'];    
        $numero_identificacion_est = Flight::request()->data['numero_identificacion_est'];
        $nombre_acudiente_entrega = Flight::request()->data['nombre_acudiente_entrega'];    
        $id_tipo_identificacion_acu_entrega = Flight::request()->data['id_tipo_identificacion_acu_entrega'];
        $numero_identificacion_acu_entrega = Flight::request()->data['numero_identificacion_acu_entrega'];    
        $nombre_acudiente_recoge = Flight::request()->data['nombre_acudiente_recoge'];
        $id_tipo_identificacion_acu_recoge = Flight::request()->data['id_tipo_identificacion_acu_recoge'];    
        $numero_identificacion_acu_recoge = Flight::request()->data['numero_identificacion_acu_recoge'];
        $observacion_ingreso = Flight::request()->data['observacion_ingreso'];    
        $sentence = $db->prepare("insert into estudiantes_diario (
            nombre_estudiante,
            id_tipo_identificacion_est,
            numero_identificacion_est,
            nombre_acudiente_entrega,
            id_tipo_identificacion_acu_entrega,
            numero_identificacion_acu_entrega,
            nombre_acudiente_recoge,
            id_tipo_identificacion_acu_recoge,
            numero_identificacion_acu_recoge,
            fecha_ingreso,
            observacion_ingreso
        ) values (
            :nombre_estudiante,
            :id_tipo_identificacion_est,
            :numero_identificacion_est,
            :nombre_acudiente_entrega,
            :id_tipo_identificacion_acu_entrega,
            :numero_identificacion_acu_entrega,
            :nombre_acudiente_recoge,
            :id_tipo_identificacion_acu_recoge,
            :numero_identificacion_acu_recoge,
            CURRENT_TIMESTAMP,
            :observacion_ingreso
        )");

        $sentence->bindParam(':nombre_estudiante', $nombre_estudiante);
        $sentence->bindParam(':id_tipo_identificacion_est', $id_tipo_identificacion_est);
        $sentence->bindParam(':numero_identificacion_est', $numero_identificacion_est);
        $sentence->bindParam(':nombre_acudiente_entrega', $nombre_acudiente_entrega);
        $sentence->bindParam(':id_tipo_identificacion_acu_entrega', $id_tipo_identificacion_acu_entrega);
        $sentence->bindParam(':numero_identificacion_acu_entrega', $numero_identificacion_acu_entrega);
        $sentence->bindParam(':nombre_acudiente_recoge', $nombre_acudiente_recoge);
        $sentence->bindParam(':id_tipo_identificacion_acu_recoge', $id_tipo_identificacion_acu_recoge);
        $sentence->bindParam(':numero_identificacion_acu_recoge', $numero_identificacion_acu_recoge);
        $sentence->bindParam(':observacion_ingreso', $observacion_ingreso);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $observacion_salida = Flight::request()->data['observacion_salida'];        
        $sentence = $db->prepare("update estudiantes_diario set fecha_salida = CURRENT_TIMESTAMP, observacion_salida = :observacion_salida where id = :id");
        $sentence->bindParam(':observacion_salida', $observacion_salida);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
}