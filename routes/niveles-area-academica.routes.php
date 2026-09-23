<?php
// NIVELES POR AREA ACADEMICA
// Las rutas con palabra fija van antes de /@id: Flight resuelve por orden de
// declaracion y si quedan despues, la palabra entra como si fuera un id.
Flight::route('GET /niveles-area-academica/area/@id_area_academica', [NivelesAreaAcademica::class, 'getByArea']);
Flight::route('GET /niveles-area-academica/area/@id_area_academica/activos', [NivelesAreaAcademica::class, 'getActivosByArea']);
Flight::route('GET /niveles-area-academica/curso-extra/@id_curso_extra', [NivelesAreaAcademica::class, 'getByCursoExtra']);
Flight::route('GET /niveles-area-academica/@id', [NivelesAreaAcademica::class, 'getById']);
Flight::route('POST /niveles-area-academica', [NivelesAreaAcademica::class, 'new']);
Flight::route('PUT /niveles-area-academica', [NivelesAreaAcademica::class, 'replace']);
Flight::route('DELETE /niveles-area-academica', [NivelesAreaAcademica::class, 'delete']);
