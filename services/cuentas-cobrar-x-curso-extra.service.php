<?php 
class CuentasCobrarXCursoExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante_x_curso_extra, id_cuenta_por_cobrar, fecha_registro
        FROM cuentas_cobrar_x_curso_extra
        ORDER BY fecha_registro DESC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_estudiante_x_curso_extra, id_cuenta_por_cobrar, fecha_registro
        FROM cuentas_cobrar_x_curso_extra
        WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Trae las cuentas por cobrar asociadas a una inscripcion incluyendo el valor pagado
    // y nombre del producto. Lo usa el preview del retiro para mostrar que se anulara
    // y que se conservara antes de confirmar.
    public static function getByInscripcion($id_estudiante_x_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ccxce.id, ccxce.id_estudiante_x_curso_extra, ccxce.id_cuenta_por_cobrar, ccxce.fecha_registro,
        cpc.id_producto_servicio, cpc.fecha, cpc.valor, cpc.detalle, cpc.anulado,
        ps.nombre AS nombre_producto,
        COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado
        FROM cuentas_cobrar_x_curso_extra ccxce
        INNER JOIN cuentas_por_cobrar cpc ON ccxce.id_cuenta_por_cobrar = cpc.id
        INNER JOIN productos_servicios ps ON cpc.id_producto_servicio = ps.id
        LEFT JOIN cuenta_pagada cp ON cpc.id = cp.id_cuenta_por_cobrar
        WHERE ccxce.id_estudiante_x_curso_extra = :id_estudiante_x_curso_extra
        GROUP BY ccxce.id, cpc.id, ps.nombre
        ORDER BY cpc.fecha");
        $sentence->bindParam(':id_estudiante_x_curso_extra', $id_estudiante_x_curso_extra);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_estudiante_x_curso_extra = Flight::request()->data['id_estudiante_x_curso_extra'];
        $id_cuenta_por_cobrar = Flight::request()->data['id_cuenta_por_cobrar'];

        $sentence = $db->prepare("INSERT INTO cuentas_cobrar_x_curso_extra(id_estudiante_x_curso_extra, id_cuenta_por_cobrar, fecha_registro) 
        VALUES (:id_estudiante_x_curso_extra, :id_cuenta_por_cobrar, NOW())");
        $sentence->bindParam(':id_estudiante_x_curso_extra', $id_estudiante_x_curso_extra);
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM cuentas_cobrar_x_curso_extra WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

}