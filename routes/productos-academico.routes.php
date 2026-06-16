<?php
// PRODUCTOS ACADEMICO
Flight::route('GET /productos-academico', ['ProductosAcademico', 'getAll']);
Flight::route('GET /productos-academico/@id', ['ProductosAcademico', 'getById']);
Flight::route('POST /productos-academico', ['ProductosAcademico', 'new']);
Flight::route('PUT /productos-academico', ['ProductosAcademico', 'replace']);
Flight::route('DELETE /productos-academico', ['ProductosAcademico', 'delete']);
Flight::route('GET /productos-academico-disponibles', ['ProductosAcademico', 'getProductosDisponiblesParaAcademico']);

// TIPOS PRODUCTO ACADEMICO
Flight::route('GET /tipos-producto-academico', ['TiposProductoAcademico', 'getAll']);

// GRADOS POR PRODUCTO ACADEMICO
Flight::route('GET /productos-academico/@id/grados', ['ProductosAcademico', 'getGradosByProducto']);
Flight::route('POST /productos-academico/@id/grados', ['ProductosAcademico', 'asignarGrados']);