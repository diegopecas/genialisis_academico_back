<?php
// ASISTENCIA ESTUDIANTES
Flight::route('GET /asistencia-estudiantes', [AsistenciaEstudiantes::class, 'getAll']);
Flight::route('GET /asistencia-estudiantes/@id_estudiante', [AsistenciaEstudiantes::class, 'getByIdEstudiante']);
Flight::route('GET /asistencia-estudiantes-ingresos', [AsistenciaEstudiantes::class, 'getIngresosHoy']); // niños que han ingresado
Flight::route('GET /asistencia-estudiantes-salidas', [AsistenciaEstudiantes::class, 'getSalidasHoy']); // niños que han salido
Flight::route('GET /asistencia-estudiantes-no-ingresos', [AsistenciaEstudiantes::class, 'getNoIngresosHoy']); // niños que no han ingresado
Flight::route('GET /asistencia-estudiantes-no-salidas', [AsistenciaEstudiantes::class, 'getNoSalidasHoy']); // niños que no han salido
Flight::route('POST /asistencia-estudiantes', [AsistenciaEstudiantes::class, 'new']); // registrar ingreso
Flight::route('PUT /asistencia-estudiantes', [AsistenciaEstudiantes::class, 'replace']); // registrar salida
Flight::route('DELETE /asistencia-estudiantes', [AsistenciaEstudiantes::class, 'delete']); // borrar registro
Flight::route('POST /asistencia-estudiantes/verificar-x-dia', [AsistenciaEstudiantes::class, 'verificarAsistenciaEstudiante']); // verificar la asistencia en un dia
Flight::route('POST /asistencia-estudiantes/mensual', [AsistenciaEstudiantes::class, 'getAsistenciaMensual']); // obtener asistencia mensual de un estudiante
Flight::route('GET /asistencia-estudiantes/resumen-grupo/@id_grupo', [AsistenciaEstudiantes::class, 'getResumenAsistenciaPorGrupo']);
Flight::route('POST /asistencia-estudiantes/reporte-por-fecha', [AsistenciaEstudiantes::class, 'getReporteAsistenciaPorFecha']); // Reporte de asistencia por fecha específica
Flight::route('GET /asistencia-estudiantes-reporte-indicadores', [AsistenciaEstudiantes::class, 'getReporteIndicadoresAsistencia']); // Reporte de indicadores de asistencia por estudiante
Flight::route('GET /asistencia-estudiantes-fecha/@fecha', [AsistenciaEstudiantes::class, 'getEstudiantesPorFecha']); // estudiantes que asistieron en una fecha

// Seguimiento de asistencia
Flight::route('GET /seguimiento-asistencia-estudiantes', [AsistenciaEstudiantes::class, 'getSeguimientoAsistencia']);

// Historial de recordatorios de asistencia
Flight::route('GET /historial-recordatorios-asistencia', [HistorialRecordatoriosAsistencia::class, 'getAll']);
Flight::route('GET /historial-recordatorios-asistencia/estudiante/@id', [HistorialRecordatoriosAsistencia::class, 'getByEstudiante']);
Flight::route('POST /historial-recordatorios-asistencia', [HistorialRecordatoriosAsistencia::class, 'new']);