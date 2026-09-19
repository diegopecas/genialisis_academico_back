<?php
// INSTITUCIONES CLIENTE
Flight::route('GET /instituciones-cliente', [InstitucionesCliente::class, 'getAll']);
Flight::route('GET /instituciones-cliente-activos', [InstitucionesCliente::class, 'getActivos']);
Flight::route('GET /instituciones-cliente/@id', [InstitucionesCliente::class, 'getById']);
Flight::route('GET /instituciones-cliente/tipo/@id_tipo', [InstitucionesCliente::class, 'getByTipo']);
Flight::route('POST /instituciones-cliente', [InstitucionesCliente::class, 'new']);
Flight::route('PUT /instituciones-cliente', [InstitucionesCliente::class, 'replace']);
Flight::route('DELETE /instituciones-cliente', [InstitucionesCliente::class, 'delete']);
Flight::route('POST /instituciones-cliente/verificar-duplicados', [InstitucionesCliente::class, 'verificarDuplicados']);

// TIPOS DE INSTITUCION
// /tipos-institucion devuelve solo los activos (alimenta el selector del
// formulario) y /tipos-institucion-todos trae activos e inactivos para la
// pantalla de administracion.
Flight::route('GET /tipos-institucion', [TiposInstitucion::class, 'getAll']);
Flight::route('GET /tipos-institucion-todos', [TiposInstitucion::class, 'getTodos']);
Flight::route('GET /tipos-institucion/@id', [TiposInstitucion::class, 'getById']);
Flight::route('POST /tipos-institucion', [TiposInstitucion::class, 'new']);
Flight::route('PUT /tipos-institucion', [TiposInstitucion::class, 'replace']);
Flight::route('DELETE /tipos-institucion', [TiposInstitucion::class, 'delete']);
