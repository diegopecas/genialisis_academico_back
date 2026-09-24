<?php
/**
 * IaConsumos — registro de consumo de IA (tabla ia_consumos).
 *
 * Es la única fuente de consumo de IA: reemplaza a los contadores
 * tokens_consumidos_hoy e ia_vision_uso de ia_configuracion.
 *
 * Se guarda UN registro por petición del usuario, no uno por proveedor:
 *   - En columnas normales va el intento que respondió (proveedor, modelo,
 *     tokens), para poder sumar y agrupar directo.
 *   - En la columna JSON `intentos` va el detalle de todos los proveedores que
 *     se probaron en la cadena, incluidos los que fallaron (p.ej. la llave
 *     gratis que respondió 503 antes de pasar a la paga).
 *
 * Uso desde un servicio de IA:
 *
 *   $intentos = [];
 *   $inicio = microtime(true);
 *   $r = self::llamarGemini(...);
 *   $intentos[] = IaConsumos::intento('gemini', 'gemini-2.5-flash', $r, $inicio);
 *   ...
 *   IaConsumos::registrar('maquina_actividades', 'generar', $intentos);
 *
 * El registro es best-effort: si falla, queda en el log y NUNCA rompe la
 * petición que lo llamó.
 */
class IaConsumos
{
    const MAX_ERROR = 500;
    const MAX_ERROR_INTENTO = 300;
    const MAX_TEXTO_CORTO = 100;

    // =====================================================
    // REPORTE
    // =====================================================

