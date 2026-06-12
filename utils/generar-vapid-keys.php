<?php
/*=============================================
GENERAR CLAVES VAPID
Ejecutar UNA SOLA VEZ en tu máquina local:
  php generar-vapid-keys.php

Requiere: composer require minishlink/web-push
=============================================*/

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "=============================================\n";
echo "CLAVES VAPID GENERADAS\n";
echo "=============================================\n\n";
echo "VAPID_PUBLIC_KEY:\n";
echo $keys['publicKey'] . "\n\n";
echo "VAPID_PRIVATE_KEY:\n";
echo $keys['privateKey'] . "\n\n";
echo "=============================================\n";
echo "INSTRUCCIONES:\n";
echo "1. Copia VAPID_PUBLIC_KEY al frontend:\n";
echo "   push-notification.service.ts → VAPID_PUBLIC_KEY\n\n";
echo "2. Agrega ambas claves al archivo .env.php del tenant:\n";
echo "   define('VAPID_PUBLIC_KEY', '{$keys['publicKey']}');\n";
echo "   define('VAPID_PRIVATE_KEY', '{$keys['privateKey']}');\n";
echo "   define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');\n";
echo "=============================================\n";