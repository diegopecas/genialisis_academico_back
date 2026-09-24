<?php

// Tareas Estudiantes: portal de padres (especificas primero)
Flight::route('GET /tareas-estudiantes/estudiante/@id_estudiante', [TareasEstudiantes::class, 'getPorEstudiante']);
Flight::route('GET /tareas-estudiantes/detalle/@id/@id_estudiante', [TareasEstudiantes::class, 'getDetallePadres']);
Flight::route('GET /tareas-estudiantes/@id/estudiantes', [TareasEstudiantes::class, 'getEstudiantes']);
Flight::route('POST /tareas-estudiantes/publicar', [TareasEstudiantes::class, 'publicar']);

// Tareas Estudiantes: CRUD
Flight::route('GET /tareas-estudiantes/@id', [TareasEstudiantes::class, 'getById']);
Flight::route('GET /tareas-estudiantes', [TareasEstudiantes::class, 'getAll']);
Flight::route('POST /tareas-estudiantes', [TareasEstudiantes::class, 'new']);
Flight::route('PUT /tareas-estudiantes', [TareasEstudiantes::class, 'replace']);
Flight::route('DELETE /tareas-estudiantes', [TareasEstudiantes::class, 'delete']);

// Estado y calificacion por estudiante
Flight::route('GET /tareas-estudiantes-x-estudiante/escala', [TareasEstudiantesXEstudiante::class, 'getEscala']);
Flight::route('PUT /tareas-estudiantes-x-estudiante/calificar', [TareasEstudiantesXEstudiante::class, 'calificar']);
Flight::route('POST /tareas-estudiantes-x-estudiante/marcar-enviada', [TareasEstudiantesXEstudiante::class, 'marcarEnviada']);

// Responsables
Flight::route('GET /tareas-estudiantes-responsables/colaboradores', [TareasEstudiantesResponsables::class, 'getColaboradores']);

// Adjuntos (especificas primero)
Flight::route('POST /tareas-estudiantes-adjuntos/subir', [TareasEstudiantesAdjuntos::class, 'subir']);
Flight::route('GET /tareas-estudiantes-adjuntos/descargar/@id', [TareasEstudiantesAdjuntos::class, 'descargar']);
Flight::route('DELETE /tareas-estudiantes-adjuntos', [TareasEstudiantesAdjuntos::class, 'delete']);

// Foro de preguntas
Flight::route('GET /tareas-estudiantes-preguntas/tarea/@id_tarea', [TareasEstudiantesPreguntas::class, 'getByTarea']);
Flight::route('POST /tareas-estudiantes-preguntas', [TareasEstudiantesPreguntas::class, 'new']);
Flight::route('DELETE /tareas-estudiantes-preguntas', [TareasEstudiantesPreguntas::class, 'delete']);
