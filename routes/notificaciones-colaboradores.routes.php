<?php
// NOTIFICACIONES A COLABORADORES (bandeja del portal institucional)
Flight::route('GET /notificaciones-colaboradores', [NotificacionesColaboradores::class, 'getAll']);
Flight::route('GET /notificaciones-colaboradores/@id', [NotificacionesColaboradores::class, 'getById']);
Flight::route('POST /notificaciones-colaboradores', [NotificacionesColaboradores::class, 'new']);
Flight::route('PUT /notificaciones-colaboradores', [NotificacionesColaboradores::class, 'replace']);
Flight::route('DELETE /notificaciones-colaboradores', [NotificacionesColaboradores::class, 'delete']);

// DESTINATARIOS
Flight::route('GET /notificaciones-colaboradores-destinatarios', [NotificacionesColaboradoresDestinatarios::class, 'getAll']);
Flight::route('GET /notificaciones-colaboradores-destinatarios/@id', [NotificacionesColaboradoresDestinatarios::class, 'getById']);
Flight::route('GET /notificaciones-colaboradores-destinatarios-notificacion/@id_notificacion', [NotificacionesColaboradoresDestinatarios::class, 'getByNotificacion']);
Flight::route('GET /notificaciones-colaboradores-mias', [NotificacionesColaboradoresDestinatarios::class, 'getMisNotificaciones']);
Flight::route('GET /notificaciones-colaboradores-no-leidas', [NotificacionesColaboradoresDestinatarios::class, 'getNoLeidas']);
Flight::route('POST /notificaciones-colaboradores-destinatarios', [NotificacionesColaboradoresDestinatarios::class, 'new']);
Flight::route('PUT /notificaciones-colaboradores-destinatarios', [NotificacionesColaboradoresDestinatarios::class, 'replace']);
Flight::route('PUT /notificaciones-colaboradores-destinatarios/leida', [NotificacionesColaboradoresDestinatarios::class, 'marcarLeida']);
Flight::route('DELETE /notificaciones-colaboradores-destinatarios', [NotificacionesColaboradoresDestinatarios::class, 'delete']);

// TIPOS (catalogo global, solo lectura)
Flight::route('GET /tipos-notificacion-colaborador', [TiposNotificacionColaborador::class, 'getAll']);
Flight::route('GET /tipos-notificacion-colaborador/@id', [TiposNotificacionColaborador::class, 'getById']);
