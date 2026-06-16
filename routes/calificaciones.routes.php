<?php

// CALIFICACIONES
Flight::route('GET /calificaciones', [Calificaciones::class, 'getAll']);
Flight::route('GET /calificaciones/@id', [Calificaciones::class, 'getById']);
Flight::route('POST /calificaciones', [Calificaciones::class, 'new']);
Flight::route('PUT /calificaciones', [Calificaciones::class, 'replace']);
Flight::route('DELETE /calificaciones', [Calificaciones::class, 'delete']);
Flight::route('GET /calificaciones-tarea-sprint/@id', [Calificaciones::class, 'getByTareaSprint']);
Flight::route('GET /calificaciones-vista-tarea/@id_grupo/@id_tarea_sprint', [Calificaciones::class, 'getVistaTarea']);
Flight::route('GET /calificaciones-tareas-sprint-estudiante/@id_estudiante/@id_sprint', [Calificaciones::class, 'consultarCalificacionesTareasSprintEstudiante']);
Flight::route('GET /calificaciones-tareas-sprint-estudiantes/@id_sprint', [Calificaciones::class, 'consultarCalificacionesTareasSprintEstudiantes']);
Flight::route('GET /calificaciones-tareas-sprint-estudiantes/calificaciones/@id_sprint', [Calificaciones::class, 'obtenerCalificacionesPorSprintEstudiantes']);
Flight::route('GET /calificaciones-tareas-sprint-estudiantes/compara_estudiante/@id_estudiante', [Calificaciones::class, 'obtenerCalificacionesPorSprintEstudiantes']);
Flight::route('GET /calificaciones-tareas-sprint-estudiantes/compara_grupo/@id_grupo', [Calificaciones::class, 'obtenerCalificacionesPorSprintEstudiantes']);
Flight::route('GET /calificaciones-pdm-estudiante/@id_estudiante', [Calificaciones::class, 'consultarCalificacionesPDMXEstudiante']);
Flight::route('GET /calificaciones-pdm-estudiantes', [Calificaciones::class, 'consultarCalificacionesPDMXEstudiantes']);
Flight::route('GET /calificaciones-tareas-sprint-estudiantes/calificaciones/@id_sprint/estudiante/@id_estudiante', [Calificaciones::class, 'obtenerCalificacionesEstudianteDetalle']);

// PARAMETROS-CALIFICACIONES
Flight::route('GET /parametros-calificaciones', [ParametrosCalificaciones::class, 'getAll']);
Flight::route('GET /parametros-calificaciones/@id', [ParametrosCalificaciones::class, 'getById']);
Flight::route('POST /parametros-calificaciones', [ParametrosCalificaciones::class, 'new']);
Flight::route('PUT /parametros-calificaciones', [ParametrosCalificaciones::class, 'replace']);
Flight::route('DELETE /parametros-calificaciones', [ParametrosCalificaciones::class, 'delete']);

// VALORES-PARAMETROS-CALIFICACIONES
Flight::route('GET /valores-parametros-calificaciones', [ValoresParametrosCalificaciones::class, 'getAll']);
Flight::route('GET /valores-parametros-calificaciones/@id', [ValoresParametrosCalificaciones::class, 'getById']);
Flight::route('GET /valores-parametros-calificaciones/parametro/@id_parametro', [ValoresParametrosCalificaciones::class, 'getByParametro']);
Flight::route('POST /valores-parametros-calificaciones', [ValoresParametrosCalificaciones::class, 'new']);
Flight::route('PUT /valores-parametros-calificaciones', [ValoresParametrosCalificaciones::class, 'replace']);
Flight::route('DELETE /valores-parametros-calificaciones', [ValoresParametrosCalificaciones::class, 'delete']);