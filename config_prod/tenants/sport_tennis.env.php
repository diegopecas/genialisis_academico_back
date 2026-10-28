<?php

// ========================================
// CONFIGURACIÓN BD PRINCIPAL (GENIALISIS)
// ========================================
define('DB_HOST', '132.148.181.209');
define('DB_NAME', 'g_fundadores_prod');
define('DB_USERNAME', 'usr_g_fundadores_prod');
define('DB_PASSWORD', 'G8mdj!$2UqmF=XIh');
define('DB_CHARSET', 'utf8mb4');
define('DB_TYPE', 'mysql');
define('DB_DSN', DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// ========================================
// ID NUMÉRICO DEL TENANT (= tenants.id en la BD maestra)
// Lo lee TenantContext::id() para aislar las filas por id_tenant.
// ========================================
define('TENANT_ID', 8);


// =============================================
// CONFIGURACIÓN VAPID - PUSH NOTIFICATIONS
// =============================================
define('VAPID_PUBLIC_KEY', 'BObkU8JSPUs8tC4Hk3m31gc_yfV9bPkrVPWxJPL9qpFd3wSnL8q4kDBTcnrYWn4ll9CUv5rcyebb8jU5o9ZL1vQ');
define('VAPID_PRIVATE_KEY', 'HnpvQ11fiBrnqyQOs_mFNvEdMeiE-RM1-WtxCoQ2B6o');
define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');

// =============================================
// INTEGRACION CON EL PORTAL WEB (portal-back)
// API key con la que el back del portal manda las solicitudes de
// inscripcion (POST /integracion-portal/solicitudes). La misma va en el
// portal como GENIALISIS_API_KEY.
// =============================================
define('INTEGRACION_PORTAL_API_KEY', '386a11829f3e70eb15a74b5e80f354f49252655b0bbc1dd1a9cf238def7ea0f9');

// =============================================
// PASARELA DE PAGOS Y CORREO
// Ya no van aqui: se configuran en Genialisis (Datos maestros >
// Administracion > Configuracion > Pasarela de pagos / Servicio de correo)
// y las llaves quedan cifradas en la base con la llave de cifrado.env.php.
// =============================================