    /**
     * Consumos de IA del tenant en un rango de fechas.
     * Query: desde=YYYY-MM-DD, hasta=YYYY-MM-DD (por defecto, el mes en curso).
     */
    public static function getReporte()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'reportes.consumos_ia');

        try {
            $db = Flight::db();

            $desde = Flight::request()->query['desde'] ?? date('Y-m-01');
            $hasta = Flight::request()->query['hasta'] ?? date('Y-m-d');

            if (!self::esFechaValida($desde) || !self::esFechaValida($hasta)) {
                Flight::json(['error' => 'Las fechas deben tener el formato AAAA-MM-DD'], 400);
                return;
            }

            if ($desde > $hasta) {
                Flight::json(['error' => 'La fecha inicial no puede ser mayor que la fecha final'], 400);
                return;
            }

            $sentence = $db->prepare("
                SELECT
                    ic.id,
                    ic.id_persona,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre,
                                        p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                    ic.servicio,
                    ic.accion,
                    ic.exito,
                    ic.proveedor,
                    ic.modelo,
                    ic.tokens_entrada,
                    ic.tokens_salida,
                    ic.tokens_total,
                    ic.tiempo_ms,
                    ic.cantidad_intentos,
                    ic.intentos,
                    ic.error,
                    ic.fecha
                FROM ia_consumos ic
                LEFT JOIN personas p ON p.id = ic.id_persona AND p.id_tenant = ic.id_tenant
                WHERE ic.id_tenant = :id_tenant
                  AND ic.fecha >= :desde
                  AND ic.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY)
                ORDER BY ic.fecha DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':desde', $desde);
            $sentence->bindValue(':hasta', $hasta);
            $sentence->execute();
            $filas = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // El detalle de intentos se devuelve como arreglo, no como texto JSON.
            foreach ($filas as &$fila) {
                $intentos = json_decode($fila['intentos'] ?? '[]', true);
                $fila['intentos'] = is_array($intentos) ? $intentos : [];
            }
            unset($fila);

            Flight::json($filas);
        } catch (Exception $e) {
            error_log("Error en IaConsumos::getReporte: " . $e->getMessage());
            Flight::json(['error' => 'No se pudo consultar el consumo de IA'], 500);
        }
    }

    // =====================================================
    // REGISTRO
    // =====================================================

    /**
     * Arma la entrada de un intento a partir de lo que devolvió la llamada al
     * proveedor. Entiende las llaves que usan los servicios de IA:
     * success, error, http (opcional) y tokens (opcional).
     *
     * @param string     $proveedor Proveedor de la cadena (gemini, gemini_pago, groq...)
     * @param string     $modelo    Modelo usado
     * @param array|null $resultado Respuesta de la llamada al proveedor
     * @param float      $inicio    microtime(true) tomado justo antes de la llamada
     * @return array
     */
    public static function intento($proveedor, $modelo, $resultado, $inicio)
    {
        $resultado = is_array($resultado) ? $resultado : [];
        $exito = !empty($resultado['success']);
        $error = $exito ? null : (string) ($resultado['error'] ?? 'desconocido');

        // Si la llamada no reportó el código HTTP, se intenta sacar del texto del error.
        $http = isset($resultado['http']) ? (int) $resultado['http'] : null;
        if ($http === null && $error !== null && preg_match('/HTTP\s+(\d{3})/', $error, $m)) {
            $http = (int) $m[1];
        }

        return [
            'proveedor' => (string) $proveedor,
            'modelo' => (string) $modelo,
            'exito' => $exito,
            'http' => $http,
            'error' => $error !== null ? mb_substr($error, 0, self::MAX_ERROR_INTENTO) : null,
            'ms' => (int) round((microtime(true) - $inicio) * 1000),
            'tokens' => self::normalizarTokens($resultado['tokens'] ?? null)
        ];
    }

    /**
     * Graba el registro de una petición de IA con todos sus intentos.
     * Nunca lanza excepción.
     *
     * @param string      $servicio  Servicio de IA (maquina_actividades, chat, pagos...)
     * @param string|null $accion    Operación dentro del servicio
     * @param array       $intentos  Lista armada con IaConsumos::intento()
     * @param string|null $idPersona Quién hizo la llamada; si es null se toma del token
     */
    public static function registrar($servicio, $accion, array $intentos, $idPersona = null)
    {
        try {
            $exitoso = null;
            $tiempoMs = 0;
            foreach ($intentos as $intento) {
                $tiempoMs += (int) ($intento['ms'] ?? 0);
                if (!empty($intento['exito'])) {
                    $exitoso = $intento;
                }
            }

            $error = null;
            if ($exitoso === null) {
                $ultimo = end($intentos);
                $error = $ultimo ? ($ultimo['error'] ?? 'desconocido') : 'No hay proveedores de IA configurados';
                $error = mb_substr((string) $error, 0, self::MAX_ERROR);
            }

            $tokens = $exitoso ? ($exitoso['tokens'] ?? null) : null;

            $datos = [
                ':id_tenant' => TenantContext::id(),
                ':id_persona' => $idPersona !== null ? $idPersona : self::idPersonaDelToken(),
                ':servicio' => mb_substr((string) $servicio, 0, self::MAX_TEXTO_CORTO),
                ':accion' => $accion !== null ? mb_substr((string) $accion, 0, self::MAX_TEXTO_CORTO) : null,
                ':exito' => $exitoso !== null ? 1 : 0,
                ':proveedor' => $exitoso ? $exitoso['proveedor'] : null,
                ':modelo' => $exitoso ? $exitoso['modelo'] : null,
                ':tokens_entrada' => $tokens ? $tokens['entrada'] : null,
                ':tokens_salida' => $tokens ? $tokens['salida'] : null,
                ':tokens_total' => $tokens ? $tokens['total'] : null,
                ':tiempo_ms' => $tiempoMs,
                ':cantidad_intentos' => min(count($intentos), 255),
                ':intentos' => json_encode(array_values($intentos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':error' => $error
            ];

            self::insertar($datos);
        } catch (Throwable $e) {
            error_log("IaConsumos::registrar falló (no afecta la petición): " . $e->getMessage());
        }
    }

    /**
     * Tokens de una respuesta de Gemini (usageMetadata). null si no vienen.
     */
    public static function tokensGemini($data)
    {
        if (!is_array($data) || !isset($data['usageMetadata'])) {
            return null;
        }
        $uso = $data['usageMetadata'];
        $entrada = (int) ($uso['promptTokenCount'] ?? 0);
        $salida = (int) ($uso['candidatesTokenCount'] ?? 0);
        // totalTokenCount incluye los tokens de razonamiento de los modelos que piensan
        $total = isset($uso['totalTokenCount']) ? (int) $uso['totalTokenCount'] : $entrada + $salida;

        return ['entrada' => $entrada, 'salida' => $salida, 'total' => $total];
    }

    /**
     * Tokens de una respuesta OpenAI-compatible (usage). null si no vienen.
     */
    public static function tokensOpenAI($data)
    {
        if (!is_array($data) || !isset($data['usage'])) {
            return null;
        }
        $uso = $data['usage'];
        $entrada = (int) ($uso['prompt_tokens'] ?? 0);
        $salida = (int) ($uso['completion_tokens'] ?? 0);
        $total = isset($uso['total_tokens']) ? (int) $uso['total_tokens'] : $entrada + $salida;

        return ['entrada' => $entrada, 'salida' => $salida, 'total' => $total];
    }

    // =====================================================
    // PRIVADOS
    // =====================================================

    /**
     * Acepta tokens con llaves en español (entrada/salida/total) o en inglés
     * (input/output/total), que es como los devuelve IaVision.
     */
    private static function normalizarTokens($tokens)
    {
        if (!is_array($tokens)) {
            return null;
        }
        $entrada = (int) ($tokens['entrada'] ?? $tokens['input'] ?? 0);
        $salida = (int) ($tokens['salida'] ?? $tokens['output'] ?? 0);
        $total = (int) ($tokens['total'] ?? ($entrada + $salida));

        if ($entrada === 0 && $salida === 0 && $total === 0) {
            return null;
        }
        return ['entrada' => $entrada, 'salida' => $salida, 'total' => $total];
    }

    /**
     * id_persona del usuario del token, si hay uno válido. Las rutas públicas
     * (p.ej. el autoregistro de acudientes) no traen token y quedan en NULL.
     */
    private static function idPersonaDelToken()
    {
        try {
            $userData = JWTService::autenticacionOpcional();
            return ($userData && !empty($userData->id_persona)) ? $userData->id_persona : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Inserta el registro. La llamada a la IA puede tardar hasta 2 minutos y en
     * ese rato MySQL puede cerrar la conexión por wait_timeout ("MySQL server has
     * gone away"). Si pasa, se abre una conexión nueva solo para este insert.
     */
    private static function insertar(array $datos)
    {
        $sql = "INSERT INTO ia_consumos
                    (id, id_tenant, id_persona, servicio, accion, exito, proveedor, modelo,
                     tokens_entrada, tokens_salida, tokens_total, tiempo_ms,
                     cantidad_intentos, intentos, error)
                VALUES
                    (UUID(), :id_tenant, :id_persona, :servicio, :accion, :exito, :proveedor, :modelo,
                     :tokens_entrada, :tokens_salida, :tokens_total, :tiempo_ms,
                     :cantidad_intentos, :intentos, :error)";

        try {
            $stmt = Flight::db()->prepare($sql);
            $stmt->execute($datos);
        } catch (PDOException $e) {
            if (!self::esConexionCaida($e)) {
                throw $e;
            }
            $stmt = self::conexionNueva()->prepare($sql);
            $stmt->execute($datos);
        }
    }

    private static function esConexionCaida(PDOException $e)
    {
        $mensaje = $e->getMessage();
        $codigo = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;

        return in_array($codigo, [2006, 2013], true)
            || stripos($mensaje, 'gone away') !== false
            || stripos($mensaje, 'Lost connection') !== false;
    }

    private static function conexionNueva()
    {
        return new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }

    private static function esFechaValida($fecha)
    {
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));
        return checkdate($mes, $dia, $anio);
    }
}
