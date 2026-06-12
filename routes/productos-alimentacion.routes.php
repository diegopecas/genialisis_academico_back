<?php
// PRODUCTOS ALIMENTACION
Flight::route('GET /productos-alimentacion', ['ProductosAlimentacion', 'getAll']);
Flight::route('GET /productos-alimentacion/@id', ['ProductosAlimentacion', 'getById']);
Flight::route('POST /productos-alimentacion', ['ProductosAlimentacion', 'new']);
Flight::route('PUT /productos-alimentacion', ['ProductosAlimentacion', 'replace']);
Flight::route('DELETE /productos-alimentacion', ['ProductosAlimentacion', 'delete']);
Flight::route('GET /productos-alimentacion-disponibles-alimentacion', ['ProductosAlimentacion', 'getProductosDisponiblesParaAlimentacion']);

// TIPOS PRODUCTO ALIMENTACION
Flight::route('GET /tipos-producto-alimentacion', ['TiposProductoAlimentacion', 'getAll']);

// CLASIFICACIONES DE PRODUCTOS ALIMENTACION
Flight::route('GET /productos-alimentacion/@id/clasificaciones', ['ProductosAlimentacion', 'getClasificacionesByProducto']);
Flight::route('POST /productos-alimentacion/@id/clasificaciones', ['ProductosAlimentacion', 'asignarClasificaciones']);
Flight::route('DELETE /productos-alimentacion/@id/clasificaciones/@id_clasificacion', ['ProductosAlimentacion', 'eliminarClasificacion']);

// CLASIFICACION PRODUCTOS ALIMENTACION (CRUD básico)
Flight::route('GET /clasificacion-productos-alimentacion', ['ClasificacionProductosAlimentacion', 'getAll']);
Flight::route('GET /clasificacion-productos-alimentacion/@id', ['ClasificacionProductosAlimentacion', 'getById']);
Flight::route('POST /clasificacion-productos-alimentacion', ['ClasificacionProductosAlimentacion', 'new']);
Flight::route('PUT /clasificacion-productos-alimentacion', ['ClasificacionProductosAlimentacion', 'replace']);
Flight::route('DELETE /clasificacion-productos-alimentacion', ['ClasificacionProductosAlimentacion', 'delete']);

Flight::route('GET /productos-alimentacion/clasificacion/@id_clasificacion/con-stock', ['ProductosAlimentacion', 'getProductosPorClasificacionConStock']);

Flight::route('POST /productos-alimentacion/validar-stock-multiple', ['ProductosAlimentacion', 'validarStockMultiple']);