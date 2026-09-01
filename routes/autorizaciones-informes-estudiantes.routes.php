<?php

// AUTORIZACIONES DE INFORMES DE ESTUDIANTES
// Las rutas con segmento fijo van antes de las que reciben parametros sueltos,
// para que no las capture la equivocada.
Flight::route('GET /autorizaciones-informes-estudiantes/anios', [AutorizacionesInformesEstudiantes::class, 'getAnios']);
Flight::route('GET /autorizaciones-informes-estudiantes/cortes/@anio', [AutorizacionesInformesEstudiantes::class, 'getCortes']);
Flight::route('GET /autorizaciones-informes-estudiantes/corte/@idCorte/@anio', [AutorizacionesInformesEstudiantes::class, 'getEstudiantesCorte']);
Flight::route('GET /autorizaciones-informes-estudiantes/conceptos-vencidos/@idEstudiante', [AutorizacionesInformesEstudiantes::class, 'getConceptosVencidos']);
Flight::route('GET /autorizaciones-informes-estudiantes/publicables/@idEstudiante', [AutorizacionesInformesEstudiantes::class, 'getSprintsPublicables']);
Flight::route('POST /autorizaciones-informes-estudiantes/lote', [AutorizacionesInformesEstudiantes::class, 'guardarLote']);
