<?php
// UTILES DIARIOS (catálogo)
Flight::route('GET /utiles-diarios', [UtilesDiarios::class, 'getAll']);
Flight::route('GET /utiles-diarios/@id', [UtilesDiarios::class, 'getById']);
Flight::route('GET /utiles-diarios-grupo/@id_grupo', [UtilesDiarios::class, 'getByGrupo']);
Flight::route('POST /utiles-diarios', [UtilesDiarios::class, 'new']);
Flight::route('PUT /utiles-diarios', [UtilesDiarios::class, 'replace']);
Flight::route('DELETE /utiles-diarios', [UtilesDiarios::class, 'delete']);

// GRUPOS A LOS QUE APLICA CADA UTIL
Flight::route('GET /utiles-diarios-grupos', [UtilesDiariosGrupos::class, 'getAll']);
Flight::route('GET /utiles-diarios-grupos/@id', [UtilesDiariosGrupos::class, 'getById']);
Flight::route('GET /utiles-diarios-grupos-util/@id_util', [UtilesDiariosGrupos::class, 'getByUtil']);
Flight::route('POST /utiles-diarios-grupos', [UtilesDiariosGrupos::class, 'new']);
Flight::route('PUT /utiles-diarios-grupos-util', [UtilesDiariosGrupos::class, 'replaceGruposUtil']);
Flight::route('DELETE /utiles-diarios-grupos', [UtilesDiariosGrupos::class, 'delete']);

// REGISTRO DIARIO
Flight::route('GET /utiles-diarios-registro/@id', [RegistroUtilesDiarios::class, 'getById']);
Flight::route('GET /utiles-diarios-registro-estudiante/@id_estudiante/@fecha', [RegistroUtilesDiarios::class, 'getPorEstudiante']);
Flight::route('GET /utiles-diarios-propuesta/@id_estudiante/@fecha', [RegistroUtilesDiarios::class, 'getPropuesta']);
Flight::route('POST /utiles-diarios-registro/dia-grupo', [RegistroUtilesDiarios::class, 'getDiaGrupo']);
Flight::route('POST /utiles-diarios-registro/guardar-lote', [RegistroUtilesDiarios::class, 'guardarLote']);
Flight::route('POST /utiles-diarios-registro/reporte', [RegistroUtilesDiarios::class, 'getReporte']);
Flight::route('POST /utiles-diarios-registro', [RegistroUtilesDiarios::class, 'new']);
Flight::route('PUT /utiles-diarios-registro', [RegistroUtilesDiarios::class, 'replace']);
Flight::route('DELETE /utiles-diarios-registro', [RegistroUtilesDiarios::class, 'delete']);
