<?php

define('DB_HOST', '92.205.2.161');
define('DB_NAME', 'g_lumen_prod');
//define('DB_USERNAME', 'usr-genialisis-admin-prod');
//define('DB_PASSWORD', 'Ge,{_^G,v.%wY;Pl');

define('DB_USERNAME', 'usr_g_lumen_prod');
define('DB_PASSWORD', 'Z$3$M,Ao1pNCH2G8');

define('DB_CHARSET', 'utf8mb4');
define('DB_TYPE', 'mysql');
define('DB_DSN', DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// =============================================
// CONFIGURACIÓN VAPID - PUSH NOTIFICATIONS
// =============================================
define('VAPID_PUBLIC_KEY', 'BObkU8JSPUs8tC4Hk3m31gc_yfV9bPkrVPWxJPL9qpFd3wSnL8q4kDBTcnrYWn4ll9CUv5rcyebb8jU5o9ZL1vQ');
define('VAPID_PRIVATE_KEY', 'HnpvQ11fiBrnqyQOs_mFNvEdMeiE-RM1-WtxCoQ2B6o');
define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');