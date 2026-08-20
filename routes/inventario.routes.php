<?php
// ELEMENTOS DE INVENTARIO (catálogo)
Flight::route('GET /elementos-inventario', [ElementosInventario::class, 'getAll']);
Flight::route('GET /elementos-inventario/@id', [ElementosInventario::class, 'getById']);
Flight::route('GET /elementos-inventario-grupo/@id_grupo', [ElementosInventario::class, 'getByGrupo']);
Flight::route('POST /elementos-inventario', [ElementosInventario::class, 'new']);
Flight::route('PUT /elementos-inventario', [ElementosInventario::class, 'replace']);
Flight::route('DELETE /elementos-inventario', [ElementosInventario::class, 'delete']);

// GRUPOS A LOS QUE APLICA CADA ELEMENTO
Flight::route('GET /elementos-inventario-grupos', [ElementosInventarioGrupos::class, 'getAll']);
Flight::route('GET /elementos-inventario-grupos/@id', [ElementosInventarioGrupos::class, 'getById']);
Flight::route('GET /elementos-inventario-grupos-elemento/@id_elemento', [ElementosInventarioGrupos::class, 'getByElemento']);
Flight::route('POST /elementos-inventario-grupos', [ElementosInventarioGrupos::class, 'new']);
Flight::route('PUT /elementos-inventario-grupos-elemento', [ElementosInventarioGrupos::class, 'replaceGruposElemento']);
Flight::route('DELETE /elementos-inventario-grupos', [ElementosInventarioGrupos::class, 'delete']);

// INVENTARIO DIARIO
Flight::route('GET /inventario-diario/@id', [InventarioDiario::class, 'getById']);
Flight::route('GET /inventario-diario-estudiante/@id_estudiante/@fecha', [InventarioDiario::class, 'getPorEstudiante']);
Flight::route('POST /inventario-diario/dia-grupo', [InventarioDiario::class, 'getDiaGrupo']);
Flight::route('POST /inventario-diario/guardar-lote', [InventarioDiario::class, 'guardarLote']);
Flight::route('POST /inventario-diario/reporte', [InventarioDiario::class, 'getReporte']);
Flight::route('POST /inventario-diario', [InventarioDiario::class, 'new']);
Flight::route('PUT /inventario-diario', [InventarioDiario::class, 'replace']);
Flight::route('DELETE /inventario-diario', [InventarioDiario::class, 'delete']);
