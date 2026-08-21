<?php

// Plantillas de notificaciones (específicas primero)
Flight::route('GET /notificaciones-plantillas/activas', [NotificacionesPlantillas::class, 'getActivas']);
Flight::route('GET /notificaciones-plantillas/@id', [NotificacionesPlantillas::class, 'getById']);
Flight::route('GET /notificaciones-plantillas', [NotificacionesPlantillas::class, 'getAll']);
Flight::route('POST /notificaciones-plantillas', [NotificacionesPlantillas::class, 'new']);
Flight::route('PUT /notificaciones-plantillas', [NotificacionesPlantillas::class, 'replace']);
Flight::route('DELETE /notificaciones-plantillas', [NotificacionesPlantillas::class, 'delete']);
