<?php
// PROVEEDORES
Flight::route('GET /proveedores', [Proveedores::class, 'getAll']);
Flight::route('GET /proveedores-activos', [Proveedores::class, 'getActivos']);
Flight::route('GET /proveedores/@id', [Proveedores::class, 'getById']);
Flight::route('GET /proveedores/tipo/@id_tipo', [Proveedores::class, 'getByTipo']);
Flight::route('POST /proveedores', [Proveedores::class, 'new']);
Flight::route('PUT /proveedores', [Proveedores::class, 'replace']);
Flight::route('DELETE /proveedores', [Proveedores::class, 'delete']);
Flight::route('POST /proveedores/verificar-duplicados', [Proveedores::class, 'verificarDuplicados']);

// TIPOS DE PROVEEDOR
Flight::route('GET /tipos-proveedor', [TiposProveedor::class, 'getAll']);
Flight::route('GET /tipos-proveedor/@id', [TiposProveedor::class, 'getById']);