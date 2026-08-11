<?php
// TIPOS DE MORA (catalogo global, solo lectura)
Flight::route('GET /tipos-mora', [TiposMora::class, 'getAll']);
Flight::route('GET /tipos-mora/@id', [TiposMora::class, 'getById']);

// CONFIGURACION DE MORA POR PRODUCTO
Flight::route('GET /mora-configuracion', [MoraConfiguracion::class, 'getAll']);
Flight::route('GET /mora-configuracion/productos', [MoraConfiguracion::class, 'getProductosConMora']);
Flight::route('GET /mora-configuracion/producto/@id_producto_servicio', [MoraConfiguracion::class, 'getByProducto']);
Flight::route('GET /mora-configuracion/@id', [MoraConfiguracion::class, 'getById']);
Flight::route('POST /mora-configuracion', [MoraConfiguracion::class, 'new']);
Flight::route('PUT /mora-configuracion', [MoraConfiguracion::class, 'replace']);
Flight::route('DELETE /mora-configuracion', [MoraConfiguracion::class, 'delete']);
Flight::route('POST /mora-configuracion/masivo', [MoraConfiguracion::class, 'aplicarMasivo']);

// EXENCIONES
Flight::route('GET /mora-exenciones', [MoraExenciones::class, 'getAll']);
Flight::route('GET /mora-exenciones/personas', [MoraExenciones::class, 'getPersonasParaExencion']);
Flight::route('GET /mora-exenciones/persona/@id_persona', [MoraExenciones::class, 'getByPersona']);
Flight::route('GET /mora-exenciones/@id', [MoraExenciones::class, 'getById']);
Flight::route('POST /mora-exenciones', [MoraExenciones::class, 'new']);
Flight::route('PUT /mora-exenciones', [MoraExenciones::class, 'replace']);
Flight::route('DELETE /mora-exenciones', [MoraExenciones::class, 'delete']);
Flight::route('POST /mora-exenciones/masivo', [MoraExenciones::class, 'aplicarMasivo']);

// CAUSACIONES (solo lectura: las escribe el motor)
Flight::route('GET /mora-causaciones', [MoraCausaciones::class, 'getAll']);
Flight::route('GET /mora-causaciones/cuenta/@id_cuenta_por_cobrar', [MoraCausaciones::class, 'getByCuentaPorCobrar']);
Flight::route('GET /mora-causaciones/persona/@id_persona', [MoraCausaciones::class, 'getByPersona']);
Flight::route('GET /mora-causaciones/@id', [MoraCausaciones::class, 'getById']);

// BITACORA DE EJECUCIONES
Flight::route('GET /mora-ejecuciones', [MoraEjecuciones::class, 'getAll']);
Flight::route('GET /mora-ejecuciones/estado', [MoraEjecuciones::class, 'getEstado']);
Flight::route('GET /mora-ejecuciones/@id', [MoraEjecuciones::class, 'getById']);

// MOTOR
Flight::route('POST /mora/liquidar', [MotorMora::class, 'liquidar']);
Flight::route('POST /mora/liquidar-si-hace-falta', [MotorMora::class, 'liquidarSiHaceFalta']);
Flight::route('GET /mora/simular/persona/@id_persona', [MotorMora::class, 'simularPorPersona']);
