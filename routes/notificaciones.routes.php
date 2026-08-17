<?php

// Categorías de notificaciones (específicas primero)
Flight::route('GET /notificaciones-categorias/activos', [NotificacionesCategorias::class, 'getActivos']);
Flight::route('GET /notificaciones-categorias/@id', [NotificacionesCategorias::class, 'getById']);
Flight::route('GET /notificaciones-categorias', [NotificacionesCategorias::class, 'getAll']);
Flight::route('POST /notificaciones-categorias', [NotificacionesCategorias::class, 'new']);
Flight::route('PUT /notificaciones-categorias', [NotificacionesCategorias::class, 'replace']);
Flight::route('DELETE /notificaciones-categorias', [NotificacionesCategorias::class, 'delete']);

// Tipos de respuesta (específicas primero)
Flight::route('GET /notificaciones-respuestas-tipos/activos-con-opciones', [NotificacionesRespuestasTipos::class, 'getActivosConOpciones']);
Flight::route('GET /notificaciones-respuestas-tipos/@id', [NotificacionesRespuestasTipos::class, 'getById']);
Flight::route('GET /notificaciones-respuestas-tipos', [NotificacionesRespuestasTipos::class, 'getAll']);
Flight::route('POST /notificaciones-respuestas-tipos', [NotificacionesRespuestasTipos::class, 'new']);
Flight::route('PUT /notificaciones-respuestas-tipos', [NotificacionesRespuestasTipos::class, 'replace']);
Flight::route('DELETE /notificaciones-respuestas-tipos', [NotificacionesRespuestasTipos::class, 'delete']);

// Opciones de respuesta (específicas primero)
Flight::route('GET /notificaciones-respuestas-opciones/tipo/@id_respuesta_tipo', [NotificacionesRespuestasOpciones::class, 'getByTipo']);
Flight::route('GET /notificaciones-respuestas-opciones/@id', [NotificacionesRespuestasOpciones::class, 'getById']);
Flight::route('GET /notificaciones-respuestas-opciones', [NotificacionesRespuestasOpciones::class, 'getAll']);
Flight::route('POST /notificaciones-respuestas-opciones', [NotificacionesRespuestasOpciones::class, 'new']);
Flight::route('PUT /notificaciones-respuestas-opciones', [NotificacionesRespuestasOpciones::class, 'replace']);
Flight::route('DELETE /notificaciones-respuestas-opciones', [NotificacionesRespuestasOpciones::class, 'delete']);

// Bandeja del portal de acudientes (específicas primero)
Flight::route('GET /notificaciones-destinatarios/mis-notificaciones', [NotificacionesDestinatarios::class, 'getMisNotificaciones']);
Flight::route('GET /notificaciones-destinatarios/no-leidas', [NotificacionesDestinatarios::class, 'getNoLeidas']);
Flight::route('POST /notificaciones-destinatarios/marcar-leida', [NotificacionesDestinatarios::class, 'marcarLeida']);
Flight::route('POST /notificaciones-destinatarios/responder', [NotificacionesDestinatarios::class, 'responder']);
Flight::route('GET /notificaciones-destinatarios/resumen/@id_notificacion', [NotificacionesDestinatarios::class, 'getResumen']);
Flight::route('GET /notificaciones-destinatarios/notificacion/@id_notificacion', [NotificacionesDestinatarios::class, 'getByNotificacion']);

// Adjuntos (específicas primero)
Flight::route('POST /notificaciones-adjuntos/subir', [NotificacionesAdjuntos::class, 'subir']);
Flight::route('GET /notificaciones-adjuntos/descargar/@id', [NotificacionesAdjuntos::class, 'descargar']);
Flight::route('GET /notificaciones-adjuntos/notificacion/@id_notificacion', [NotificacionesAdjuntos::class, 'getByNotificacion']);
Flight::route('GET /notificaciones-adjuntos/@id', [NotificacionesAdjuntos::class, 'getById']);
Flight::route('DELETE /notificaciones-adjuntos', [NotificacionesAdjuntos::class, 'delete']);

// Notificaciones (específicas primero)
Flight::route('POST /notificaciones/previsualizar-destinatarios', [Notificaciones::class, 'previsualizarDestinatarios']);
Flight::route('GET /notificaciones/@id', [Notificaciones::class, 'getById']);
Flight::route('GET /notificaciones', [Notificaciones::class, 'getAll']);
Flight::route('POST /notificaciones', [Notificaciones::class, 'new']);
Flight::route('PUT /notificaciones', [Notificaciones::class, 'replace']);
Flight::route('DELETE /notificaciones', [Notificaciones::class, 'delete']);
