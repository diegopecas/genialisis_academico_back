<?php

// SPRINTS
Flight::route('GET /sprints', [Sprints::class, 'getAll']);
Flight::route('GET /sprints/cobertura-curricular', [Sprints::class, 'getAnalisisCoberturaCurricular']);
Flight::route('GET /sprints/actual-y-anteriores', [Sprints::class, 'getActualYAnteriores']);
Flight::route('GET /sprints/@id', [Sprints::class, 'getById']);
Flight::route('POST /sprints', [Sprints::class, 'new']);
Flight::route('PUT /sprints', [Sprints::class, 'replace']);
Flight::route('DELETE /sprints', [Sprints::class, 'delete']);
Flight::route('GET /sprints-actual', [Sprints::class, 'getActual']);
Flight::route('GET /sprints-anio/@anio', [Sprints::class, 'getByAnio']);
Flight::route('GET /sprints-corte/@id_corte_academico', [Sprints::class, 'getByCorteAcademico']);
Flight::route('GET /sprints-evaluaciones', [Sprints::class, 'getEvaluaciones']);
Flight::route('GET /sprints-estadisticas', [Sprints::class, 'getObtenerTodoConEstadisticas']);
Flight::route('PUT /sprints/finalizar/@id_sprint', [Sprints::class, 'finalizarSprint']);
// VALIDACIONES DE SPRINTS
Flight::route('GET /sprints/verificar-solapamiento', [Sprints::class, 'verificarSolapamiento']);
Flight::route('GET /sprints/verificar-numero-unico', [Sprints::class, 'verificarNumeroUnico']);
Flight::route('GET /sprints/verificar-sprint-evaluacion', [Sprints::class, 'verificarSprintEvaluacion']);
Flight::route('GET /sprints/por-anio/@anio', [Sprints::class, 'getSprintsPorAnio']);
Flight::route('PUT /sprints/desactivar-actuales', [Sprints::class, 'desactivarSprintsActuales']);
// ANÁLISIS DE TIEMPO
Flight::route('GET /sprints/analisis-tiempo/@id_sprint', [Sprints::class, 'getAnalisisTiempoSprint']);
Flight::route('GET /sprints/validar-actividad/@id_sprint/@id_actividad', [Sprints::class, 'validarActividadEnSprint']);

// TAREAS X SPRINTS
Flight::route('GET /tareas-x-sprints', [TareasXSprints::class, 'getAll']);
Flight::route('GET /tareas-x-sprints/reporte-ejecucion', [TareasXSprints::class, 'getReporteEjecucionTareas']);
Flight::route('GET /tareas-x-sprints/@id', [TareasXSprints::class, 'getById']);
Flight::route('GET /tareas-x-sprints/sprint/@id_sprint', [TareasXSprints::class, 'getBySprintId']);
Flight::route('GET /tareas-x-sprints/actividad/@id_actividad', [TareasXSprints::class, 'getByActividadId']);
Flight::route('POST /tareas-x-sprints', [TareasXSprints::class, 'new']);
Flight::route('PUT /tareas-x-sprints', [TareasXSprints::class, 'replace']);
Flight::route('PUT /tareas-x-sprints-inicio', [TareasXSprints::class, 'iniciar']);
Flight::route('DELETE /tareas-x-sprints', [TareasXSprints::class, 'delete']);
Flight::route('GET /tareas-x-sprints/estadisticas/@id_sprint', [TareasXSprints::class, 'getEstadisticasSprint']);
Flight::route('GET /tareas-x-sprints/sprint-detallado/@id_sprint', [TareasXSprints::class, 'getBySprintIdDetallado']);
Flight::route('GET /tareas-x-sprints/resumen-grupo/@id_grupo', [TareasXSprints::class, 'getResumenClasesPorGrupo']);
Flight::route('GET /tareas-x-sprints/resumen-todos-grupos', [TareasXSprints::class, 'getResumenClasesTodosGrupos']);
Flight::route('PUT /tareas-x-sprints/cambiar-estado', [TareasXSprints::class, 'cambiarEstado']);
Flight::route('GET /tareas-x-sprints/importar/@id_sprint', [TareasXSprints::class, 'getTareasParaImportar']);
Flight::route('POST /tareas-x-sprints/importar-masivo', [TareasXSprints::class, 'importarMasivo']);
Flight::route('GET /tareas-x-sprints/sprint-grupo-area/@id_sprint/@id_grupo/@id_area', [TareasXSprints::class, 'getBySprintGrupoArea']);
Flight::route('PUT /tareas-x-sprints/actualizar-orden', [TareasXSprints::class, 'actualizarOrden']);
Flight::route('PUT /tareas-x-sprints/actualizar-orden-duracion', [TareasXSprints::class, 'actualizarOrdenYDuracion']);
Flight::route('PUT /tareas-x-sprints/observacion', [TareasXSprints::class, 'actualizarObservacion']);
Flight::route('PUT /tareas-x-sprints/sincronizar', [TareasXSprints::class, 'sincronizar']);

// TAREAS X SPRINTS X ESTUDIANTE
Flight::route('GET /tareas-x-sprints-x-estudiante/tarea/@id_tarea_x_sprint', [TareasXSprintsXEstudiante::class, 'getByTareaSprint']);
Flight::route('POST /tareas-x-sprints-x-estudiante', [TareasXSprintsXEstudiante::class, 'crear']);
Flight::route('PUT /tareas-x-sprints-x-estudiante/observacion', [TareasXSprintsXEstudiante::class, 'actualizarObservacion']);

// DIAS X SPRINT
Flight::route('GET /dias-x-sprint', [DiasXSprint::class, 'getAll']);
Flight::route('GET /dias-x-sprint/@id', [DiasXSprint::class, 'getById']);
Flight::route('GET /dias-x-sprint/sprint/@id_sprint', [DiasXSprint::class, 'getBySprintId']);
Flight::route('POST /dias-x-sprint', [DiasXSprint::class, 'new']);
Flight::route('PUT /dias-x-sprint', [DiasXSprint::class, 'replace']);
Flight::route('DELETE /dias-x-sprint', [DiasXSprint::class, 'delete']);
Flight::route('GET /dias-x-sprint/calcular-habiles/@fecha_inicial/@fecha_final', [DiasXSprint::class, 'calcularDiasHabiles']);
Flight::route('DELETE /dias-x-sprint/sprint/@id_sprint', [DiasXSprint::class, 'eliminarPorSprint']);

// Rutas para Estados de Tareas
Flight::route('GET /estados-tareas', array('EstadosTareas', 'getAll'));
Flight::route('GET /estados-tareas/@id', array('EstadosTareas', 'getById'));
Flight::route('POST /estados-tareas', array('EstadosTareas', 'new'));
Flight::route('PUT /estados-tareas', array('EstadosTareas', 'replace'));
Flight::route('DELETE /estados-tareas', array('EstadosTareas', 'delete'));