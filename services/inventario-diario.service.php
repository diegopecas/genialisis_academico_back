<?php
class InventarioDiario
{
    /**
     * Calcula la clave que sostiene el índice único uk_inv_dia_elemento.
     * Para un elemento del catálogo es su propio id; para uno agregado con el
     * + es el MD5 del nombre normalizado, para que 'Inhalador' y
     * '  inhalador ' choquen contra el índice en vez de crear dos filas.
     */
    private static function calcularClaveElemento($id_elemento_inventario, $nombre_libre)
    {
        if (!empty($id_elemento_inventario)) {
            return $id_elemento_inventario;
        }
        return md5(mb_strtolower(trim($nombre_libre)));
    }

    /**
     * Devuelve la fecha del último día con registro de inventario de un
     * estudiante, anterior a la fecha dada. Es la base de la herencia: lo que
     * el niño llevó ese día es lo que se le propone hoy.
     */
    private static function getFechaUltimoRegistro($db, $id_estudiante, $fecha)
    {
        $sentence = $db->prepare("SELECT MAX(fecha) AS fecha
                                  FROM inventario_diario
                                  WHERE id_tenant = :id_tenant
                                  AND id_estudiante = :id_estudiante
                                  AND fecha < :fecha");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $fila = $sentence->fetch();
        return ($fila && $fila['fecha']) ? $fila['fecha'] : null;
    }

    /**
     * Siembra el inventario de un estudiante para una fecha, si todavía no
     * tiene filas. Nunca sobreescribe: si la docente ya marcó algo desde la
     * grilla, esto no lo toca.
     *
     * Copia del último día con registro. Si el estudiante nunca ha tenido
     * inventario, arranca con los elementos del catálogo que apliquen a su
     * grupo. Todo entra con trajo = 1, que es el precargado marcado.
     *
     * La usa la grilla al abrir el día y también el registro de ingreso de
     * asistencia. Devuelve la cantidad de filas creadas.
     */
    public static function sembrarDiaEstudiante($db, $id_estudiante, $fecha, $id_asistencia_estudiante, $id_usuario)
    {
        $sentence = $db->prepare("SELECT COUNT(*) AS filas FROM inventario_diario
                                  WHERE id_tenant = :id_tenant AND id_estudiante = :id_estudiante AND fecha = :fecha");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['filas'] > 0) {
            // Ya tiene el día armado. Solo se completa la referencia a la
            // asistencia si llegó ahora y antes no estaba.
            if (!empty($id_asistencia_estudiante)) {
                $sentence = $db->prepare("UPDATE inventario_diario SET id_asistencia_estudiante = :id_asistencia
                                          WHERE id_tenant = :id_tenant AND id_estudiante = :id_estudiante
                                          AND fecha = :fecha AND id_asistencia_estudiante IS NULL");
                $sentence->bindParam(':id_asistencia', $id_asistencia_estudiante);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_estudiante', $id_estudiante);
                $sentence->bindParam(':fecha', $fecha);
                $sentence->execute();
            }
            return 0;
        }

        $fechaAnterior = self::getFechaUltimoRegistro($db, $id_estudiante, $fecha);

        if ($fechaAnterior) {
            $sentence = $db->prepare("SELECT id_elemento_inventario, nombre_libre, clave_elemento
                                      FROM inventario_diario
                                      WHERE id_tenant = :id_tenant AND id_estudiante = :id_estudiante AND fecha = :fecha_anterior");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':fecha_anterior', $fechaAnterior);
            $sentence->execute();
            $origen = $sentence->fetchAll();
        } else {
            $sentence = $db->prepare("SELECT e.id AS id_elemento_inventario, NULL AS nombre_libre, e.id AS clave_elemento
                                      FROM elementos_inventario e
                                      INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = :id_estudiante AND exg.activo = 1
                                      WHERE e.id_tenant = :id_tenant
                                      AND e.activo = 1
                                      AND (
                                          NOT EXISTS (SELECT 1 FROM elementos_inventario_grupos g WHERE g.id_elemento_inventario = e.id)
                                          OR EXISTS (SELECT 1 FROM elementos_inventario_grupos g WHERE g.id_elemento_inventario = e.id AND g.id_grupo = exg.id_grupo)
                                      )
                                      GROUP BY e.id
                                      ORDER BY e.orden, e.nombre");
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $origen = $sentence->fetchAll();
        }

        if (empty($origen)) {
            return 0;
        }

        $sentence = $db->prepare("INSERT INTO inventario_diario
            (id, id_tenant, id_estudiante, fecha, id_asistencia_estudiante, id_elemento_inventario, nombre_libre, clave_elemento, trajo, id_usuario_entrada)
            VALUES (:id, :id_tenant, :id_estudiante, :fecha, :id_asistencia, :id_elemento, :nombre_libre, :clave_elemento, 1, :id_usuario)");

        $creadas = 0;
        foreach ($origen as $item) {
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_estudiante', $id_estudiante);
            $sentence->bindValue(':fecha', $fecha);
            $sentence->bindValue(':id_asistencia', !empty($id_asistencia_estudiante) ? $id_asistencia_estudiante : null);
            $sentence->bindValue(':id_elemento', !empty($item['id_elemento_inventario']) ? $item['id_elemento_inventario'] : null);
            $sentence->bindValue(':nombre_libre', !empty($item['nombre_libre']) ? $item['nombre_libre'] : null);
            $sentence->bindValue(':clave_elemento', $item['clave_elemento']);
            $sentence->bindValue(':id_usuario', !empty($id_usuario) ? $id_usuario : null);
            $sentence->execute();
            $creadas++;
        }

        return $creadas;
    }

    /**
     * Marca el regreso de todos los elementos que el estudiante trajo ese día.
     * La usa el registro de salida de asistencia cuando la usuaria no detalla
     * elemento por elemento.
     *
     * $noRegresaron es un arreglo de ids de inventario_diario que NO se lleva.
     * El resto de lo que trajo queda con regreso = 1.
     */
    public static function registrarSalidaEstudiante($db, $id_estudiante, $fecha, $noRegresaron, $id_usuario)
    {
        $sentence = $db->prepare("UPDATE inventario_diario
                                  SET regreso = 1, id_usuario_salida = :id_usuario
                                  WHERE id_tenant = :id_tenant AND id_estudiante = :id_estudiante
                                  AND fecha = :fecha AND trajo = 1");
        $sentence->bindValue(':id_usuario', !empty($id_usuario) ? $id_usuario : null);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $marcados = $sentence->rowCount();

        if (is_array($noRegresaron) && count($noRegresaron) > 0) {
            $sentence = $db->prepare("UPDATE inventario_diario
                                      SET regreso = 0, id_usuario_salida = :id_usuario
                                      WHERE id = :id AND id_tenant = :id_tenant");
            foreach ($noRegresaron as $idFila) {
                if (empty($idFila)) {
                    continue;
                }
                $sentence->bindValue(':id_usuario', !empty($id_usuario) ? $id_usuario : null);
                $sentence->bindValue(':id', $idFila);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
            }
        }

        return $marcados;
    }

    /**
     * Filas de inventario de un estudiante en una fecha. La usan los paneles
     * de ingreso y salida de asistencia, que trabajan de a un niño.
     */
    public static function getPorEstudiante($id_estudiante, $fecha)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $sentence = $db->prepare("SELECT i.id, i.id_elemento_inventario, i.nombre_libre, i.trajo, i.regreso, i.observacion,
                                         COALESCE(e.nombre, i.nombre_libre) AS nombre, e.icono, COALESCE(e.orden, 999) AS orden
                                  FROM inventario_diario i
                                  LEFT JOIN elementos_inventario e ON e.id = i.id_elemento_inventario
                                  WHERE i.id_tenant = :id_tenant AND i.id_estudiante = :id_estudiante AND i.fecha = :fecha
                                  ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Arma la grilla del día de un grupo: estudiantes, columnas y filas ya
     * marcadas. Siembra el día de los estudiantes que aún no lo tengan, para
     * que la docente entre y encuentre todo precargado.
     *
     * Recibe id_grupo y fecha. Si solo_presentes viene en 1, deja únicamente
     * los estudiantes con ingreso registrado ese día.
     */
    public static function getDiaGrupo()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_grupo = Flight::request()->data['id_grupo'];
        $fecha = Flight::request()->data['fecha'];
        $solo_presentes = isset(Flight::request()->data['solo_presentes']) ? Flight::request()->data['solo_presentes'] : 0;
        $id_usuario = isset(Flight::request()->data['id_usuario']) ? Flight::request()->data['id_usuario'] : null;

        if (empty($id_grupo) || empty($fecha)) {
            Flight::json(array('error' => 'El grupo y la fecha son obligatorios'), 400);
            return;
        }

        $sql = "SELECT e.id AS id_estudiante, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       ae.id AS id_asistencia_estudiante, ae.fecha_ingreso, ae.fecha_salida
                FROM estudiantes e
                INNER JOIN personas p ON e.id_persona = p.id
                INNER JOIN estudiantes_x_grupos exg ON e.id = exg.id_estudiante
                LEFT JOIN asistencia_estudiantes ae ON ae.id_estudiante = e.id
                     AND DATE(ae.fecha_ingreso) = :fecha_asistencia
                     AND ae.id_tenant = :id_tenant_asis
                WHERE exg.id_grupo = :id_grupo
                AND exg.activo = 1
                AND e.activo = 1
                AND e.id_tenant = :id_tenant";

        if ($solo_presentes == 1) {
            $sql .= " AND ae.id IS NOT NULL";
        }

        $sql .= " ORDER BY p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindParam(':fecha_asistencia', $fecha);
        $sentence->bindValue(':id_tenant_asis', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $estudiantes = $sentence->fetchAll();

        foreach ($estudiantes as $estudiante) {
            self::sembrarDiaEstudiante($db, $estudiante['id_estudiante'], $fecha, $estudiante['id_asistencia_estudiante'], $id_usuario);
        }

        // Columnas de la grilla: los elementos del catálogo que aplican al
        // grupo. Los elementos sueltos de cada niño no son columna, van como
        // chip en su fila.
        $sentence = $db->prepare("SELECT e.id, e.nombre, e.icono, e.orden
                                  FROM elementos_inventario e
                                  WHERE e.id_tenant = :id_tenant
                                  AND e.activo = 1
                                  AND (
                                      NOT EXISTS (SELECT 1 FROM elementos_inventario_grupos g WHERE g.id_elemento_inventario = e.id)
                                      OR EXISTS (SELECT 1 FROM elementos_inventario_grupos g WHERE g.id_elemento_inventario = e.id AND g.id_grupo = :id_grupo)
                                  )
                                  ORDER BY e.orden, e.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $columnas = $sentence->fetchAll();

        $sentence = $db->prepare("SELECT i.id, i.id_estudiante, i.id_elemento_inventario, i.nombre_libre, i.trajo, i.regreso, i.observacion,
                                         COALESCE(el.nombre, i.nombre_libre) AS nombre, COALESCE(el.orden, 999) AS orden
                                  FROM inventario_diario i
                                  LEFT JOIN elementos_inventario el ON el.id = i.id_elemento_inventario
                                  INNER JOIN estudiantes_x_grupos exg ON exg.id_estudiante = i.id_estudiante AND exg.activo = 1
                                  WHERE i.id_tenant = :id_tenant
                                  AND i.fecha = :fecha
                                  AND exg.id_grupo = :id_grupo
                                  ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $filas = $sentence->fetchAll();

        Flight::json(array(
            'fecha' => $fecha,
            'estudiantes' => $estudiantes,
            'columnas' => $columnas,
            'filas' => $filas
        ));
    }

    /**
     * Guarda en lote los checks de la grilla. Recibe un arreglo de cambios,
     * cada uno con el id de la fila de inventario y el valor. El modo dice
     * qué columna se está editando: 'entrada' escribe trajo, 'salida' escribe
     * regreso.
     *
     * Va en lote y no fila por fila porque la docente marca el grupo entero
     * de un tirón y no puede quedarse esperando una petición por check.
     */
    public static function guardarLote()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $modo = isset(Flight::request()->data['modo']) ? Flight::request()->data['modo'] : 'entrada';
        $cambios = isset(Flight::request()->data['cambios']) ? Flight::request()->data['cambios'] : array();
        $id_usuario = isset(Flight::request()->data['id_usuario']) ? Flight::request()->data['id_usuario'] : null;

        if (!is_array($cambios) || count($cambios) === 0) {
            Flight::json(array('actualizados' => 0));
            return;
        }

        if ($modo !== 'entrada' && $modo !== 'salida') {
            Flight::json(array('error' => 'El modo debe ser entrada o salida'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            if ($modo === 'entrada') {
                $sentence = $db->prepare("UPDATE inventario_diario
                                          SET trajo = :valor, id_usuario_entrada = :id_usuario
                                          WHERE id = :id AND id_tenant = :id_tenant");
            } else {
                $sentence = $db->prepare("UPDATE inventario_diario
                                          SET regreso = :valor, id_usuario_salida = :id_usuario
                                          WHERE id = :id AND id_tenant = :id_tenant");
            }

            $actualizados = 0;
            foreach ($cambios as $cambio) {
                if (empty($cambio['id'])) {
                    continue;
                }
                $valor = isset($cambio['valor']) && $cambio['valor'] ? 1 : 0;
                $sentence->bindValue(':valor', $valor, PDO::PARAM_INT);
                $sentence->bindValue(':id_usuario', !empty($id_usuario) ? $id_usuario : null);
                $sentence->bindValue(':id', $cambio['id']);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
                $actualizados += $sentence->rowCount();
            }

            $db->commit();
            Flight::json(array('actualizados' => $actualizados));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en InventarioDiario::guardarLote: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron guardar los cambios'), 500);
        }
    }

    /**
     * Agrega un elemento suelto a un solo estudiante en una fecha: el botón +
     * de la grilla. Puede venir con id_elemento_inventario, si escogió uno del
     * catálogo que no era columna de su grupo, o con nombre_libre.
     */
    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_estudiante = Flight::request()->data['id_estudiante'];
        $fecha = Flight::request()->data['fecha'];
        $id_elemento_inventario = isset(Flight::request()->data['id_elemento_inventario']) ? Flight::request()->data['id_elemento_inventario'] : null;
        $nombre_libre = isset(Flight::request()->data['nombre_libre']) ? Flight::request()->data['nombre_libre'] : null;
        $trajo = isset(Flight::request()->data['trajo']) ? (Flight::request()->data['trajo'] ? 1 : 0) : 1;
        $observacion = isset(Flight::request()->data['observacion']) ? Flight::request()->data['observacion'] : null;
        $id_usuario = isset(Flight::request()->data['id_usuario']) ? Flight::request()->data['id_usuario'] : null;

        if (empty($id_estudiante) || empty($fecha)) {
            Flight::json(array('error' => 'El estudiante y la fecha son obligatorios'), 400);
            return;
        }

        if (empty($id_elemento_inventario) && (empty($nombre_libre) || trim($nombre_libre) === '')) {
            Flight::json(array('error' => 'Debe indicar un elemento del catálogo o un nombre'), 400);
            return;
        }

        if (!empty($nombre_libre)) {
            $nombre_libre = trim($nombre_libre);
        }

        $clave_elemento = self::calcularClaveElemento($id_elemento_inventario, $nombre_libre);

        $sentence = $db->prepare("SELECT id FROM inventario_diario
                                  WHERE id_tenant = :id_tenant AND id_estudiante = :id_estudiante
                                  AND fecha = :fecha AND clave_elemento = :clave_elemento");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':clave_elemento', $clave_elemento);
        $sentence->execute();

        if ($sentence->fetch()) {
            Flight::json(array('error' => 'Ese elemento ya está registrado para el estudiante en esa fecha'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO inventario_diario
            (id, id_tenant, id_estudiante, fecha, id_elemento_inventario, nombre_libre, clave_elemento, trajo, observacion, id_usuario_entrada)
            VALUES (:id, :id_tenant, :id_estudiante, :fecha, :id_elemento, :nombre_libre, :clave_elemento, :trajo, :observacion, :id_usuario)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':id_elemento', !empty($id_elemento_inventario) ? $id_elemento_inventario : null);
        $sentence->bindValue(':nombre_libre', !empty($nombre_libre) ? $nombre_libre : null);
        $sentence->bindParam(':clave_elemento', $clave_elemento);
        $sentence->bindValue(':trajo', $trajo, PDO::PARAM_INT);
        $sentence->bindValue(':observacion', !empty($observacion) ? $observacion : null);
        $sentence->bindValue(':id_usuario', !empty($id_usuario) ? $id_usuario : null);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT i.id, i.id_estudiante, i.fecha, i.id_asistencia_estudiante, i.id_elemento_inventario,
                                         i.nombre_libre, i.trajo, i.regreso, i.observacion,
                                         COALESCE(e.nombre, i.nombre_libre) AS nombre
                                  FROM inventario_diario i
                                  LEFT JOIN elementos_inventario e ON e.id = i.id_elemento_inventario
                                  WHERE i.id = :id AND i.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $trajo = isset(Flight::request()->data['trajo']) ? (Flight::request()->data['trajo'] ? 1 : 0) : 1;
        $regreso = isset(Flight::request()->data['regreso']) && Flight::request()->data['regreso'] !== null
            ? (Flight::request()->data['regreso'] ? 1 : 0)
            : null;
        $observacion = isset(Flight::request()->data['observacion']) ? Flight::request()->data['observacion'] : null;

        $sentence = $db->prepare("UPDATE inventario_diario SET trajo = :trajo, regreso = :regreso, observacion = :observacion
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':trajo', $trajo, PDO::PARAM_INT);
        $sentence->bindValue(':regreso', $regreso, $regreso === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':observacion', !empty($observacion) ? $observacion : null);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM inventario_diario WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    /**
     * Reporte de lo que cada niño ha traído y llevado. Sirve para los dos
     * cortes: por estudiante en un rango de fechas, que es el caso del
     * reclamo de un acudiente, y por grupo en un día.
     *
     * Todos los filtros son opcionales y se van sumando.
     */
    public static function getReporte()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_estudiante = isset(Flight::request()->data['id_estudiante']) ? Flight::request()->data['id_estudiante'] : null;
        $id_grupo = isset(Flight::request()->data['id_grupo']) ? Flight::request()->data['id_grupo'] : null;
        $fecha_inicial = isset(Flight::request()->data['fecha_inicial']) ? Flight::request()->data['fecha_inicial'] : null;
        $fecha_final = isset(Flight::request()->data['fecha_final']) ? Flight::request()->data['fecha_final'] : null;
        $solo_faltantes = isset(Flight::request()->data['solo_faltantes']) ? Flight::request()->data['solo_faltantes'] : 0;

        $sql = "SELECT i.id, i.fecha, i.id_estudiante,
                       TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS estudiante,
                       g.nombre AS grupo,
                       COALESCE(el.nombre, i.nombre_libre) AS elemento,
                       i.trajo, i.regreso, i.observacion
                FROM inventario_diario i
                INNER JOIN estudiantes e ON e.id = i.id_estudiante
                INNER JOIN personas p ON p.id = e.id_persona
                LEFT JOIN elementos_inventario el ON el.id = i.id_elemento_inventario
                LEFT JOIN estudiantes_x_grupos exg ON exg.id_estudiante = e.id AND exg.activo = 1
                LEFT JOIN grupos g ON g.id = exg.id_grupo
                WHERE i.id_tenant = :id_tenant";

        $parametros = array();

        if (!empty($id_estudiante)) {
            $sql .= " AND i.id_estudiante = :id_estudiante";
            $parametros[':id_estudiante'] = $id_estudiante;
        }

        if (!empty($id_grupo)) {
            $sql .= " AND exg.id_grupo = :id_grupo";
            $parametros[':id_grupo'] = $id_grupo;
        }

        if (!empty($fecha_inicial)) {
            $sql .= " AND i.fecha >= :fecha_inicial";
            $parametros[':fecha_inicial'] = $fecha_inicial;
        }

        if (!empty($fecha_final)) {
            $sql .= " AND i.fecha <= :fecha_final";
            $parametros[':fecha_final'] = $fecha_final;
        }

        // Lo que no se llevó: trajo pero no regresó. Es el filtro que se usa
        // cuando un acudiente reclama algo.
        if ($solo_faltantes == 1) {
            $sql .= " AND i.trajo = 1 AND (i.regreso = 0 OR i.regreso IS NULL)";
        }

        $sql .= " ORDER BY i.fecha DESC, estudiante, elemento";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($parametros as $clave => $valor) {
            $sentence->bindValue($clave, $valor);
        }
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
