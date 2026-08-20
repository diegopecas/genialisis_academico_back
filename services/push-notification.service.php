<?php
/*=============================================
SERVICIO - PUSH NOTIFICATIONS
Archivo: services/push-notification.service.php

Requiere: composer require minishlink/web-push
=============================================*/

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// El index.php principal no carga el autoload de Composer, por eso el
// namespace de web-push no esta disponible cuando se llama desde la API.
//
// No se puede hacer require de vendor/autoload.php: el composer.json declara
// "autoload.files" con flight/Flight.php, y como index.php ya lo cargo por
// ruta relativa, el require_once de Composer no lo reconoce como el mismo
// archivo y revienta con "Cannot declare class Flight".
//
// La salida es registrar los mapas de Composer (PSR-4, PSR-0 y classmap) sin
// ejecutar autoload_files, que es la parte que incluye Flight. Asi quedan
// resueltas web-push y todas sus dependencias (web-token, guzzle, brick, psr)
// sin tocar index.php.
//
// El webhook carga el autoload completo por su cuenta; este registro es
// adicional y no le interfiere.
if (!class_exists('Minishlink\WebPush\WebPush', false)) {
    $rutaVendorPush = dirname(__DIR__) . '/vendor';

    if (is_file($rutaVendorPush . '/composer/autoload_psr4.php')) {
        $mapaPsr4      = require $rutaVendorPush . '/composer/autoload_psr4.php';
        $mapaPsr0      = is_file($rutaVendorPush . '/composer/autoload_namespaces.php')
                       ? require $rutaVendorPush . '/composer/autoload_namespaces.php'
                       : array();
        $mapaClassmap  = is_file($rutaVendorPush . '/composer/autoload_classmap.php')
                       ? require $rutaVendorPush . '/composer/autoload_classmap.php'
                       : array();

        spl_autoload_register(function ($clase) use ($mapaPsr4, $mapaPsr0, $mapaClassmap) {
            if (isset($mapaClassmap[$clase]) && file_exists($mapaClassmap[$clase])) {
                require_once $mapaClassmap[$clase];
                return;
            }

            foreach ($mapaPsr4 as $prefijo => $directorios) {
                if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
                    continue;
                }
                $relativa = substr($clase, strlen($prefijo));
                foreach ((array)$directorios as $directorio) {
                    $archivo = $directorio . '/' . str_replace('\\', '/', $relativa) . '.php';
                    if (file_exists($archivo)) {
                        require_once $archivo;
                        return;
                    }
                }
            }

            foreach ($mapaPsr0 as $prefijo => $directorios) {
                if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
                    continue;
                }
                foreach ((array)$directorios as $directorio) {
                    $archivo = $directorio . '/' . str_replace('\\', '/', $clase) . '.php';
                    if (file_exists($archivo)) {
                        require_once $archivo;
                        return;
                    }
                }
            }
        });
    }
}

class PushNotificationService
{
    private $db;
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidSubject;

    public function __construct(PDO $db)
    {
        $this->db = $db;

        // Claves VAPID — se leen de las constantes definidas en el env del tenant
        // o de la tabla wa_config si se prefiere almacenarlas en BD
        $this->vapidPublicKey  = defined('VAPID_PUBLIC_KEY')  ? VAPID_PUBLIC_KEY  : '';
        $this->vapidPrivateKey = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : '';
        $this->vapidSubject    = defined('VAPID_SUBJECT')     ? VAPID_SUBJECT     : 'mailto:contacto@genialisis.com';
    }

    /**
     * Envía push notification a todos los usuarios suscritos del tenant
     *
     * @param string $titulo     Título de la notificación
     * @param string $cuerpo     Cuerpo del mensaje
     * @param array  $datosExtra Datos adicionales (id_conversacion, url, etc.)
     */
    public function notificarATodos(string $titulo, string $cuerpo, array $datosExtra = []): void
    {
        if (empty($this->vapidPublicKey) || empty($this->vapidPrivateKey)) {
            error_log('[Push] Claves VAPID no configuradas');
            return;
        }

        $suscripciones = $this->obtenerSuscripcionesActivas();

        if (empty($suscripciones)) {
            return;
        }

        $this->enviarPush($suscripciones, $titulo, $cuerpo, $datosExtra);
    }

    /**
     * Envía push notification a un conjunto concreto de usuarios dentro de un
     * portal determinado.
     *
     * El portal importa porque el endpoint se genera por origen: una misma
     * persona con acceso institucional y de acudiente tiene dos suscripciones
     * distintas. Sin filtrar, un aviso dirigido a los papás también alcanzaría
     * su sesión institucional.
     *
     * A diferencia de notificarATodos, aquí no se exige acceso_chat_wa: esa
     * bandera pertenece al chat de WhatsApp y no tiene relación con la central
     * de notificaciones.
     *
     * @param  array  $idsUsuarios Lista de id de usuarios destinatarios
     * @param  string $titulo      Título de la notificación
     * @param  string $cuerpo      Cuerpo del mensaje
     * @param  array  $datosExtra  Datos adicionales (id_notificacion, url, etc.)
     * @param  string $portal      Portal destino (ver JWTService::PORTAL_*)
     * @return array  Conteo de envíos exitosos y fallidos
     */
    public function notificarAUsuarios(array $idsUsuarios, string $titulo, string $cuerpo, array $datosExtra = [], string $portal = ''): array
    {
        $reporteVacio = ['enviadas' => 0, 'fallidas' => 0, 'sin_suscripcion' => 0];

        if (empty($this->vapidPublicKey) || empty($this->vapidPrivateKey)) {
            error_log('[Push] Claves VAPID no configuradas');
            return $reporteVacio;
        }

        $idsUsuarios = array_values(array_unique(array_filter($idsUsuarios)));

        if (empty($idsUsuarios)) {
            return $reporteVacio;
        }

        if ($portal === '') {
            $portal = JWTService::PORTAL_INSTITUCIONAL;
        }

        $suscripciones = $this->obtenerSuscripcionesPorUsuarios($idsUsuarios, $portal);

        if (empty($suscripciones)) {
            $reporteVacio['sin_suscripcion'] = count($idsUsuarios);
            return $reporteVacio;
        }

        $usuariosConSuscripcion = [];
        foreach ($suscripciones as $suscripcion) {
            $usuariosConSuscripcion[$suscripcion['id_usuario']] = true;
        }

        $reporte = $this->enviarPushConReporte($suscripciones, $titulo, $cuerpo, $datosExtra);
        $reporte['sin_suscripcion'] = count($idsUsuarios) - count($usuariosConSuscripcion);

        return $reporte;
    }

