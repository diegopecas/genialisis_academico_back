<?php
// productos-servicios
Flight::route('GET /productos-servicios', [ProductosServicios::class, 'getAll']);
Flight::route('GET /productos-servicios/catalogo-disponibles', [ProductosServicios::class, 'getCatalogoDisponibles']);
Flight::route('GET /productos-servicios/@id', [ProductosServicios::class, 'getById']);
Flight::route('GET /productos-servicios/clasificacion/@id', [ProductosServicios::class, 'getByClasificacion']);
Flight::route('POST /productos-servicios', [ProductosServicios::class, 'new']);
Flight::route('PUT /productos-servicios', [ProductosServicios::class, 'replace']);
Flight::route('DELETE /productos-servicios', [ProductosServicios::class, 'delete']);

// clasificacion-productos-servicios
Flight::route('GET /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'getAll']);
Flight::route('GET /clasificacion-productos-servicios/@id', [ClasificacionProductosServicios::class, 'getById']);
Flight::route('POST /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'new']);
Flight::route('PUT /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'replace']);
Flight::route('DELETE /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'delete']);

// categoria-productos-servicios
Flight::route('GET /categoria-productos-servicios', [CategoriaProductosServicios::class, 'getAll']);
Flight::route('GET /categoria-productos-servicios/@id', [CategoriaProductosServicios::class, 'getById']);
Flight::route('POST /categoria-productos-servicios', [CategoriaProductosServicios::class, 'new']);
Flight::route('PUT /categoria-productos-servicios', [CategoriaProductosServicios::class, 'replace']);
Flight::route('DELETE /categoria-productos-servicios', [CategoriaProductosServicios::class, 'delete']);

// periodicidad-cobro
Flight::route('GET /periodicidad-cobro', [PeriodicidadCobro::class, 'getAll']);
Flight::route('GET /periodicidad-cobro/@id', [PeriodicidadCobro::class, 'getById']);
Flight::route('POST /periodicidad-cobro', [PeriodicidadCobro::class, 'new']);
Flight::route('PUT /periodicidad-cobro', [PeriodicidadCobro::class, 'replace']);
Flight::route('DELETE /periodicidad-cobro', [PeriodicidadCobro::class, 'delete']);

// horarios-alimentacion
Flight::route('GET /horarios-alimentacion', [HorariosAlimentacion::class, 'getAll']);
Flight::route('GET /horarios-alimentacion/@id', [HorariosAlimentacion::class, 'getById']);
Flight::route('GET /horarios-alimentacion/max-orden', [HorariosAlimentacion::class, 'getMaxOrden']);
Flight::route('POST /horarios-alimentacion', [HorariosAlimentacion::class, 'new']);
Flight::route('PUT /horarios-alimentacion', [HorariosAlimentacion::class, 'replace']);
Flight::route('DELETE /horarios-alimentacion', [HorariosAlimentacion::class, 'delete']);

// ONCES
Flight::route('GET /onces', [Onces::class, 'getAll']);
// ONCES-PERSONAS
Flight::route('GET /onces-personas', [OncesPersonas::class, 'getAll']);
Flight::route('GET /onces-personas/@id', [OncesPersonas::class, 'getById']);
Flight::route('GET /onces-personas-per/@id', [OncesPersonas::class, 'getByIdPersona']);
Flight::route('POST /onces-personas', [OncesPersonas::class, 'new']);