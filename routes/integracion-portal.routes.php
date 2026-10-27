<?php
// INTEGRACION CON LOS PORTALES WEB DE LOS CLIENTES (sin token de sesion)
// La llama el back del portal, no una pagina. Se autentica con X-Tenant y la
// API key del tenant en X-API-KEY (ver index.php y registrarDesdePortal).
Flight::route('POST /integracion-portal/solicitudes', [SolicitudesInscripcionPublica::class, 'registrarDesdePortal']);
Flight::route('GET /integracion-portal/catalogo', [SolicitudesInscripcionPublica::class, 'catalogoParaPortal']);
