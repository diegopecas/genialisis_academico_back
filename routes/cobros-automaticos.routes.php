<?php

// CONVENIOS
Flight::route('GET /convenios', [Convenios::class, 'getAll']);
Flight::route('GET /convenios/@id', [Convenios::class, 'getById']);
Flight::route('POST /convenios', [Convenios::class, 'new']);
Flight::route('PUT /convenios', [Convenios::class, 'replace']);
Flight::route('DELETE /convenios', [Convenios::class, 'delete']);

// CONVENIOS ESTUDIANTE
Flight::route('GET /convenios-estudiante/@id_estudiante', [ConveniosEstudiante::class, 'getByEstudiante']);
Flight::route('GET /convenios-estudiante-activos/@id_estudiante', [ConveniosEstudiante::class, 'getActivosByEstudiante']);
Flight::route('POST /convenios-estudiante', [ConveniosEstudiante::class, 'new']);
Flight::route('PUT /convenios-estudiante', [ConveniosEstudiante::class, 'replace']);
Flight::route('DELETE /convenios-estudiante', [ConveniosEstudiante::class, 'delete']);

// TIPOS EVENTO COBRO
Flight::route('GET /tipos-evento-cobro', [TiposEventoCobro::class, 'getAll']);
Flight::route('GET /tipos-evento-cobro/@id', [TiposEventoCobro::class, 'getById']);

// REGLAS COBRO AUTOMÁTICO
Flight::route('GET /reglas-cobro-automatico', [ReglasCobroAutomatico::class, 'getAll']);
Flight::route('GET /reglas-cobro-automatico/@id', [ReglasCobroAutomatico::class, 'getById']);
Flight::route('POST /reglas-cobro-automatico', [ReglasCobroAutomatico::class, 'new']);
Flight::route('PUT /reglas-cobro-automatico', [ReglasCobroAutomatico::class, 'replace']);
Flight::route('DELETE /reglas-cobro-automatico', [ReglasCobroAutomatico::class, 'delete']);

// COBROS AUTOMÁTICOS HISTORIAL
Flight::route('GET /cobros-automaticos-historial/@id_estudiante', [CobrosAutomaticosHistorial::class, 'getByEstudiante']);
Flight::route('POST /cobros-automaticos-historial', [CobrosAutomaticosHistorial::class, 'new']);

// MOTOR DE COBROS AUTOMÁTICOS
Flight::route('POST /motor-cobros/evaluar', [MotorCobrosAutomaticos::class, 'evaluar']);
Flight::route('POST /motor-cobros/ejecutar', [MotorCobrosAutomaticos::class, 'ejecutar']);