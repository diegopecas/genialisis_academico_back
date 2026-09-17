<?php
/**
 * Intentos de autoregistro: cada vez que alguien abre un enlace se crea un
 * intento, y se va actualizando con el paso mas alto al que llega. Sirve para
 * ver quien se quedo a mitad de camino.
 */
class EnlacesAutoregistroIntentos
{
    // Pasos del asistente publico. El numero es el orden; el paso guardado
    // siempre es el mas alto alcanzado.
    const PASO_ABRIO = 1;
    const PASO_SELECCIONO = 2;
    const PASO_MODO = 3;
    const PASO_DOCUMENTO = 4;
    const PASO_DATOS = 5;
    const PASO_COMPLETADO = 6;

    const NOMBRES_PASO = array(
        1 => 'Abrió el enlace',
        2 => 'Seleccionó estudiantes',
        3 => 'Escogió el modo',
        4 => 'Validó el documento',
        5 => 'Diligenció los datos',
        6 => 'Registro completado',
    );

    private static function camposSelect()
    {
        return "i.id, i.id_enlace, i.paso, i.modo, i.resultado_ia, i.lecturas_ia, i.error_ia,
                i.numero_identificacion, i.nombre_completo,
                i.id_persona, i.persona_nueva, i.id_usuario, i.usuario_nuevo, i.id_documento,
                i.observaciones, i.ip_address, i.fecha_inicio, i.fecha_actualizacion, i.fecha_completado";
    }

    private static function agregarNombrePaso($filas)
    {
        foreach ($filas as $indice => $fila) {
            $paso = (int) $fila['paso'];
            $filas[$indice]['paso_nombre'] = isset(self::NOMBRES_PASO[$paso]) ? self::NOMBRES_PASO[$paso] : '';
        }
        return $filas;
    }

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT " . self::camposSelect() . "
                                  FROM enlaces_autoregistro_intentos i
                                  WHERE i.id_tenant = :id_tenant
                                  ORDER BY i.fecha_inicio DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(self::agregarNombrePaso($sentence->fetchAll()));
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT " . self::camposSelect() . "
                                  FROM enlaces_autoregistro_intentos i
                                  WHERE i.id = :id AND i.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(self::agregarNombrePaso($sentence->fetchAll()));
    }

    /**
     * Seguimiento de un enlace: todos sus intentos con los estudiantes
     * escogidos y el resultado de cada uno.
     */
    public static function getByEnlace($idEnlace)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT " . self::camposSelect() . ",
                                         (SELECT GROUP_CONCAT(
                                                    CONCAT(TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.primer_apellido)),
                                                           ' (', COALESCE(ta.nombre, ''), ')')
                                                    ORDER BY pe.primer_nombre SEPARATOR ', ')
                                          FROM enlaces_autoregistro_vinculos v
                                          INNER JOIN estudiantes e ON e.id = v.id_estudiante
                                          INNER JOIN personas pe ON pe.id = e.id_persona
                                          LEFT JOIN tipos_acudiente ta ON ta.id = v.id_tipo_acudiente
                                          WHERE v.id_intento = i.id AND v.id_tenant = i.id_tenant) AS estudiantes
                                  FROM enlaces_autoregistro_intentos i
                                  WHERE i.id_enlace = :id_enlace AND i.id_tenant = :id_tenant
                                  ORDER BY i.fecha_inicio DESC");
        $sentence->bindParam(':id_enlace', $idEnlace);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(self::agregarNombrePaso($sentence->fetchAll()));
    }

    // =================================================================
    // Uso interno (pagina publica). No responden JSON.
    // =================================================================

    /**
     * Crea un intento nuevo en el paso 1.
     *
     * @return string id del intento
     */
    public static function crear(PDO $db, $idEnlace)
    {
        $id = Uuid::generar();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? mb_substr($_SERVER['REMOTE_ADDR'], 0, 45) : null;

        $sentence = $db->prepare("INSERT INTO enlaces_autoregistro_intentos
                                  (id, id_tenant, id_enlace, paso, lecturas_ia, user_agent, ip_address, fecha_inicio, fecha_actualizacion)
                                  VALUES (:id, :id_tenant, :id_enlace, :paso, 0, :user_agent, :ip, NOW(), NOW())");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_enlace', $idEnlace);
        $sentence->bindValue(':paso', self::PASO_ABRIO, PDO::PARAM_INT);
        $sentence->bindValue(':user_agent', $userAgent);
        $sentence->bindValue(':ip', $ip);
        $sentence->execute();

        return $id;
    }

    /**
     * Intento de un enlace, o null si no existe o es de otro enlace.
     */
    public static function obtener(PDO $db, $idIntento, $idEnlace)
    {
        if (empty($idIntento)) {
            return null;
        }

        $sentence = $db->prepare("SELECT " . self::camposSelect() . "
                                  FROM enlaces_autoregistro_intentos i
                                  WHERE i.id = :id AND i.id_enlace = :id_enlace AND i.id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindValue(':id', $idIntento);
        $sentence->bindValue(':id_enlace', $idEnlace);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    /**
     * Actualiza el intento. El paso nunca baja: se guarda el mayor entre el
     * actual y el recibido. Los demas campos solo se tocan si vienen en
     * $campos (lista blanca).
     *
     * @param PDO      $db
     * @param string   $idIntento
     * @param int|null $paso
     * @param array    $campos
     * @return void
     */
    public static function actualizar(PDO $db, $idIntento, $paso = null, $campos = array())
    {
        $permitidos = array(
            'modo', 'resultado_ia', 'error_ia', 'numero_identificacion', 'nombre_completo',
            'id_persona', 'persona_nueva', 'id_usuario', 'usuario_nuevo', 'id_documento',
            'observaciones', 'fecha_completado'
        );

        $sets = array('fecha_actualizacion = NOW()');
        $valores = array();

        if ($paso !== null) {
            $sets[] = 'paso = GREATEST(paso, :paso)';
            $valores[':paso'] = (int) $paso;
        }

        foreach ($campos as $campo => $valor) {
            if (!in_array($campo, $permitidos, true)) {
                continue;
            }
            $sets[] = $campo . ' = :' . $campo;
            $valores[':' . $campo] = $valor;
        }

        $sentence = $db->prepare("UPDATE enlaces_autoregistro_intentos SET " . implode(', ', $sets) . "
                                  WHERE id = :id AND id_tenant = :id_tenant");
        foreach ($valores as $clave => $valor) {
            $sentence->bindValue($clave, $valor);
        }
        $sentence->bindValue(':id', $idIntento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Suma una lectura de IA al intento.
     */
    public static function sumarLecturaIa(PDO $db, $idIntento)
    {
        $sentence = $db->prepare("UPDATE enlaces_autoregistro_intentos
                                  SET lecturas_ia = lecturas_ia + 1, fecha_actualizacion = NOW()
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id', $idIntento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }
}
