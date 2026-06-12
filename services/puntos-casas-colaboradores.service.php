<?php 
class PuntosCasasColaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pcc.id, pcc.id_colaborador_entrega, pcc.id_colaborador_recibe, 
        pcc.valor, pcc.fecha, pcc.id_casa_colaborador, pcc.observacion,
        cc.nombre nombre_casa_colaborador,
        CONCAT(IFNULL(pe.primer_nombre, ''), ' ', IFNULL(pe.segundo_nombre, ''), ' ', IFNULL(pe.primer_apellido, ''), ' ', IFNULL(pe.segundo_apellido, '')) AS nombre_colaborador_entrega,
        CONCAT(IFNULL(pr.primer_nombre, ''), ' ', IFNULL(pr.segundo_nombre, ''), ' ', IFNULL(pr.primer_apellido, ''), ' ', IFNULL(pr.segundo_apellido, '')) AS nombre_colaborador_recibe
        FROM puntos_casas_colaboradores pcc
        INNER JOIN casas_colaboradores cc ON pcc.id_casa_colaborador = cc.id
        LEFT OUTER JOIN colaboradores ce ON pcc.id_colaborador_entrega = ce.id
        LEFT OUTER JOIN personas pe ON ce.id_persona = pe.id
        LEFT OUTER JOIN colaboradores cr ON pcc.id_colaborador_recibe = cr.id
        LEFT OUTER JOIN personas pr ON cr.id_persona = pr.id
        ORDER BY pcc.fecha DESC");
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        // Limpiar nombres completos de espacios extras
        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador_entrega'])) {
                $row['nombre_colaborador_entrega'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador_entrega']));
            }
            if (isset($row['nombre_colaborador_recibe'])) {
                $row['nombre_colaborador_recibe'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador_recibe']));
            }
        }
        
        Flight::json($response);
    }

    public static function getAllByCasa($id_casa)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pcc.id, pcc.id_colaborador_entrega, pcc.id_colaborador_recibe, 
        pcc.valor, pcc.fecha, pcc.id_casa_colaborador, pcc.observacion,
        cc.nombre nombre_casa_colaborador,
        CONCAT(IFNULL(pe.primer_nombre, ''), ' ', IFNULL(pe.segundo_nombre, ''), ' ', IFNULL(pe.primer_apellido, ''), ' ', IFNULL(pe.segundo_apellido, '')) AS nombre_colaborador_entrega,
        CONCAT(IFNULL(pr.primer_nombre, ''), ' ', IFNULL(pr.segundo_nombre, ''), ' ', IFNULL(pr.primer_apellido, ''), ' ', IFNULL(pr.segundo_apellido, '')) AS nombre_colaborador_recibe
        FROM puntos_casas_colaboradores pcc
        INNER JOIN casas_colaboradores cc ON pcc.id_casa_colaborador = cc.id
        LEFT OUTER JOIN colaboradores ce ON pcc.id_colaborador_entrega = ce.id
        LEFT OUTER JOIN personas pe ON ce.id_persona = pe.id
        LEFT OUTER JOIN colaboradores cr ON pcc.id_colaborador_recibe = cr.id
        LEFT OUTER JOIN personas pr ON cr.id_persona = pr.id
        WHERE pcc.id_casa_colaborador = :id_casa
        ORDER BY pcc.fecha DESC");
        $sentence->bindParam(':id_casa', $id_casa);
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        // Limpiar nombres completos de espacios extras
        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador_entrega'])) {
                $row['nombre_colaborador_entrega'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador_entrega']));
            }
            if (isset($row['nombre_colaborador_recibe'])) {
                $row['nombre_colaborador_recibe'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador_recibe']));
            }
        }
        
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();
            
            $id_colaborador_entrega = isset(Flight::request()->data['id_colaborador_entrega']) ? 
                Flight::request()->data['id_colaborador_entrega'] : null;
            $id_colaborador_recibe = isset(Flight::request()->data['id_colaborador_recibe']) ? 
                Flight::request()->data['id_colaborador_recibe'] : null;
            $valor = Flight::request()->data['valor'];
            $id_casa_colaborador = Flight::request()->data['id_casa_colaborador'];
            $observacion = isset(Flight::request()->data['observacion']) ? 
                Flight::request()->data['observacion'] : null;
            $fecha = isset(Flight::request()->data['fecha']) ? 
                Flight::request()->data['fecha'] : date('Y-m-d H:i:s');
            
            error_log("Creando punto casa colaborador: id_colaborador_entrega=$id_colaborador_entrega, id_colaborador_recibe=$id_colaborador_recibe, valor=$valor, id_casa_colaborador=$id_casa_colaborador, observacion=$observacion, fecha=$fecha");
            
            // Insertar punto
            $sentence = $db->prepare("INSERT INTO puntos_casas_colaboradores(id_colaborador_entrega, id_colaborador_recibe, valor, fecha, id_casa_colaborador, observacion) 
                VALUES (:id_colaborador_entrega, :id_colaborador_recibe, :valor, :fecha, :id_casa_colaborador, :observacion)");
            $sentence->bindParam(':id_colaborador_entrega', $id_colaborador_entrega);
            $sentence->bindParam(':id_colaborador_recibe', $id_colaborador_recibe);
            $sentence->bindParam(':valor', $valor);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id_casa_colaborador', $id_casa_colaborador);
            $sentence->bindParam(':observacion', $observacion);
            $sentence->execute();
            
            $id = $db->lastInsertId();
            
            if ($id == 0) {
                $db->rollBack();
                error_log("Error: El ID insertado es 0.");
                Flight::json(array('error' => 'No se pudo crear el punto. Intente de nuevo.'), 500);
                return;
            }
            
            // Actualizar puntos de la casa colaborador
            if ($valor > 0) {
                // Sumar a puntos_entregar
                $updateCasa = $db->prepare("UPDATE casas_colaboradores SET puntos_entregar = puntos_entregar + :valor WHERE id = :id_casa");
                $updateCasa->bindParam(':valor', $valor);
                $updateCasa->bindParam(':id_casa', $id_casa_colaborador);
                $updateCasa->execute();
            } else {
                // Sumar a puntos_quitar (el valor ya viene negativo)
                $valorAbsoluto = abs($valor);
                $updateCasa = $db->prepare("UPDATE casas_colaboradores SET puntos_quitar = puntos_quitar + :valor WHERE id = :id_casa");
                $updateCasa->bindParam(':valor', $valorAbsoluto);
                $updateCasa->bindParam(':id_casa', $id_casa_colaborador);
                $updateCasa->execute();
            }
            
            $db->commit();
            
            error_log("Punto casa colaborador creado con ID: $id");
            Flight::json(array('id' => $id));
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en la ejecución del método new de puntos casas colaboradores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}