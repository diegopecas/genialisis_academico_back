<?php

// EAD-3: RANGOS DE EDAD
Flight::route('GET /ead3-rangos-edad', [Ead3RangosEdad::class, 'getAll']);
Flight::route('GET /ead3-rangos-edad/@id', [Ead3RangosEdad::class, 'getById']);

// EAD-3: ÍTEMS
Flight::route('GET /ead3-items', [Ead3Items::class, 'getAll']);
Flight::route('GET /ead3-items/area/@area', [Ead3Items::class, 'getByArea']);
Flight::route('GET /ead3-items/rango/@id_rango', [Ead3Items::class, 'getByRango']);
Flight::route('GET /ead3-items/area/@area/rango/@id_rango', [Ead3Items::class, 'getByAreaRango']);
Flight::route('GET /ead3-items/evaluar/@id_rango', [Ead3Items::class, 'getItemsParaEvaluar']);

// EAD-3: EVALUACIONES
Flight::route('GET /ead3-evaluaciones', [Ead3Evaluaciones::class, 'getAll']);
Flight::route('GET /ead3-evaluaciones/listado-estudiantes', [Ead3Evaluaciones::class, 'getListadoEstudiantes']);
Flight::route('GET /ead3-evaluaciones/@id', [Ead3Evaluaciones::class, 'getById']);
Flight::route('GET /ead3-evaluaciones/estudiante/@id_estudiante', [Ead3Evaluaciones::class, 'getByEstudiante']);
Flight::route('GET /ead3-evaluaciones/calcular-edad/@id_estudiante', [Ead3Evaluaciones::class, 'calcularEdad']);
Flight::route('GET /ead3-evaluaciones/@id/detalle', [Ead3Evaluaciones::class, 'getDetalleEvaluacion']);
Flight::route('GET /ead3-evaluaciones/@id/retomar', [Ead3Evaluaciones::class, 'getEvaluacionParaRetomar']);
Flight::route('POST /ead3-evaluaciones', [Ead3Evaluaciones::class, 'new']);
Flight::route('POST /ead3-evaluaciones/iniciar', [Ead3Evaluaciones::class, 'iniciar']);
Flight::route('PUT /ead3-evaluaciones/guardar-area', [Ead3Evaluaciones::class, 'guardarArea']);
Flight::route('PUT /ead3-evaluaciones/finalizar', [Ead3Evaluaciones::class, 'finalizar']);
Flight::route('PUT /ead3-evaluaciones/anular', [Ead3Evaluaciones::class, 'anular']);
Flight::route('PUT /ead3-evaluaciones/observaciones', [Ead3Evaluaciones::class, 'actualizarObservaciones']);
Flight::route('PUT /ead3-evaluaciones/analisis', [Ead3Evaluaciones::class, 'actualizarAnalisis']);
Flight::route('PUT /ead3-evaluaciones/item', [Ead3Evaluaciones::class, 'actualizarItem']);

// EAD-3: TABLAS DE CONVERSIÓN
Flight::route('GET /ead3-tablas-conversion/@id_rango/@area', [Ead3TablasConversion::class, 'getByRangoArea']);
Flight::route('POST /ead3-tablas-conversion/convertir', [Ead3TablasConversion::class, 'convertir']);