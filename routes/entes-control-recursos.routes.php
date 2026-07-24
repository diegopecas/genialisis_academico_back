<?php
// TIPOS DE REPORTES
Flight::route('GET /tipos-reportes', [TiposReportes::class, 'getAll']);

// CATÁLOGO DE REPORTES
Flight::route('GET /catalogo-reportes', [CatalogoReportes::class, 'getAll']);
Flight::route('GET /catalogo-reportes/entes-control', [CatalogoReportes::class, 'getParaEntesControl']);
Flight::route('GET /catalogo-reportes/@id', [CatalogoReportes::class, 'getById']);
Flight::route('POST /catalogo-reportes', [CatalogoReportes::class, 'new']);
Flight::route('PUT /catalogo-reportes', [CatalogoReportes::class, 'replace']);
Flight::route('DELETE /catalogo-reportes', [CatalogoReportes::class, 'delete']);

// RECURSOS POR ENTE DE CONTROL
Flight::route('GET /entes-control-recursos/@idEnteControl', [EntesControlRecursos::class, 'getByEnte']);
Flight::route('GET /entes-control-recursos/@idEnteControl/resolver', [EntesControlRecursos::class, 'resolver']);
Flight::route('POST /entes-control-recursos', [EntesControlRecursos::class, 'new']);
Flight::route('DELETE /entes-control-recursos', [EntesControlRecursos::class, 'delete']);
