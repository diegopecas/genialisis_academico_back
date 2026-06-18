<?php

class GoogleCalendarService
{
    private static $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private static $tokenUrl = 'https://oauth2.googleapis.com/token';
    private static $calendarApiUrl = 'https://www.googleapis.com/calendar/v3';
    private static $tasksApiUrl = 'https://tasks.googleapis.com/tasks/v1';
    private static $scopes = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/tasks';

    /**
     * Genera la URL de autorización OAuth de Google.
     * Incluye ambos scopes: Calendar y Tasks.
     */
    public static function generarUrlAutorizacion()
    {
        $clientId = GoogleConfiguracion::getValorPorClave('client_id');
        $redirectUri = GoogleConfiguracion::getValorPorClave('redirect_uri');

        if (empty($clientId) || empty($redirectUri)) {
            Flight::json(['error' => true, 'message' => 'Faltan client_id o redirect_uri en google_configuracion'], 400);
            return;
        }

        $tenant = isset($_SERVER['HTTP_X_TENANT']) ? $_SERVER['HTTP_X_TENANT'] : '';
        if (empty($tenant)) {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-tenant') {
                    $tenant = $value;
                    break;
                }
            }
        }

        if (empty($tenant)) {
            Flight::json(['error' => true, 'message' => 'No se pudo determinar el tenant'], 400);
            return;
        }

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::$scopes,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $tenant
        ];

        $url = self::$authUrl . '?' . http_build_query($params);

        Flight::json(['url' => $url]);
    }

    /**
     * Callback de Google OAuth.
     * Ruta pública en index.php (sin X-Tenant).
     */
    public static function callback()
    {
        $code = isset($_GET['code']) ? $_GET['code'] : null;
        $tenant = isset($_GET['state']) ? $_GET['state'] : null;
        $error = isset($_GET['error']) ? $_GET['error'] : null;

        if ($error) {
            self::redirigirConMensaje($tenant, 'error', 'Autorización cancelada: ' . $error);
            return;
        }

        if (empty($code) || empty($tenant)) {
            self::redirigirConMensaje($tenant, 'error', 'Parámetros inválidos en callback');
            return;
        }

        $tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
        $configFile = __DIR__ . "/../config/tenants/{$tenant}.env.php";

        if (!file_exists($configFile)) {
            self::redirigirConMensaje($tenant, 'error', 'Tenant no encontrado');
            return;
        }

        require_once $configFile;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            $db = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            error_log("Google Calendar callback - Error BD: " . $e->getMessage());
            self::redirigirConMensaje($tenant, 'error', 'Error de conexión a base de datos');
            return;
        }

        $clientId = self::getConfigValor($db, 'client_id');
        $clientSecret = self::getConfigValor($db, 'client_secret');
        $redirectUri = self::getConfigValor($db, 'redirect_uri');

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            self::redirigirConMensaje($tenant, 'error', 'Configuración OAuth incompleta en BD');
            return;
        }

        $tokenData = self::intercambiarCodigoPorTokens($code, $clientId, $clientSecret, $redirectUri);

        if (isset($tokenData['error'])) {
            error_log("Google Calendar callback - Error tokens: " . json_encode($tokenData));
            self::redirigirConMensaje($tenant, 'error', 'Error al obtener tokens: ' . ($tokenData['error_description'] ?? $tokenData['error']));
            return;
        }

        if (!empty($tokenData['access_token'])) {
            self::setConfigValor($db, 'access_token', $tokenData['access_token']);
        }
        if (!empty($tokenData['refresh_token'])) {
            self::setConfigValor($db, 'refresh_token', $tokenData['refresh_token']);
        }
        if (!empty($tokenData['expires_in'])) {
            $expiraEn = time() + (int) $tokenData['expires_in'];
            self::setConfigValor($db, 'token_expira_en', (string) $expiraEn);
        }

        self::redirigirConMensaje($tenant, 'ok', 'Google Calendar conectado exitosamente');
    }

    /**
     * Verifica si la cuenta está conectada.
     */
    public static function verificarConexion()
    {
        $refreshToken = GoogleConfiguracion::getValorPorClave('refresh_token');
        $email = GoogleConfiguracion::getValorPorClave('email_organizador');

        Flight::json([
            'conectado'         => !empty($refreshToken),
            'email_organizador' => $email
        ]);
    }

    /**
     * Sincroniza una tarea según su clase:
     * 1 = Tarea (Google Tasks), 2 = Reunión, 3 = Evento individual
     */
    public static function crearEventoDesdeTarea()
    {
        $data = Flight::request()->data;

        $idTarea = $data['id_tarea'];
        if (empty($idTarea)) {
            Flight::json(['error' => true, 'message' => 'id_tarea es requerido'], 400);
            return;
        }

        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT 
                tc.id,
                tc.descripcion,
                tc.fecha_limite,
                tc.hora_inicio,
                tc.hora_fin,
                tc.observaciones,
                tc.google_event_id,
                tc.id_clase_tarea,
                tc.correos_asistentes,
                CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_colaborador,
                p.correo_electronico
            FROM tareas_colaboradores tc
            INNER JOIN colaboradores c ON c.id = tc.id_colaborador
            INNER JOIN personas p ON p.id = c.id_persona
            WHERE tc.id = :id_tarea AND tc.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_tarea', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $tarea = $sentence->fetch();

        if (!$tarea) {
            Flight::json(['error' => true, 'message' => 'Tarea no encontrada'], 404);
            return;
        }

        if (empty($tarea['correo_electronico'])) {
            Flight::json(['error' => true, 'message' => 'El colaborador no tiene correo electrónico registrado'], 400);
            return;
        }

        if (!empty($tarea['google_event_id'])) {
            Flight::json(['error' => true, 'message' => 'Esta tarea ya fue sincronizada', 'google_event_id' => $tarea['google_event_id']], 409);
            return;
        }

        $claseTarea = (int) ($tarea['id_clase_tarea'] ?? 3);

        switch ($claseTarea) {
            case 1:
                self::crearGoogleTask($db, $tarea, $idTarea);
                break;
            case 2:
                self::crearReunionCalendar($db, $tarea, $idTarea);
                break;
            case 3:
            default:
                self::crearEventoIndividualCalendar($db, $tarea, $idTarea);
                break;
        }
    }

    /**
     * Clase 1: Crea una tarea en Google Tasks.
     */
    private static function crearGoogleTask($db, $tarea, $idTarea)
    {
        $accessToken = self::obtenerAccessTokenValido();
        if (empty($accessToken)) {
            Flight::json(['error' => true, 'message' => 'No se pudo obtener un access token válido. Reconecte Google Calendar.'], 401);
            return;
        }

        $taskData = [
            'title'  => 'Tarea: ' . $tarea['descripcion'],
            'notes'  => $tarea['observaciones'] ?? '',
            'status' => 'needsAction'
        ];

        if (!empty($tarea['fecha_limite'])) {
            $taskData['due'] = $tarea['fecha_limite'] . 'T23:59:59.000Z';
        }

        $url = self::$tasksApiUrl . '/lists/@default/tasks';

        $resultado = self::hacerRequestHTTP('POST', $url, $taskData, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        if (isset($resultado['error'])) {
            Flight::json(['error' => true, 'message' => 'Error al crear tarea en Google Tasks', 'detalle' => $resultado['error']], 500);
            return;
        }

        $googleEventId = $resultado['id'] ?? '';
        $sentence = $db->prepare("UPDATE tareas_colaboradores SET google_event_id = :google_event_id WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':google_event_id', $googleEventId);
        $sentence->bindParam(':id', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json([
            'ok'              => true,
            'message'         => 'Tarea creada exitosamente en Google Tasks',
            'google_event_id' => $googleEventId,
            'tipo'            => 'task'
        ]);
    }

    /**
     * Clase 2: Crea un evento de reunión con múltiples asistentes.
     * Incluye al colaborador, al organizador (liceolumen) y correos adicionales.
     */
    private static function crearReunionCalendar($db, $tarea, $idTarea)
    {
        $fechaLimite = $tarea['fecha_limite'];
        if (empty($fechaLimite)) {
            Flight::json(['error' => true, 'message' => 'La reunión requiere fecha para agendar'], 400);
            return;
        }

        $horaInicio = !empty($tarea['hora_inicio']) ? $tarea['hora_inicio'] : '08:00:00';
        $horaFin = !empty($tarea['hora_fin']) ? $tarea['hora_fin'] : '09:00:00';

        $attendees = [];
        $attendees[] = ['email' => $tarea['correo_electronico']];

        $emailOrganizador = GoogleConfiguracion::getValorPorClave('email_organizador');
        if (!empty($emailOrganizador)) {
            $attendees[] = ['email' => $emailOrganizador];
        }

        if (!empty($tarea['correos_asistentes'])) {
            $correosExtra = array_map('trim', explode(',', $tarea['correos_asistentes']));
            foreach ($correosExtra as $correo) {
                if (!empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    $attendees[] = ['email' => $correo];
                }
            }
        }

        $evento = [
            'summary'     => 'Reunión: ' . $tarea['descripcion'],
            'description' => $tarea['observaciones'] ?? '',
            'start'       => [
                'dateTime' => $fechaLimite . 'T' . $horaInicio,
                'timeZone' => 'America/Bogota'
            ],
            'end'         => [
                'dateTime' => $fechaLimite . 'T' . $horaFin,
                'timeZone' => 'America/Bogota'
            ],
            'attendees'   => $attendees,
            'reminders'   => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];

        $resultado = self::crearEventoEnCalendar($evento);

        if (isset($resultado['error'])) {
            Flight::json(['error' => true, 'message' => 'Error al crear reunión en Google Calendar', 'detalle' => $resultado['error']], 500);
            return;
        }

        $googleEventId = $resultado['id'];
        $sentence = $db->prepare("UPDATE tareas_colaboradores SET google_event_id = :google_event_id WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':google_event_id', $googleEventId);
        $sentence->bindParam(':id', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json([
            'ok'              => true,
            'message'         => 'Reunión creada exitosamente en Google Calendar',
            'google_event_id' => $googleEventId,
            'html_link'       => $resultado['htmlLink'] ?? '',
            'tipo'            => 'reunion'
        ]);
    }

    /**
     * Clase 3: Crea un evento individual (solo el colaborador).
     */
    private static function crearEventoIndividualCalendar($db, $tarea, $idTarea)
    {
        $fechaLimite = $tarea['fecha_limite'];
        if (empty($fechaLimite)) {
            Flight::json(['error' => true, 'message' => 'El evento requiere fecha para agendar'], 400);
            return;
        }

        $horaInicio = !empty($tarea['hora_inicio']) ? $tarea['hora_inicio'] : '08:00:00';
        $horaFin = !empty($tarea['hora_fin']) ? $tarea['hora_fin'] : '09:00:00';

        $evento = [
            'summary'     => 'Tarea: ' . $tarea['descripcion'],
            'description' => $tarea['observaciones'] ?? '',
            'start'       => [
                'dateTime' => $fechaLimite . 'T' . $horaInicio,
                'timeZone' => 'America/Bogota'
            ],
            'end'         => [
                'dateTime' => $fechaLimite . 'T' . $horaFin,
                'timeZone' => 'America/Bogota'
            ],
            'attendees'   => [
                ['email' => $tarea['correo_electronico']]
            ],
            'reminders'   => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];

        $resultado = self::crearEventoEnCalendar($evento);

        if (isset($resultado['error'])) {
            Flight::json(['error' => true, 'message' => 'Error al crear evento en Google Calendar', 'detalle' => $resultado['error']], 500);
            return;
        }

        $googleEventId = $resultado['id'];
        $sentence = $db->prepare("UPDATE tareas_colaboradores SET google_event_id = :google_event_id WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':google_event_id', $googleEventId);
        $sentence->bindParam(':id', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json([
            'ok'              => true,
            'message'         => 'Evento creado exitosamente en Google Calendar',
            'google_event_id' => $googleEventId,
            'html_link'       => $resultado['htmlLink'] ?? '',
            'tipo'            => 'evento'
        ]);
    }

    /**
     * Crea un evento en Google Calendar a partir de una actividad del colaborador.
     */
    public static function crearEventoDesdeActividad()
    {
        $data = Flight::request()->data;

        $idActividad = $data['id_actividad'];
        if (empty($idActividad)) {
            Flight::json(['error' => true, 'message' => 'id_actividad es requerido'], 400);
            return;
        }

        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT 
                ac.id,
                ac.fecha_hora_inicio,
                ac.fecha_hora_fin,
                ac.observaciones,
                ac.google_event_id,
                ta.nombre AS tipo_actividad,
                CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_colaborador,
                p.correo_electronico
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores ta ON ta.id = ac.id_tipo_actividad
            INNER JOIN colaboradores c ON c.id = ac.id_colaborador
            INNER JOIN personas p ON p.id = c.id_persona
            WHERE ac.id = :id_actividad AND ac.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_actividad', $idActividad);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $actividad = $sentence->fetch();

        if (!$actividad) {
            Flight::json(['error' => true, 'message' => 'Actividad no encontrada'], 404);
            return;
        }

        if (empty($actividad['correo_electronico'])) {
            Flight::json(['error' => true, 'message' => 'El colaborador no tiene correo electrónico registrado'], 400);
            return;
        }

        if (!empty($actividad['google_event_id'])) {
            Flight::json(['error' => true, 'message' => 'Esta actividad ya fue sincronizada con Google Calendar', 'google_event_id' => $actividad['google_event_id']], 409);
            return;
        }

        $evento = [
            'summary'     => $actividad['tipo_actividad'] . ' - ' . $actividad['nombre_colaborador'],
            'description' => $actividad['observaciones'] ?? '',
            'start'       => [
                'dateTime' => str_replace(' ', 'T', $actividad['fecha_hora_inicio']),
                'timeZone' => 'America/Bogota'
            ],
            'end'         => [
                'dateTime' => str_replace(' ', 'T', $actividad['fecha_hora_fin']),
                'timeZone' => 'America/Bogota'
            ],
            'attendees'   => [
                ['email' => $actividad['correo_electronico']]
            ],
            'reminders'   => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];

        $resultado = self::crearEventoEnCalendar($evento);

        if (isset($resultado['error'])) {
            Flight::json(['error' => true, 'message' => 'Error al crear evento en Google Calendar', 'detalle' => $resultado['error']], 500);
            return;
        }

        $googleEventId = $resultado['id'];
        $sentence = $db->prepare("UPDATE actividades_colaboradores SET google_event_id = :google_event_id WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':google_event_id', $googleEventId);
        $sentence->bindParam(':id', $idActividad);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json([
            'ok'              => true,
            'message'         => 'Evento creado exitosamente en Google Calendar',
            'google_event_id' => $googleEventId,
            'html_link'       => $resultado['htmlLink'] ?? ''
        ]);
    }

    // ===================================================================
    // MÉTODOS PRIVADOS
    // ===================================================================

    private static function crearEventoEnCalendar($evento)
    {
        $accessToken = self::obtenerAccessTokenValido();

        if (empty($accessToken)) {
            return ['error' => 'No se pudo obtener un access token válido. Reconecte Google Calendar.'];
        }

        $url = self::$calendarApiUrl . '/calendars/primary/events?sendUpdates=all';

        return self::hacerRequestHTTP('POST', $url, $evento, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
    }

    private static function obtenerAccessTokenValido()
    {
        $accessToken = GoogleConfiguracion::getValorPorClave('access_token');
        $tokenExpiraEn = GoogleConfiguracion::getValorPorClave('token_expira_en');

        if (!empty($accessToken) && !empty($tokenExpiraEn) && (time() + 300) < (int) $tokenExpiraEn) {
            return $accessToken;
        }

        return self::refrescarAccessToken();
    }

    private static function refrescarAccessToken()
    {
        $refreshToken = GoogleConfiguracion::getValorPorClave('refresh_token');
        $clientId = GoogleConfiguracion::getValorPorClave('client_id');
        $clientSecret = GoogleConfiguracion::getValorPorClave('client_secret');

        if (empty($refreshToken) || empty($clientId) || empty($clientSecret)) {
            error_log("Google Calendar - No se puede refrescar token: faltan credenciales");
            return null;
        }

        $postData = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token'
        ];

        $response = self::hacerRequestHTTP('POST', self::$tokenUrl, $postData, [
            'Content-Type: application/x-www-form-urlencoded'
        ], true);

        if (isset($response['access_token'])) {
            GoogleConfiguracion::setValorPorClave('access_token', $response['access_token']);

            if (!empty($response['expires_in'])) {
                $expiraEn = time() + (int) $response['expires_in'];
                GoogleConfiguracion::setValorPorClave('token_expira_en', (string) $expiraEn);
            }

            return $response['access_token'];
        }

        error_log("Google Calendar - Error al refrescar token: " . json_encode($response));
        return null;
    }

    private static function intercambiarCodigoPorTokens($code, $clientId, $clientSecret, $redirectUri)
    {
        $postData = [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code'
        ];

        return self::hacerRequestHTTP('POST', self::$tokenUrl, $postData, [
            'Content-Type: application/x-www-form-urlencoded'
        ], true);
    }

    private static function hacerRequestHTTP($method, $url, $data = null, $headers = [], $formEncoded = false)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $body = $formEncoded ? http_build_query($data) : json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Google Calendar cURL error: " . $error);
            return ['error' => 'Error de conexión: ' . $error];
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : ['error' => 'Respuesta no válida', 'raw' => $response, 'http_code' => $httpCode];
    }

    private static function getConfigValor($db, $clave)
    {
        $sentence = $db->prepare("SELECT valor FROM google_configuracion WHERE clave = :clave AND id_tenant = :id_tenant");
        $sentence->bindParam(':clave', $clave);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $row = $sentence->fetch();
        return $row ? $row['valor'] : null;
    }

    private static function setConfigValor($db, $clave, $valor)
    {
        $sentence = $db->prepare("UPDATE google_configuracion SET valor = :valor WHERE clave = :clave AND id_tenant = :id_tenant");
        $sentence->bindParam(':clave', $clave);
        $sentence->bindParam(':valor', $valor);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    private static function redirigirConMensaje($tenant, $status, $message)
    {
        $params = http_build_query([
            'google_calendar' => $status,
            'message'         => $message
        ]);
        header('Location: https://app.genialisis.com/#/administracion/configuracion-google?' . $params);
        exit(0);
    }
}