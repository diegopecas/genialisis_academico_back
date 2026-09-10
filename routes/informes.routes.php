<?php
// =====================================================================
// INFORMES DE CALIFICACIONES - CONFIGURACIÓN
// Se carga solo con el glob de index.php, no hay que registrarlo.
// =====================================================================

// CONFIGURACIÓN GENERAL DEL INFORME
Flight::route('GET /informes-configuracion', [InformesConfiguracion::class, 'getAll']);
Flight::route('GET /informes-configuracion/@id', [InformesConfiguracion::class, 'getById']);
Flight::route('POST /informes-configuracion', [InformesConfiguracion::class, 'new']);
Flight::route('PUT /informes-configuracion', [InformesConfiguracion::class, 'replace']);
Flight::route('DELETE /informes-configuracion', [InformesConfiguracion::class, 'delete']);

// SECCIONES DEL INFORME
Flight::route('GET /informes-secciones', [InformesSecciones::class, 'getAll']);
Flight::route('GET /informes-secciones/posibles-padres', [InformesSecciones::class, 'getPosiblesPadres']);
Flight::route('GET /informes-secciones/@id', [InformesSecciones::class, 'getById']);
Flight::route('POST /informes-secciones', [InformesSecciones::class, 'new']);
Flight::route('PUT /informes-secciones', [InformesSecciones::class, 'replace']);
Flight::route('DELETE /informes-secciones', [InformesSecciones::class, 'delete']);

// ÍTEMS PROPIOS DEL INFORME
Flight::route('GET /informes-items', [InformesItems::class, 'getAll']);
Flight::route('GET /informes-items/seccion/@id_seccion', [InformesItems::class, 'getBySeccion']);
Flight::route('GET /informes-items/@id', [InformesItems::class, 'getById']);
Flight::route('POST /informes-items', [InformesItems::class, 'new']);
Flight::route('PUT /informes-items', [InformesItems::class, 'replace']);
Flight::route('DELETE /informes-items', [InformesItems::class, 'delete']);
