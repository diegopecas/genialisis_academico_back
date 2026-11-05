<?php
// TIPOS DE NOVEDAD DE ESPACIO (Datos maestros)
Flight::route('GET /tipos-novedad-espacio', [TiposNovedadEspacio::class, 'getAll']);
Flight::route('GET /tipos-novedad-espacio/activos', [TiposNovedadEspacio::class, 'getActivos']);
Flight::route('GET /tipos-novedad-espacio/@id', [TiposNovedadEspacio::class, 'getById']);
Flight::route('POST /tipos-novedad-espacio', [TiposNovedadEspacio::class, 'new']);
Flight::route('PUT /tipos-novedad-espacio', [TiposNovedadEspacio::class, 'replace']);
Flight::route('DELETE /tipos-novedad-espacio', [TiposNovedadEspacio::class, 'delete']);
