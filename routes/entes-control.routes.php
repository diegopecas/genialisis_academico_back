<?php
// ENTES DE CONTROL
Flight::route('GET /entes-control', [EntesControl::class, 'getAll']);
Flight::route('GET /entes-control/@id', [EntesControl::class, 'getById']);
Flight::route('GET /entes-control-duplicados/@idPersona', [EntesControl::class, 'verificarDuplicados']);
Flight::route('POST /entes-control', [EntesControl::class, 'new']);
Flight::route('PUT /entes-control', [EntesControl::class, 'replace']);
Flight::route('DELETE /entes-control', [EntesControl::class, 'delete']);

// INSTITUCIÓN (única por tenant)
Flight::route('GET /instituciones', [Instituciones::class, 'getByTenant']);
Flight::route('POST /instituciones', [Instituciones::class, 'new']);