<?php

// =====================================================
// RUTAS PARA TIPOS DE PRÉSTAMOS
// =====================================================
Flight::route('GET /tipos-prestamo', [TiposPrestamos::class, 'getAll']);
Flight::route('GET /tipos-prestamo/@id', [TiposPrestamos::class, 'getById']);
Flight::route('POST /tipos-prestamo', [TiposPrestamos::class, 'new']);
Flight::route('PUT /tipos-prestamo', [TiposPrestamos::class, 'replace']);
Flight::route('DELETE /tipos-prestamo', [TiposPrestamos::class, 'delete']);

// =====================================================
// RUTAS PARA TIPOS DE DESCUENTO PRÉSTAMO
// =====================================================
Flight::route('GET /tipos-descuento-prestamo', [TiposDescuentoPrestamo::class, 'getAll']);
Flight::route('GET /tipos-descuento-prestamo/@id', [TiposDescuentoPrestamo::class, 'getById']);
Flight::route('POST /tipos-descuento-prestamo', [TiposDescuentoPrestamo::class, 'new']);
Flight::route('PUT /tipos-descuento-prestamo', [TiposDescuentoPrestamo::class, 'replace']);
Flight::route('DELETE /tipos-descuento-prestamo', [TiposDescuentoPrestamo::class, 'delete']);

// =====================================================
// RUTAS PARA ESTADOS DE PRÉSTAMO
// =====================================================
Flight::route('GET /estados-prestamo', [EstadosPrestamo::class, 'getAll']);
Flight::route('GET /estados-prestamo/@id', [EstadosPrestamo::class, 'getById']);
Flight::route('POST /estados-prestamo', [EstadosPrestamo::class, 'new']);
Flight::route('PUT /estados-prestamo', [EstadosPrestamo::class, 'replace']);
Flight::route('DELETE /estados-prestamo', [EstadosPrestamo::class, 'delete']);

// =====================================================
// RUTAS PARA ESTADOS DE CUOTA PRÉSTAMO
// =====================================================
Flight::route('GET /estados-cuota-prestamo', [EstadosCuotaPrestamo::class, 'getAll']);
Flight::route('GET /estados-cuota-prestamo/@id', [EstadosCuotaPrestamo::class, 'getById']);
Flight::route('POST /estados-cuota-prestamo', [EstadosCuotaPrestamo::class, 'new']);
Flight::route('PUT /estados-cuota-prestamo', [EstadosCuotaPrestamo::class, 'replace']);
Flight::route('DELETE /estados-cuota-prestamo', [EstadosCuotaPrestamo::class, 'delete']);

// =====================================================
// RUTAS PARA TIPOS DE PAGO PRÉSTAMO
// =====================================================
Flight::route('GET /tipos-pago-prestamo', [TiposPagoPrestamo::class, 'getAll']);
Flight::route('GET /tipos-pago-prestamo/@id', [TiposPagoPrestamo::class, 'getById']);
Flight::route('POST /tipos-pago-prestamo', [TiposPagoPrestamo::class, 'new']);
Flight::route('PUT /tipos-pago-prestamo', [TiposPagoPrestamo::class, 'replace']);
Flight::route('DELETE /tipos-pago-prestamo', [TiposPagoPrestamo::class, 'delete']);

// =====================================================
// RUTAS PARA PRÉSTAMOS
// =====================================================
Flight::route('GET /prestamos', [Prestamo::class, 'getAll']);
Flight::route('GET /prestamos/@id', [Prestamo::class, 'getById']);
Flight::route('GET /prestamos-colaborador/@id_colaborador', [Prestamo::class, 'getByColaborador']);
Flight::route('POST /prestamos', [Prestamo::class, 'new']);
Flight::route('PUT /prestamos', [Prestamo::class, 'replace']);
Flight::route('DELETE /prestamos', [Prestamo::class, 'delete']);
Flight::route('PUT /prestamos-aprobar', [Prestamo::class, 'aprobar']);
Flight::route('PUT /prestamos-anular', [Prestamo::class, 'anular']);

// =====================================================
// RUTAS PARA CUOTAS DE PRÉSTAMOS
// =====================================================
Flight::route('GET /prestamos-cuotas', [PrestamoCuota::class, 'getAll']);
Flight::route('GET /prestamos-cuotas/@id', [PrestamoCuota::class, 'getById']);
Flight::route('GET /prestamos-cuotas-prestamo/@id_prestamo', [PrestamoCuota::class, 'getByPrestamo']);
Flight::route('POST /prestamos-cuotas', [PrestamoCuota::class, 'new']);
Flight::route('POST /prestamos-cuotas-batch', [PrestamoCuota::class, 'createBatch']);
Flight::route('PUT /prestamos-cuotas', [PrestamoCuota::class, 'replace']);
Flight::route('DELETE /prestamos-cuotas', [PrestamoCuota::class, 'delete']);
Flight::route('PUT /prestamos-cuotas-marcar-pagada', [PrestamoCuota::class, 'marcarPagada']);
Flight::route('PUT /prestamos-cuotas-anular', [PrestamoCuota::class, 'anular']);

// =====================================================
// RUTAS PARA PAGOS DE PRÉSTAMOS
// =====================================================
Flight::route('GET /prestamos-pagos', [PrestamosPagos::class, 'getAll']);
Flight::route('GET /prestamos-pagos/@id', [PrestamosPagos::class, 'getById']);
Flight::route('GET /prestamos-pagos-prestamo/@id_prestamo', [PrestamosPagos::class, 'getByPrestamo']);
Flight::route('GET /prestamos-pagos-cuota/@id_cuota', [PrestamosPagos::class, 'getByCuota']);
Flight::route('GET /prestamos-cuotas-con-saldo/@id_prestamo', [PrestamosPagos::class, 'getCuotasConSaldo']);
Flight::route('POST /prestamos-pagos', [PrestamosPagos::class, 'new']);
Flight::route('PUT /prestamos-pagos', [PrestamosPagos::class, 'replace']);
Flight::route('PUT /prestamos-pagos-anular', [PrestamosPagos::class, 'anular']);
Flight::route('DELETE /prestamos-pagos', [PrestamosPagos::class, 'delete']);