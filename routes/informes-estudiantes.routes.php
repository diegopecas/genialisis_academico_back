<?php
// =====================================================================
// INFORMES DE CALIFICACIONES - CALIFICACIÓN DEL CORTE
// =====================================================================

// ----- Vista por estudiante -----
Flight::route('GET /informes-estudiantes/grupo/@id_grupo/corte/@id_corte', [InformesEstudiantes::class, 'getEstadoPorGrupo']);
Flight::route('GET /informes-estudiantes/estudiante/@id_estudiante/corte/@id_corte', [InformesEstudiantes::class, 'getByEstudianteCorte']);

// ----- Vista masiva por sección -----
Flight::route('GET /informes-estudiantes/secciones-grupo/@id_grupo', [InformesEstudiantes::class, 'getSeccionesPorGrupo']);
Flight::route('GET /informes-estudiantes/seccion-grupo/@id_grupo/corte/@id_corte/seccion/@id_seccion', [InformesEstudiantes::class, 'getSeccionPorGrupo']);

// ----- Acciones -----
Flight::route('POST /informes-estudiantes/generar',         [InformesEstudiantes::class, 'generar']);
Flight::route('POST /informes-estudiantes/generar-masivo',  [InformesEstudiantes::class, 'generarMasivo']);
Flight::route('PUT /informes-estudiantes/guardar',          [InformesEstudiantes::class, 'guardar']);
Flight::route('PUT /informes-estudiantes/guardar-masivo',   [InformesEstudiantes::class, 'guardarMasivo']);
Flight::route('PUT /informes-estudiantes/confirmar',        [InformesEstudiantes::class, 'confirmar']);
Flight::route('PUT /informes-estudiantes/reabrir',          [InformesEstudiantes::class, 'reabrir']);
