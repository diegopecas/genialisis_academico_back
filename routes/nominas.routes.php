<?php
// =====================================================
// RUTAS PARA MÓDULO DE NÓMINAS
// =====================================================

// =====================================================
// RUTAS PARA NÓMINAS
// =====================================================
Flight::route('GET /nominas', [Nominas::class, 'getAll']);
Flight::route('GET /nominas/@id', [Nominas::class, 'getById']);
Flight::route('GET /nominas-activas', [Nominas::class, 'getActivas']);
Flight::route('POST /nominas', [Nominas::class, 'new']);
Flight::route('PUT /nominas', [Nominas::class, 'replace']);
Flight::route('PUT /nominas-cerrar', [Nominas::class, 'cerrar']);
Flight::route('PUT /nominas-marcar-pagada', [Nominas::class, 'marcarPagada']);
Flight::route('DELETE /nominas', [Nominas::class, 'delete']);

// =====================================================
// NUEVAS RUTAS PARA PROCESAMIENTO DE NÓMINA
// =====================================================
Flight::route('POST /nominas/calcular', [NominasCalculo::class, 'calcular']);
Flight::route('POST /nominas/procesar', [NominasCalculo::class, 'procesar']);

// =====================================================
// RUTAS PARA DETALLE DE NÓMINAS
// =====================================================
Flight::route('GET /nominas-detalle/@id', [NominasDetalle::class, 'getById']);
Flight::route('GET /nominas-detalle/nomina/@id_nomina', [NominasDetalle::class, 'getByNomina']);
Flight::route('GET /nominas-detalle/nomina/@id_nomina/agrupado', [NominasDetalle::class, 'getAgrupadoPorColaborador']);
Flight::route('GET /nominas-detalle/nomina/@id_nomina/colaborador/@id_colaborador', [NominasDetalle::class, 'getByColaborador']);
Flight::route('POST /nominas-detalle', [NominasDetalle::class, 'new']);
Flight::route('POST /nominas-detalle/multiple', [NominasDetalle::class, 'newMultiple']);
Flight::route('PUT /nominas-detalle', [NominasDetalle::class, 'replace']);
Flight::route('DELETE /nominas-detalle', [NominasDetalle::class, 'delete']);
Flight::route('DELETE /nominas-detalle/nomina', [NominasDetalle::class, 'deleteByNomina']);

// =====================================================
// RUTAS PARA CONCEPTOS DE NÓMINA
// =====================================================
Flight::route('GET /conceptos-nomina', [ConceptosNomina::class, 'getAll']);
Flight::route('GET /conceptos-nomina/@id', [ConceptosNomina::class, 'getById']);
Flight::route('GET /conceptos-nomina-activos', [ConceptosNomina::class, 'getActivos']);
Flight::route('POST /conceptos-nomina', [ConceptosNomina::class, 'new']);
Flight::route('PUT /conceptos-nomina', [ConceptosNomina::class, 'replace']);
Flight::route('DELETE /conceptos-nomina', [ConceptosNomina::class, 'delete']);

// =====================================================
// RUTAS PARA ESTADOS DE NÓMINA
// =====================================================
Flight::route('GET /estados-nomina', [EstadosNomina::class, 'getAll']);
Flight::route('GET /estados-nomina/@id', [EstadosNomina::class, 'getById']);
Flight::route('GET /estados-nomina-activos', [EstadosNomina::class, 'getActivos']);
Flight::route('POST /estados-nomina', [EstadosNomina::class, 'new']);
Flight::route('PUT /estados-nomina', [EstadosNomina::class, 'replace']);
Flight::route('DELETE /estados-nomina', [EstadosNomina::class, 'delete']);

// =====================================================
// RUTAS PARA CONFIGURACIÓN DE NÓMINA
// =====================================================
Flight::route('GET /nomina-configuracion', [NominaConfiguracion::class, 'getAll']);
Flight::route('GET /nomina-configuracion/@id', [NominaConfiguracion::class, 'getById']);
Flight::route('GET /nomina-configuracion-activas', [NominaConfiguracion::class, 'getActivas']);
Flight::route('GET /nomina-configuracion/anio/@anio', [NominaConfiguracion::class, 'getByAnio']);
Flight::route('GET /nomina-configuracion/codigo/@codigo/@anio', [NominaConfiguracion::class, 'getByCodigo']);
Flight::route('POST /nomina-configuracion', [NominaConfiguracion::class, 'new']);
Flight::route('PUT /nomina-configuracion', [NominaConfiguracion::class, 'replace']);
Flight::route('DELETE /nomina-configuracion', [NominaConfiguracion::class, 'delete']);