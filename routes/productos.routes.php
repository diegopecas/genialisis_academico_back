<?php
// PRODUCTOS
Flight::route('GET /productos', ['Productos', 'getAll']);
Flight::route('GET /productos-activos', ['Productos', 'getActivos']);
Flight::route('GET /productos/@id', ['Productos', 'getById']);
Flight::route('GET /productos/proveedor/@id_proveedor', ['Productos', 'getByProveedor']);
Flight::route('GET /productos-bajo-stock', ['Productos', 'getBajoStock']);
Flight::route('POST /productos', ['Productos', 'new']);
Flight::route('PUT /productos', ['Productos', 'replace']);
Flight::route('DELETE /productos', ['Productos', 'delete']);
Flight::route('POST /productos-verificar-duplicados', ['Productos', 'verificarDuplicados']);

// UNIDADES DE MEDIDA
Flight::route('GET /unidades-medida', ['UnidadesMedida', 'getAll']);
Flight::route('GET /unidades-medida/@id', ['UnidadesMedida', 'getById']);
Flight::route('POST /unidades-medida', ['UnidadesMedida', 'new']);
Flight::route('PUT /unidades-medida', ['UnidadesMedida', 'replace']);
Flight::route('DELETE /unidades-medida', ['UnidadesMedida', 'delete']);

// PRODUCTOS-PROVEEDORES
Flight::route('GET /productos/@id_producto/proveedores', ['Productos', 'getProveedoresProducto']);
Flight::route('POST /productos-proveedores/asignar', ['Productos', 'asignarProveedor']);
Flight::route('POST /productos-proveedores/quitar', ['Productos', 'quitarProveedor']);

// TIPOS DE PRODUCTO
Flight::route('GET /tipos-producto', ['TiposProducto', 'getAll']);
Flight::route('GET /tipos-producto-activos', ['TiposProducto', 'getActivos']);
Flight::route('GET /tipos-producto/@id', ['TiposProducto', 'getById']);
Flight::route('POST /tipos-producto', ['TiposProducto', 'new']);
Flight::route('PUT /tipos-producto', ['TiposProducto', 'replace']);
Flight::route('DELETE /tipos-producto', ['TiposProducto', 'delete']);
Flight::route('GET /productos/tipo/@id_tipo', ['Productos', 'getByTipo']);
Flight::route('GET /productos-imagen-base64/@id', array('Productos', 'getImagenBase64'));

// MOVIMIENTOS DE PRODUCTOS
Flight::route('GET /movimientos-productos', ['MovimientosProductos', 'getAll']);
Flight::route('GET /movimientos-productos/@id', ['MovimientosProductos', 'getById']);
Flight::route('GET /movimientos-productos/producto/@id_producto', ['MovimientosProductos', 'getByProducto']);
Flight::route('POST /movimientos-productos', ['MovimientosProductos', 'new']);
Flight::route('PUT /movimientos-productos', ['MovimientosProductos', 'actualizar']);
Flight::route('POST /movimientos-productos/anular', ['MovimientosProductos', 'anular']);
Flight::route('POST /movimientos-productos/aprobar', ['MovimientosProductos', 'aprobar']);
Flight::route('POST /movimientos-productos/registrar', ['MovimientosProductos', 'registrar']);
Flight::route('POST /movimientos-productos/agregar-productos', ['MovimientosProductos', 'agregarProductos']);
Flight::route('GET /movimientos-productos-comprobante/@id', ['MovimientosProductos', 'getComprobante']); // Nueva ruta

// CONCEPTOS DE MOVIMIENTO
Flight::route('GET /conceptos-movimiento', ['ConceptosMovimiento', 'getAll']);
Flight::route('GET /conceptos-movimiento/@id', ['ConceptosMovimiento', 'getById']);
Flight::route('GET /conceptos-movimiento/tipo/@tipo', ['ConceptosMovimiento', 'getByTipo']);
Flight::route('POST /conceptos-movimiento', ['ConceptosMovimiento', 'new']);
Flight::route('PUT /conceptos-movimiento', ['ConceptosMovimiento', 'replace']);
Flight::route('DELETE /conceptos-movimiento', ['ConceptosMovimiento', 'delete']);

// ESTADOS DE MOVIMIENTOS
Flight::route('GET /estados-movimientos-productos', ['EstadosMovimientosProductos', 'getAll']);
Flight::route('GET /estados-movimientos-productos/@id', ['EstadosMovimientosProductos', 'getById']);

// DETALLE DE MOVIMIENTOS
Flight::route('GET /movimientos-productos-detalle/movimiento/@id_movimiento', ['MovimientosProductosDetalle', 'getByMovimiento']);
Flight::route('GET /movimientos-productos-detalle/@id', ['MovimientosProductosDetalle', 'getById']);
Flight::route('DELETE /movimientos-productos-detalle', ['MovimientosProductosDetalle', 'delete']);


// UPLOAD DE IMÁGENES
Flight::route('POST /upload/producto-imagen', ['Upload', 'uploadProductImage']);
Flight::route('DELETE /upload/producto-imagen', ['Upload', 'deleteProductImage']);
Flight::route('GET /uploads/productos/@filename', ['Upload', 'getProductImage']);