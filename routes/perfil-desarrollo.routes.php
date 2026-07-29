<?php

// PDE: RANGOS DE EDAD
Flight::route('GET /pde-rangos-edad', [PdeRangosEdad::class, 'getAll']);
Flight::route('GET /pde-rangos-edad/list', [PdeRangosEdad::class, 'getAllList']);
Flight::route('GET /pde-rangos-edad/edad/@edad_meses', [PdeRangosEdad::class, 'getByEdad']);
Flight::route('GET /pde-rangos-edad/@id', [PdeRangosEdad::class, 'getById']);
Flight::route('POST /pde-rangos-edad', [PdeRangosEdad::class, 'new']);
Flight::route('PUT /pde-rangos-edad', [PdeRangosEdad::class, 'replace']);
Flight::route('DELETE /pde-rangos-edad', [PdeRangosEdad::class, 'delete']);

// PDE: ITEMS
Flight::route('GET /pde-items', [PdeItems::class, 'getAll']);
Flight::route('GET /pde-items/esferas', [PdeItems::class, 'getEsferasConItems']);
Flight::route('GET /pde-items/rango/@id_rango', [PdeItems::class, 'getByRango']);
Flight::route('GET /pde-items/esfera/@id_esfera', [PdeItems::class, 'getByEsfera']);
Flight::route('GET /pde-items/rango/@id_rango/esfera/@id_esfera', [PdeItems::class, 'getByRangoEsfera']);
Flight::route('GET /pde-items/@id', [PdeItems::class, 'getById']);
Flight::route('POST /pde-items', [PdeItems::class, 'new']);
Flight::route('PUT /pde-items', [PdeItems::class, 'replace']);
Flight::route('DELETE /pde-items', [PdeItems::class, 'delete']);

// PDE: CONFIGURACION
Flight::route('GET /pde-configuracion', [PdeConfiguracion::class, 'getAll']);
Flight::route('GET /pde-configuracion/@id', [PdeConfiguracion::class, 'getById']);
Flight::route('POST /pde-configuracion', [PdeConfiguracion::class, 'new']);
Flight::route('PUT /pde-configuracion', [PdeConfiguracion::class, 'replace']);

// PDE: APLICACIONES
Flight::route('GET /pde-aplicaciones', [PdeAplicaciones::class, 'getAll']);
Flight::route('GET /pde-aplicaciones/listado-estudiantes', [PdeAplicaciones::class, 'getListadoEstudiantes']);
Flight::route('GET /pde-aplicaciones/calcular-edad/@id_estudiante', [PdeAplicaciones::class, 'calcularEdad']);
Flight::route('GET /pde-aplicaciones/resumen-asumidos/@id_rango_inicio', [PdeAplicaciones::class, 'getResumenAsumidos']);
Flight::route('GET /pde-aplicaciones/estudiante/@id_estudiante', [PdeAplicaciones::class, 'getByEstudiante']);
Flight::route('GET /pde-aplicaciones/@id/retomar', [PdeAplicaciones::class, 'getParaRetomar']);
Flight::route('GET /pde-aplicaciones/@id', [PdeAplicaciones::class, 'getById']);
Flight::route('POST /pde-aplicaciones/iniciar', [PdeAplicaciones::class, 'iniciar']);
Flight::route('PUT /pde-aplicaciones/guardar-rango', [PdeAplicaciones::class, 'guardarRango']);
Flight::route('PUT /pde-aplicaciones/finalizar', [PdeAplicaciones::class, 'finalizar']);
Flight::route('PUT /pde-aplicaciones/anular', [PdeAplicaciones::class, 'anular']);
Flight::route('PUT /pde-aplicaciones/observaciones', [PdeAplicaciones::class, 'actualizarObservaciones']);
Flight::route('PUT /pde-aplicaciones/analisis', [PdeAplicaciones::class, 'actualizarAnalisis']);

// PDE: RESULTADOS POR ESFERA
Flight::route('GET /pde-aplicaciones-esferas/aplicacion/@id_aplicacion', [PdeAplicacionesEsferas::class, 'getByAplicacion']);
Flight::route('GET /pde-aplicaciones-esferas/historial/@id_estudiante', [PdeAplicacionesEsferas::class, 'getHistorialEstudiante']);
Flight::route('GET /pde-aplicaciones-esferas/@id', [PdeAplicacionesEsferas::class, 'getById']);

// PDE: DETALLE ITEM POR ITEM
Flight::route('GET /pde-aplicaciones-detalle/aplicacion/@id_aplicacion', [PdeAplicacionesDetalle::class, 'getByAplicacion']);
Flight::route('GET /pde-aplicaciones-detalle/aplicados/@id_aplicacion', [PdeAplicacionesDetalle::class, 'getAplicadosByAplicacion']);
Flight::route('GET /pde-aplicaciones-detalle/@id', [PdeAplicacionesDetalle::class, 'getById']);
