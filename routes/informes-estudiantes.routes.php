<?php
// =====================================================================
// INFORMES DE CALIFICACIONES - CALIFICACIÓN DEL CORTE
// =====================================================================

Flight::route('GET /informes-estudiantes/grupo/@id_grupo/corte/@id_corte', [InformesEstudiantes::class, 'getEstadoPorGrupo']);
Flight::route('GET /informes-estudiantes/estudiante/@id_estudiante/corte/@id_corte', [InformesEstudiantes::class, 'getByEstudianteCorte']);

Flight::route('POST /informes-estudiantes/generar',  [InformesEstudiantes::class, 'generar']);
Flight::route('PUT /informes-estudiantes/guardar',   [InformesEstudiantes::class, 'guardar']);
Flight::route('PUT /informes-estudiantes/confirmar', [InformesEstudiantes::class, 'confirmar']);
Flight::route('PUT /informes-estudiantes/reabrir',   [InformesEstudiantes::class, 'reabrir']);