    /**
     * Obtiene todas las suscripciones push activas
     */
    private function obtenerSuscripcionesActivas(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wps.id, wps.id_usuario, wps.endpoint, wps.p256dh, wps.auth
                FROM wa_push_subscriptions wps
                INNER JOIN usuarios u ON u.id = wps.id_usuario
                WHERE wps.activo = 1
                AND u.activo = 1
                AND u.acceso_chat_wa = 1
                AND wps.portal = :portal
                AND wps.id_tenant = :id_tenant
            ");
            $stmt->bindValue(':portal', JWTService::PORTAL_INSTITUCIONAL);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('[Push] Error obteniendo suscripciones: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las suscripciones activas de un conjunto de usuarios dentro de
     * un portal.
     */
    private function obtenerSuscripcionesPorUsuarios(array $idsUsuarios, string $portal): array
    {
        try {
            $marcadores = implode(',', array_fill(0, count($idsUsuarios), '?'));

            $sql = "
                SELECT wps.id, wps.id_usuario, wps.endpoint, wps.p256dh, wps.auth
                FROM wa_push_subscriptions wps
                INNER JOIN usuarios u ON u.id = wps.id_usuario
                WHERE wps.activo = 1
                AND u.activo = 1
                AND wps.portal = ?
                AND wps.id_tenant = ?
                AND wps.id_usuario IN ($marcadores)
            ";

            $parametros = array_merge([$portal, TenantContext::id()], $idsUsuarios);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametros);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('[Push] Error obteniendo suscripciones por usuarios: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Envía las notificaciones push usando la librería web-push
     */
    private function enviarPush(array $suscripciones, string $titulo, string $cuerpo, array $datosExtra): void
    {
        $this->enviarPushConReporte($suscripciones, $titulo, $cuerpo, $datosExtra);
    }

    /**
     * Envía las notificaciones push y devuelve el conteo de resultados.
     *
     * @return array ['enviadas' => int, 'fallidas' => int]
     */
    private function enviarPushConReporte(array $suscripciones, string $titulo, string $cuerpo, array $datosExtra): array
    {
        $reporte = ['enviadas' => 0, 'fallidas' => 0];

        try {
            $auth = [
                'VAPID' => [
                    'subject'    => $this->vapidSubject,
                    'publicKey'  => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ],
            ];

            $webPush = new WebPush($auth);
            $webPush->setAutomaticPadding(false);

            // El tag agrupa la notificación en el sistema operativo. Para los
            // mensajes de WhatsApp se agrupa por conversación; para la central
            // de notificaciones, por notificación, de modo que dos circulares
            // distintas no se reemplacen entre sí en la bandeja del celular.
            $referenciaTag = $datosExtra['id_notificacion']
                ?? $datosExtra['id_conversacion']
                ?? time();

            $payload = json_encode(array_merge([
                'title' => $titulo,
                'body'  => $cuerpo,
                'icon'  => '/assets/images/logo_app.png',
                'badge' => '/assets/images/logo_app.png',
                'tag'   => 'wa-msg-' . $referenciaTag,
            ], $datosExtra));

            // Encolar todas las notificaciones
            foreach ($suscripciones as $sub) {
                $subscription = Subscription::create([
                    'endpoint'        => $sub['endpoint'],
                    'publicKey'       => $sub['p256dh'],
                    'authToken'       => $sub['auth'],
                    'contentEncoding' => 'aesgcm',
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            // Enviar todas y procesar resultados
            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();

                if ($report->isSuccess()) {
                    $reporte['enviadas']++;
                } else {
                    $reporte['fallidas']++;
                    $reason = $report->getReason();
                    error_log("[Push] Error enviando a endpoint: {$reason}");

                    // Si el endpoint ya no es válido (410 Gone o 404), desactivarlo
                    if ($report->isSubscriptionExpired()) {
                        $this->desactivarSuscripcion($endpoint);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('[Push] Error general enviando push: ' . $e->getMessage());
        }

        return $reporte;
    }

    /**
     * Desactiva una suscripción expirada o inválida
     */
    private function desactivarSuscripcion(string $endpoint): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM wa_push_subscriptions WHERE endpoint = :endpoint");
            $stmt->execute(['endpoint' => $endpoint]);
            error_log("[Push] Suscripción expirada eliminada: " . substr($endpoint, 0, 60) . '...');
        } catch (Exception $e) {
            error_log('[Push] Error eliminando suscripción: ' . $e->getMessage());
        }
    }
}