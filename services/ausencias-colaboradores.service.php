<?php
class AusenciasColaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            ta.nombre as nombre_tipo_ausencia,
            ca.nombre as nombre_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            ur.nombre as nombre_usuario_registro,
            ua.nombre as nombre_usuario_aprobacion,
            uc.nombre as nombre_usuario_contabilizacion
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            INNER JOIN usuarios ur ON ac.id_usuario_registro = ur.id
            LEFT JOIN usuarios ua ON ac.id_usuario_aprobacion = ua.id
            LEFT JOIN usuarios uc ON ac.id_usuario_contabilizacion = uc.id
            ORDER BY ac.fecha_registro DESC");
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            ta.nombre as nombre_tipo_ausencia,
            ta.valor_hora,
            ca.nombre as nombre_categoria,
            ca.id as id_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            ur.nombre as nombre_usuario_registro,
            ua.nombre as nombre_usuario_aprobacion,
            uc.nombre as nombre_usuario_contabilizacion
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            INNER JOIN usuarios ur ON ac.id_usuario_registro = ur.id
            LEFT JOIN usuarios ua ON ac.id_usuario_aprobacion = ua.id
            LEFT JOIN usuarios uc ON ac.id_usuario_contabilizacion = uc.id
            WHERE ac.id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();

        if (!empty($response)) {
            foreach ($response as &$row) {
                if (isset($row['nombre_colaborador'])) {
                    $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
                }
            }
        }

        Flight::json($response);
    }

    public static function getByColaborador($id_colaborador)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            ta.nombre as nombre_tipo_ausencia,
            ta.valor_hora,
            ca.nombre as nombre_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
            WHERE ac.id_colaborador = :id_colaborador
            ORDER BY ac.fecha_registro DESC");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByDocente($id_docente)
    {
        // Mantener compatibilidad: buscar el colaborador del docente
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            ta.nombre as nombre_tipo_ausencia,
            ta.valor_hora,
            ca.nombre as nombre_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN docentes d ON d.id_colaborador = c.id
            WHERE d.id = :id_docente
            ORDER BY ac.fecha_registro DESC");
        $sentence->bindParam(':id_docente', $id_docente);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstado($id_estado)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            ta.nombre as nombre_tipo_ausencia,
            ca.nombre as nombre_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            WHERE ac.id_estado = :id_estado
            ORDER BY ac.fecha_registro DESC");
        $sentence->bindParam(':id_estado', $id_estado);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getPendientesAprobar()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
        ta.nombre as nombre_tipo_ausencia,
        ta.valor_hora,
        ca.nombre as nombre_categoria,
        ea.nombre as nombre_estado,
        ea.color as color_estado,
        CONCAT(IFNULL(pc.primer_nombre, ''), ' ', IFNULL(pc.segundo_nombre, ''), ' ', 
               IFNULL(pc.primer_apellido, ''), ' ', IFNULL(pc.segundo_apellido, '')) as nombre_colaborador,
        CONCAT(IFNULL(pur.primer_nombre, ''), ' ', IFNULL(pur.primer_apellido, '')) as nombre_usuario_registro,
        CONCAT(IFNULL(pua.primer_nombre, ''), ' ', IFNULL(pua.primer_apellido, '')) as nombre_usuario_aprobacion,
        CONCAT(IFNULL(puc.primer_nombre, ''), ' ', IFNULL(puc.primer_apellido, '')) as nombre_usuario_contabilizacion
        FROM ausencias_colaboradores ac
        INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
        INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
        INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
        INNER JOIN colaboradores c ON ac.id_colaborador = c.id
        INNER JOIN personas pc ON c.id_persona = pc.id
        INNER JOIN usuarios ur ON ac.id_usuario_registro = ur.id
        INNER JOIN personas pur ON ur.id_persona = pur.id
        LEFT JOIN usuarios ua ON ac.id_usuario_aprobacion = ua.id
        LEFT JOIN personas pua ON ua.id_persona = pua.id
        LEFT JOIN usuarios uc ON ac.id_usuario_contabilizacion = uc.id
        LEFT JOIN personas puc ON uc.id_persona = puc.id
        WHERE ac.id_estado = 1
        ORDER BY ac.fecha_registro ASC");
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
            if (isset($row['nombre_usuario_registro'])) {
                $row['nombre_usuario_registro'] = trim(preg_replace('/\s+/', ' ', $row['nombre_usuario_registro']));
            }
            if (isset($row['nombre_usuario_aprobacion'])) {
                $row['nombre_usuario_aprobacion'] = trim(preg_replace('/\s+/', ' ', $row['nombre_usuario_aprobacion']));
            }
            if (isset($row['nombre_usuario_contabilizacion'])) {
                $row['nombre_usuario_contabilizacion'] = trim(preg_replace('/\s+/', ' ', $row['nombre_usuario_contabilizacion']));
            }
        }

        Flight::json($response);
    }

    public static function getAprobadas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
        ta.nombre as nombre_tipo_ausencia,
        ta.valor_hora,
        ca.nombre as nombre_categoria,
        ca.id as id_categoria,
        ea.nombre as nombre_estado,
        ea.color as color_estado,
        CONCAT(IFNULL(pc.primer_nombre, ''), ' ', IFNULL(pc.segundo_nombre, ''), ' ', 
               IFNULL(pc.primer_apellido, ''), ' ', IFNULL(pc.segundo_apellido, '')) as nombre_colaborador
        FROM ausencias_colaboradores ac
        INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
        INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
        INNER JOIN estados_ausencias ea ON ac.id_estado = ea.id
        INNER JOIN colaboradores c ON ac.id_colaborador = c.id
        INNER JOIN personas pc ON c.id_persona = pc.id
        WHERE ac.id_estado = 2 
        AND ac.id NOT IN (SELECT id_ausencia_colaborador FROM contabilizaciones_detalle)
        ORDER BY ac.fecha_registro ASC");
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getBalanceColaborador($id_colaborador)
    {
        $db = Flight::db();

        $sentencePermisos = $db->prepare("SELECT COALESCE(SUM(ac.minutos_totales), 0) as total_permisos
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            WHERE ac.id_colaborador = :id_colaborador 
            AND ca.id = 1
            AND ac.id_estado = 2
            AND ac.id NOT IN (SELECT id_ausencia_colaborador FROM contabilizaciones_detalle)");
        $sentencePermisos->bindParam(':id_colaborador', $id_colaborador);
        $sentencePermisos->execute();
        $permisos = $sentencePermisos->fetch();

        $sentenceHoras = $db->prepare("SELECT COALESCE(SUM(ac.minutos_totales), 0) as total_horas,
            COALESCE(SUM(ac.minutos_totales * ta.valor_hora / 60), 0) as valor_total_horas
            FROM ausencias_colaboradores ac
            INNER JOIN tipos_ausencias ta ON ac.id_tipo_ausencia = ta.id
            INNER JOIN categorias_ausencias ca ON ta.id_categoria = ca.id
            WHERE ac.id_colaborador = :id_colaborador 
            AND ca.id = 2
            AND ac.id_estado = 2
            AND ac.id NOT IN (SELECT id_ausencia_colaborador FROM contabilizaciones_detalle)");
        $sentenceHoras->bindParam(':id_colaborador', $id_colaborador);
        $sentenceHoras->execute();
        $horas = $sentenceHoras->fetch();

        $balance = array(
            'total_permisos' => $permisos['total_permisos'],
            'total_horas_adicionales' => $horas['total_horas'],
            'valor_horas_adicionales' => $horas['valor_total_horas'],
            'balance_minutos' => $horas['total_horas'] - $permisos['total_permisos']
        );

        Flight::json($balance);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_colaborador = Flight::request()->data['id_colaborador'];
            $id_tipo_ausencia = Flight::request()->data['id_tipo_ausencia'];
            $fecha_hora_inicio = Flight::request()->data['fecha_hora_inicio'];
            $fecha_hora_fin = Flight::request()->data['fecha_hora_fin'];
            $observaciones = isset(Flight::request()->data['observaciones']) ?
                Flight::request()->data['observaciones'] : null;
            $ruta_documento = isset(Flight::request()->data['ruta_documento']) ?
                Flight::request()->data['ruta_documento'] : null;
            $id_usuario_registro = Flight::request()->data['id_usuario_registro'];

            $inicio = new DateTime($fecha_hora_inicio);
            $fin = new DateTime($fecha_hora_fin);
            $diferencia = $inicio->diff($fin);
            $minutos_totales = ($diferencia->days * 24 * 60) + ($diferencia->h * 60) + $diferencia->i;

            $id_estado = 1;

            $sentence = $db->prepare("INSERT INTO ausencias_colaboradores 
                (id_colaborador, id_tipo_ausencia, id_estado, fecha_hora_inicio, fecha_hora_fin, 
                minutos_totales, observaciones, ruta_documento, id_usuario_registro) 
                VALUES (:id_colaborador, :id_tipo_ausencia, :id_estado, :fecha_hora_inicio, 
                :fecha_hora_fin, :minutos_totales, :observaciones, :ruta_documento, :id_usuario_registro)");

            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_tipo_ausencia', $id_tipo_ausencia);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':fecha_hora_inicio', $fecha_hora_inicio);
            $sentence->bindParam(':fecha_hora_fin', $fecha_hora_fin);
            $sentence->bindParam(':minutos_totales', $minutos_totales);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':ruta_documento', $ruta_documento);
            $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);
            $sentence->execute();

            $id = $db->lastInsertId();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en AusenciasColaboradores::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

}