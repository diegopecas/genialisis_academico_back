<?php
// LUGARES DE CURSOS EXTRACURRICULARES
// /activos va antes de /@id: Flight resuelve por orden de declaracion y si
// queda despues, la palabra "activos" entra como si fuera un id.
Flight::route('GET /lugares-cursos-extra', [LugaresCursosExtra::class, 'getAll']);
Flight::route('GET /lugares-cursos-extra/activos', [LugaresCursosExtra::class, 'getActivos']);
Flight::route('GET /lugares-cursos-extra/@id', [LugaresCursosExtra::class, 'getById']);
Flight::route('POST /lugares-cursos-extra', [LugaresCursosExtra::class, 'new']);
Flight::route('PUT /lugares-cursos-extra', [LugaresCursosExtra::class, 'replace']);
Flight::route('DELETE /lugares-cursos-extra', [LugaresCursosExtra::class, 'delete']);
