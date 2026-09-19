<?php
// TIPOS DE CURSO EXTRACURRICULAR
// /activos va antes de /@id: Flight resuelve por orden de declaracion y si
// queda despues, la palabra "activos" entra como si fuera un id.
Flight::route('GET /tipos-cursos-extracurriculares', [TiposCursosExtracurriculares::class, 'getAll']);
Flight::route('GET /tipos-cursos-extracurriculares/activos', [TiposCursosExtracurriculares::class, 'getActivos']);
Flight::route('GET /tipos-cursos-extracurriculares/@id', [TiposCursosExtracurriculares::class, 'getById']);
Flight::route('POST /tipos-cursos-extracurriculares', [TiposCursosExtracurriculares::class, 'new']);
Flight::route('PUT /tipos-cursos-extracurriculares', [TiposCursosExtracurriculares::class, 'replace']);
Flight::route('DELETE /tipos-cursos-extracurriculares', [TiposCursosExtracurriculares::class, 'delete']);
