<?php
Flight::route('POST /alimentacion/fecha', [Alimentacion::class, 'getByFecha']);

// Rutas para el servicio de HorariosAlimentacion
Flight::route('GET /horarios-alimentacion', [HorariosAlimentacion::class, 'getAll']);
Flight::route('GET /horarios-alimentacion/@id', [HorariosAlimentacion::class, 'getById']);
Flight::route('POST /horarios-alimentacion', [HorariosAlimentacion::class, 'new']);
Flight::route('PUT /horarios-alimentacion', [HorariosAlimentacion::class, 'replace']);
Flight::route('DELETE /horarios-alimentacion', [HorariosAlimentacion::class, 'delete']);
Flight::route('GET /horarios-alimentacion/max-orden', [HorariosAlimentacion::class, 'getMaxOrden']);

// Rutas para CocinaDisponibilidad
Flight::route('POST /cocina-disponibilidad/productos', [CocinaDisponibilidad::class, 'getProductosPorFecha']);
Flight::route('POST /cocina-disponibilidad/guardar-batch', [CocinaDisponibilidad::class, 'guardarBatch']);

// Rutas para AsignacionOnces
Flight::route('POST /asignacion-onces/estudiantes-del-dia', [AsignacionOnces::class, 'getEstudiantesDelDia']);
Flight::route('POST /asignacion-onces/asignaciones-del-dia', [AsignacionOnces::class, 'getAsignacionesDelDia']);
Flight::route('POST /asignacion-onces/crear-batch', [AsignacionOnces::class, 'crearBatch']);

// Rutas para EntregaAlimentacion
Flight::route('POST /entrega-alimentacion/por-fecha', [EntregaAlimentacion::class, 'getPorFecha']);
Flight::route('POST /entrega-alimentacion/registrar-batch', [EntregaAlimentacion::class, 'registrarBatch']);
Flight::route('POST /entrega-alimentacion/anular-batch', [EntregaAlimentacion::class, 'anularBatch']);
Flight::route('POST /entrega-alimentacion/calcular-inventario', [EntregaAlimentacion::class, 'calcularInventario']);
Flight::route('POST /entrega-alimentacion/registrar-con-inventario', [EntregaAlimentacion::class, 'registrarConInventario']);
Flight::route('GET /entrega-alimentacion/menus-del-dia', [EntregaAlimentacion::class, 'getMenusDelDia']);
Flight::route('POST /entrega-alimentacion/entregadas-para-inventario', [EntregaAlimentacion::class, 'getEntregadasParaInventario']);
Flight::route('POST /entrega-alimentacion/movimientos-del-dia', [EntregaAlimentacion::class, 'getMovimientosDelDia']);