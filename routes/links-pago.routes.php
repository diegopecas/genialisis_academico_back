<?php
// LINKS DE PAGO (administracion, con token)
Flight::route('GET /links-pago/solicitud/@id_solicitud', [LinksPago::class, 'getBySolicitud']);
Flight::route('PUT /links-pago/generar', [LinksPago::class, 'generar']);
Flight::route('PUT /links-pago/reenviar-correo', [LinksPago::class, 'reenviarCorreo']);
Flight::route('PUT /links-pago/verificar', [LinksPago::class, 'verificar']);
Flight::route('GET /links-pago/datos-pago/@id', [LinksPago::class, 'datosPago']); // datos del PDF para transferir

// COBROS PROGRAMADOS (pantalla de Operaciones > Financiero, con token)
Flight::route('GET /links-pago/cobros-programados', [LinksPago::class, 'cobrosProgramados']);
Flight::route('PUT /links-pago/enviar-cobro', [LinksPago::class, 'enviarCobro']);

// AVISOS DE PAGO DE BOLD (sin token, ver index.php)
// URL para el panel de Bold: https://<back>/pagos-bold/webhook?tenant=<codigo>
Flight::route('POST /pagos-bold/webhook', [LinksPago::class, 'webhookBold']);
