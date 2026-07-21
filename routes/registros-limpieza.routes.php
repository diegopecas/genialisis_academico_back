<?php
// REGISTROS DE LIMPIEZA
Flight::route('GET /registros-limpieza', ['RegistrosLimpieza', 'getAll']);
Flight::route('GET /registros-limpieza/elementos-proceso', ['RegistrosLimpieza', 'getElementosParaProceso']);
Flight::route('GET /registros-limpieza/rapido-preview', ['RegistrosLimpieza', 'getRapidoPreview']);
Flight::route('GET /registros-limpieza/pendientes-supervision', ['RegistrosLimpieza', 'getPendientesSupervision']);
Flight::route('GET /registros-limpieza/@id', ['RegistrosLimpieza', 'getById']);
Flight::route('POST /registros-limpieza', ['RegistrosLimpieza', 'new']);
Flight::route('PUT /registros-limpieza', ['RegistrosLimpieza', 'update']);
Flight::route('POST /registros-limpieza/rapido', ['RegistrosLimpieza', 'crearRapido']);
Flight::route('POST /registros-limpieza/iniciar', ['RegistrosLimpieza', 'iniciar']);
Flight::route('POST /registros-limpieza/finalizar', ['RegistrosLimpieza', 'finalizar']);
Flight::route('POST /registros-limpieza/supervisar', ['RegistrosLimpieza', 'supervisar']);
Flight::route('POST /registros-limpieza/supervisar-lote', ['RegistrosLimpieza', 'supervisarLote']);
Flight::route('POST /registros-limpieza/cancelar', ['RegistrosLimpieza', 'cancelar']);

// CONSUMO GENERAL POR ÁREA Y PROCESO
Flight::route('GET /registros-limpieza/consumo-general', ['AreasFisicasXProcesosLimpiezaConsumo', 'getPorAreaProceso']);
Flight::route('GET /registros-limpieza/consumo-general/productos', ['AreasFisicasXProcesosLimpiezaConsumo', 'getProductosDisponibles']);
Flight::route('POST /registros-limpieza/consumo-general', ['AreasFisicasXProcesosLimpiezaConsumo', 'new']);
Flight::route('PUT /registros-limpieza/consumo-general', ['AreasFisicasXProcesosLimpiezaConsumo', 'update']);
Flight::route('DELETE /registros-limpieza/consumo-general', ['AreasFisicasXProcesosLimpiezaConsumo', 'delete']);

// ESTADOS DE REGISTRO DE LIMPIEZA
Flight::route('GET /estados-registro-limpieza', ['EstadosRegistroLimpieza', 'getAll']);
Flight::route('GET /estados-registro-limpieza/@id', ['EstadosRegistroLimpieza', 'getById']);