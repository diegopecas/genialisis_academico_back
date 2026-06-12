<?php

// GOOGLE CONFIGURACION (CRUD admin)
Flight::route('GET /google-configuracion', [GoogleConfiguracion::class, 'getAll']);
Flight::route('GET /google-configuracion/@id', [GoogleConfiguracion::class, 'getById']);
Flight::route('PUT /google-configuracion', [GoogleConfiguracion::class, 'replace']);

// GOOGLE CALENDAR - OAuth
Flight::route('GET /google-calendar/autorizar', [GoogleCalendarService::class, 'generarUrlAutorizacion']);
Flight::route('GET /google-calendar/verificar-conexion', [GoogleCalendarService::class, 'verificarConexion']);

// GOOGLE CALENDAR - Crear eventos
Flight::route('POST /google-calendar/evento-tarea', [GoogleCalendarService::class, 'crearEventoDesdeTarea']);
Flight::route('POST /google-calendar/evento-actividad', [GoogleCalendarService::class, 'crearEventoDesdeActividad']);

// CLASES TAREAS (catálogo)
Flight::route('GET /clases-tareas', [ClasesTareas::class, 'getAll']);
Flight::route('GET /clases-tareas/@id', [ClasesTareas::class, 'getById']);
