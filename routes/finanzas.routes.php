<?php
// TIPOS MEDIOS PAGO FINANCIEROS
Flight::route('GET /tipos-medios-pago-financieros', [TiposMediosPagoFinancieros::class, 'getAll']);
Flight::route('GET /tipos-medios-pago-financieros/@id', [TiposMediosPagoFinancieros::class, 'getById']);
Flight::route('POST /tipos-medios-pago-financieros', [TiposMediosPagoFinancieros::class, 'new']);
Flight::route('PUT /tipos-medios-pago-financieros', [TiposMediosPagoFinancieros::class, 'replace']);
Flight::route('DELETE /tipos-medios-pago-financieros', [TiposMediosPagoFinancieros::class, 'delete']);

// TIPOS MOVIMIENTOS FINANCIEROS
Flight::route('GET /tipos-movimientos-financieros', [TiposMovimientosFinancieros::class, 'getAll']);
Flight::route('GET /tipos-movimientos-financieros/@id', [TiposMovimientosFinancieros::class, 'getById']);
Flight::route('POST /tipos-movimientos-financieros', [TiposMovimientosFinancieros::class, 'new']);
Flight::route('PUT /tipos-movimientos-financieros', [TiposMovimientosFinancieros::class, 'replace']);
Flight::route('DELETE /tipos-movimientos-financieros', [TiposMovimientosFinancieros::class, 'delete']);

// MEDIOS PAGO FINANCIEROS
Flight::route('GET /medios-pago-financieros', [MediosPagoFinancieros::class, 'getAll']);
Flight::route('GET /medios-pago-financieros/@id', [MediosPagoFinancieros::class, 'getById']);
Flight::route('GET /medios-pago-financieros/tipo/@id_tipo', [MediosPagoFinancieros::class, 'getByTipo']);
Flight::route('POST /medios-pago-financieros', [MediosPagoFinancieros::class, 'new']);
Flight::route('PUT /medios-pago-financieros', [MediosPagoFinancieros::class, 'replace']);
Flight::route('DELETE /medios-pago-financieros', [MediosPagoFinancieros::class, 'delete']);

// CATEGORIAS MOVIMIENTOS FINANCIEROS
Flight::route('GET /categorias-movimientos-financieros', [CategoriasMovimientosFinancieros::class, 'getAll']);
Flight::route('GET /categorias-movimientos-financieros/@id', [CategoriasMovimientosFinancieros::class, 'getById']);
Flight::route('GET /categorias-movimientos-financieros/tipo/@id_tipo', [CategoriasMovimientosFinancieros::class, 'getByTipoMovimiento']);
Flight::route('POST /categorias-movimientos-financieros', [CategoriasMovimientosFinancieros::class, 'new']);
Flight::route('PUT /categorias-movimientos-financieros', [CategoriasMovimientosFinancieros::class, 'replace']);
Flight::route('DELETE /categorias-movimientos-financieros', [CategoriasMovimientosFinancieros::class, 'delete']);

// CONCEPTOS FINANCIEROS
Flight::route('GET /conceptos-financieros', [ConceptosFinancieros::class, 'getAll']);
Flight::route('GET /conceptos-financieros/@id', [ConceptosFinancieros::class, 'getById']);
Flight::route('GET /conceptos-financieros/categoria/@id_categoria', [ConceptosFinancieros::class, 'getByCategoria']);
Flight::route('GET /conceptos-financieros/tipo/@id_tipo', [ConceptosFinancieros::class, 'getByTipoMovimiento']);
Flight::route('POST /conceptos-financieros', [ConceptosFinancieros::class, 'new']);
Flight::route('PUT /conceptos-financieros', [ConceptosFinancieros::class, 'replace']);
Flight::route('DELETE /conceptos-financieros', [ConceptosFinancieros::class, 'delete']);

// MOVIMIENTOS FINANCIEROS
Flight::route('GET /movimientos-financieros', [MovimientosFinancieros::class, 'getAll']);
Flight::route('GET /movimientos-financieros/reporte-anual/@anio', [MovimientosFinancieros::class, 'getReporteAnual']);
Flight::route('GET /movimientos-financieros/@id', [MovimientosFinancieros::class, 'getById']);
Flight::route('POST /movimientos-financieros/fechas', [MovimientosFinancieros::class, 'getByFechas']);
Flight::route('GET /movimientos-financieros-pendientes-aprobacion', [MovimientosFinancieros::class, 'getPendientesAprobacion']);
Flight::route('POST /movimientos-financieros/resumen-periodo', [MovimientosFinancieros::class, 'getResumenPeriodo']);
Flight::route('POST /movimientos-financieros/resumen-categoria', [MovimientosFinancieros::class, 'getResumenPorCategoria']);
Flight::route('POST /movimientos-financieros', [MovimientosFinancieros::class, 'new']);
Flight::route('PUT /movimientos-financieros', [MovimientosFinancieros::class, 'replace']);
Flight::route('PUT /movimientos-financieros/aprobar', [MovimientosFinancieros::class, 'aprobar']);
Flight::route('PUT /movimientos-financieros/anular', [MovimientosFinancieros::class, 'anular']);
Flight::route('POST /movimientos-financieros/aprobar-multiple', ['MovimientosFinancieros', 'aprobarMultiple']);
Flight::route('DELETE /movimientos-financieros', [MovimientosFinancieros::class, 'delete']);