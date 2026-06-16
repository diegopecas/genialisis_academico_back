<?php 
class TarifasCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tce.id, tce.id_curso_extra, 
        tce.id_producto_matricula, tce.valor_matricula, tce.cuotas_matricula,
        tce.id_producto_pension, tce.valor_pension,
        tce.id_producto_unico, tce.valor_unico, tce.cuotas_unico,
        tce.anio,
        pm.nombre AS nombre_producto_matricula,
        pp.nombre AS nombre_producto_pension,
        pu.nombre AS nombre_producto_unico,
        ce.nombre AS nombre_curso
        FROM tarifas_cursos_extra tce
        INNER JOIN cursos_extra ce ON tce.id_curso_extra = ce.id
        LEFT JOIN productos_servicios pm ON tce.id_producto_matricula = pm.id
        LEFT JOIN productos_servicios pp ON tce.id_producto_pension = pp.id
        LEFT JOIN productos_servicios pu ON tce.id_producto_unico = pu.id
        ORDER BY tce.anio DESC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tce.id, tce.id_curso_extra, 
        tce.id_producto_matricula, tce.valor_matricula, tce.cuotas_matricula,
        tce.id_producto_pension, tce.valor_pension,
        tce.id_producto_unico, tce.valor_unico, tce.cuotas_unico,
        tce.anio,
        pm.nombre AS nombre_producto_matricula,
        pp.nombre AS nombre_producto_pension,
        pu.nombre AS nombre_producto_unico
        FROM tarifas_cursos_extra tce
        LEFT JOIN productos_servicios pm ON tce.id_producto_matricula = pm.id
        LEFT JOIN productos_servicios pp ON tce.id_producto_pension = pp.id
        LEFT JOIN productos_servicios pu ON tce.id_producto_unico = pu.id
        WHERE tce.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tce.id, tce.id_curso_extra, 
        tce.id_producto_matricula, tce.valor_matricula, tce.cuotas_matricula,
        tce.id_producto_pension, tce.valor_pension,
        tce.id_producto_unico, tce.valor_unico, tce.cuotas_unico,
        tce.anio,
        pm.nombre AS nombre_producto_matricula,
        pp.nombre AS nombre_producto_pension,
        pu.nombre AS nombre_producto_unico
        FROM tarifas_cursos_extra tce
        LEFT JOIN productos_servicios pm ON tce.id_producto_matricula = pm.id
        LEFT JOIN productos_servicios pp ON tce.id_producto_pension = pp.id
        LEFT JOIN productos_servicios pu ON tce.id_producto_unico = pu.id
        WHERE tce.id_curso_extra = :id_curso_extra
        ORDER BY tce.anio DESC");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_curso_extra = Flight::request()->data['id_curso_extra'];
        $id_producto_matricula = Flight::request()->data['id_producto_matricula'];
        $valor_matricula = Flight::request()->data['valor_matricula'];
        $cuotas_matricula = Flight::request()->data['cuotas_matricula'];
        $id_producto_pension = Flight::request()->data['id_producto_pension'];
        $valor_pension = Flight::request()->data['valor_pension'];
        $id_producto_unico = Flight::request()->data['id_producto_unico'];
        $valor_unico = Flight::request()->data['valor_unico'];
        $cuotas_unico = Flight::request()->data['cuotas_unico'];
        $anio = Flight::request()->data['anio'];

        $sentence = $db->prepare("INSERT INTO tarifas_cursos_extra(id_curso_extra, id_producto_matricula, valor_matricula, cuotas_matricula, 
        id_producto_pension, valor_pension, id_producto_unico, valor_unico, cuotas_unico, anio) 
        VALUES (:id_curso_extra, :id_producto_matricula, :valor_matricula, :cuotas_matricula, 
        :id_producto_pension, :valor_pension, :id_producto_unico, :valor_unico, :cuotas_unico, :anio)");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindParam(':id_producto_matricula', $id_producto_matricula);
        $sentence->bindParam(':valor_matricula', $valor_matricula);
        $sentence->bindParam(':cuotas_matricula', $cuotas_matricula, PDO::PARAM_INT);
        $sentence->bindParam(':id_producto_pension', $id_producto_pension);
        $sentence->bindParam(':valor_pension', $valor_pension);
        $sentence->bindParam(':id_producto_unico', $id_producto_unico);
        $sentence->bindParam(':valor_unico', $valor_unico);
        $sentence->bindParam(':cuotas_unico', $cuotas_unico, PDO::PARAM_INT);
        $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_producto_matricula = Flight::request()->data['id_producto_matricula'];
        $valor_matricula = Flight::request()->data['valor_matricula'];
        $cuotas_matricula = Flight::request()->data['cuotas_matricula'];
        $id_producto_pension = Flight::request()->data['id_producto_pension'];
        $valor_pension = Flight::request()->data['valor_pension'];
        $id_producto_unico = Flight::request()->data['id_producto_unico'];
        $valor_unico = Flight::request()->data['valor_unico'];
        $cuotas_unico = Flight::request()->data['cuotas_unico'];
        $anio = Flight::request()->data['anio'];

        $sentence = $db->prepare("UPDATE tarifas_cursos_extra SET id_producto_matricula = :id_producto_matricula, 
        valor_matricula = :valor_matricula, cuotas_matricula = :cuotas_matricula,
        id_producto_pension = :id_producto_pension, valor_pension = :valor_pension, 
        id_producto_unico = :id_producto_unico, valor_unico = :valor_unico, cuotas_unico = :cuotas_unico,
        anio = :anio WHERE id = :id");
        $sentence->bindParam(':id_producto_matricula', $id_producto_matricula);
        $sentence->bindParam(':valor_matricula', $valor_matricula);
        $sentence->bindParam(':cuotas_matricula', $cuotas_matricula, PDO::PARAM_INT);
        $sentence->bindParam(':id_producto_pension', $id_producto_pension);
        $sentence->bindParam(':valor_pension', $valor_pension);
        $sentence->bindParam(':id_producto_unico', $id_producto_unico);
        $sentence->bindParam(':valor_unico', $valor_unico);
        $sentence->bindParam(':cuotas_unico', $cuotas_unico, PDO::PARAM_INT);
        $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tarifas_cursos_extra WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

}