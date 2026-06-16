<?php


// CATEGORIAS-ACTIVIDADES
Flight::route('GET /esferas-desarrollo', [EsferasDesarrollo::class, 'getAll']);

// OBJETIVOS ACADEMICOS
Flight::route('GET /objetivos-academicos', [ObjetivosAcademicos::class, 'getAll']);
Flight::route('GET /objetivos-academicos/@id', [ObjetivosAcademicos::class, 'getById']);
Flight::route('POST /objetivos-academicos', [ObjetivosAcademicos::class, 'new']);
Flight::route('PUT /objetivos-academicos', [ObjetivosAcademicos::class, 'replace']);
Flight::route('DELETE /objetivos-academicos', [ObjetivosAcademicos::class, 'delete']);


Flight::route('GET /competencias-cognitivas', [CompetenciasCognitivas::class, 'getAll']);

Flight::route('GET /ejes-curriculares', [EjesCurriculares::class, 'getAll']);

Flight::route('GET /estandares-basicos', [EstandaresBasicos::class, 'getAll']);


// CORTES-ACADEMICOS
Flight::route('GET /cortes-academicos', [CortesAcademicos::class, 'getAll']);
Flight::route('GET /cortes-academicos/@id', [CortesAcademicos::class, 'getById']);
Flight::route('POST /cortes-academicos', [CortesAcademicos::class, 'new']);
Flight::route('PUT /cortes-academicos', [CortesAcademicos::class, 'replace']);
Flight::route('DELETE /cortes-academicos', [CortesAcademicos::class, 'delete']);

// HORARIOS
Flight::route('GET /horarios', [Horarios::class, 'getAll']);
Flight::route('GET /horarios/@id', [Horarios::class, 'getById']);
Flight::route('GET /horarios/grupo/@id_grupo', [Horarios::class, 'getByGrupo']);
Flight::route('GET /horarios/area/@id_area_academica', [Horarios::class, 'getByArea']);
Flight::route('POST /horarios', [Horarios::class, 'new']);
Flight::route('PUT /horarios', [Horarios::class, 'replace']);
Flight::route('DELETE /horarios', [Horarios::class, 'delete']);

// DIAS SEMANA
Flight::route('GET /dias-semana', [DiasSemana::class, 'getAll']);