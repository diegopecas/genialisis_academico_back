<?php

// ========================================
// CONFIGURACIÓN BD PRINCIPAL (GENIALISIS)
// ========================================
define('DB_HOST', '132.148.181.209');
define('DB_NAME', 'g_fundadores_qa');
define('DB_USERNAME', 'usr_g_fundadores_qa');
define('DB_PASSWORD', 'B4,gdBs)xrz[6M[9');
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
define('VAPID_PUBLIC_KEY', '');
define('VAPID_PRIVATE_KEY', '');
define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');

// =============================================
// INTEGRACION CON EL PORTAL WEB (portal-back)
// API key con la que el back del portal manda las solicitudes de
// inscripcion (POST /integracion-portal/solicitudes). La misma va en el
// portal como GENIALISIS_API_KEY.
// =============================================
define('INTEGRACION_PORTAL_API_KEY', '386a11829f3e70eb15a74b5e80f354f49252655b0bbc1dd1a9cf238def7ea0f9');

// =============================================
// LINK DE PAGO DE BOLD
// Llaves del boton de pagos (Panel de Bold > Integraciones). En modo de
// pruebas Bold firma los avisos con llave vacia: BOLD_MODO_PRUEBAS = true.
// URL del webhook para el panel de Bold:
// https://api.genialisis.com/pagos-bold/webhook?tenant=sport_tennis
// =============================================
define('BOLD_API_URL', 'https://integrations.api.bold.co');
define('BOLD_API_KEY', 'OofRyZas6ED0THXwzBHlhXFx6UsoQVlI2uRKsnBSml0');
define('BOLD_SECRET_KEY', 'M8oAIDH4cu-SC8XUzUEEag');
define('BOLD_MODO_PRUEBAS', true);
define('BOLD_CALLBACK_URL', '');

// =============================================
// CORREO (correo.genialisis.com): correo de aprobacion con el link de pago
// =============================================
define('CORREO_API_URL', 'https://correo.genialisis.com/enviar');
define('CORREO_API_KEY', '53e3c2f8bba89193ca20ee5d6bf96c4734bd75fd1636fcad0d242f9218817389');
define('CORREO_RESPONDER_A', 'info@sporttennis.org');