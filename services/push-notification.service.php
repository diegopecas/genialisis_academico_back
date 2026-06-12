<?php
/*=============================================
SERVICIO - PUSH NOTIFICATIONS
Archivo: services/push-notification.service.php

Requiere: composer require minishlink/web-push
=============================================*/

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

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
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('[Push] Error obteniendo suscripciones: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Envía las notificaciones push usando la librería web-push
     */
    private function enviarPush(array $suscripciones, string $titulo, string $cuerpo, array $datosExtra): void
    {
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

            $payload = json_encode(array_merge([
                'title' => $titulo,
                'body'  => $cuerpo,
                'icon'  => '/assets/images/logo_app.png',
                'badge' => '/assets/images/logo_app.png',
                'tag'   => 'wa-msg-' . ($datosExtra['id_conversacion'] ?? time()),
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
                    // OK
                } else {
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