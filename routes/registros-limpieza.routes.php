<?php
// REGISTROS DE LIMPIEZA
Flight::route('GET /registros-limpieza', ['RegistrosLimpieza', 'getAll']);
Flight::route('GET /registros-limpieza/elementos-proceso', ['RegistrosLimpieza', 'getElementosParaProceso']);
Flight::route('GET /registros-limpieza/rapido-preview', ['RegistrosLimpieza', 'getRapidoPreview']);
Flight::route('GET /registros-limpieza/@id', ['RegistrosLimpieza', 'getById']);
Flight::route('POST /registros-limpieza', ['RegistrosLimpieza', 'new']);
Flight::route('PUT /registros-limpieza', ['RegistrosLimpieza', 'update']);
Flight::route('POST /registros-limpieza/rapido', ['RegistrosLimpieza', 'crearRapido']);
Flight::route('POST /registros-limpieza/iniciar', ['RegistrosLimpieza', 'iniciar']);
Flight::route('POST /registros-limpieza/finalizar', ['RegistrosLimpieza', 'finalizar']);
Flight::route('POST /registros-limpieza/supervisar', ['RegistrosLimpieza', 'supervisar']);
Flight::route('POST /registros-limpieza/cancelar', ['RegistrosLimpieza', 'cancelar']);

// ESTADOS DE REGISTRO DE LIMPIEZA
Flight::route('GET /estados-registro-limpieza', ['EstadosRegistroLimpieza', 'getAll']);
Flight::route('GET /estados-registro-limpieza/@id', ['EstadosRegistroLimpieza', 'getById']);