<?php

// DOCENTES
Flight::route('GET /docentes', [Docentes::class, 'getAll']);
Flight::route('GET /docentes/@id', [Docentes::class, 'getById']);
Flight::route('POST /docentes', [Docentes::class, 'new']);
Flight::route('PUT /docentes', [Docentes::class, 'replace']);
Flight::route('DELETE /docentes', [Docentes::class, 'delete']);
Flight::route('GET /docentes-x-persona/@id_persona', [Docentes::class, 'getByIdPersona']);
Flight::route('POST /docentes/verificar-duplicados', [Docentes::class, 'verificarDuplicados']);
Flight::route('GET /docentes-x-colaborador/@id_colaborador', [Docentes::class, 'getByIdColaborador']);

// TIPOS DE PUNTOS
Flight::route('GET /tipos-puntos', [TiposPuntos::class, 'getAll']);

// CASAS DOCENTES
Flight::route('GET /casas-docentes', [CasasDocentes::class, 'getAll']);
Flight::route('POST /casas-docentes-mas-puntos', [CasasDocentes::class, 'restarPuntosEntregar']);
Flight::route('POST /casas-docentes-menos-puntos', [CasasDocentes::class, 'restarPuntosQuitar']);

// PUNTOS CASAS DOCENTES
Flight::route('GET /puntos-casas-docentes', [PuntosCasasDocentes::class, 'getAll']);
Flight::route('POST /puntos-casas-docentes', [PuntosCasasDocentes::class, 'new']);
Flight::route('GET /puntos-casas-docentes/@id', [PuntosCasasDocentes::class, 'getAllByCasa']);

// DOCENTES X GRUPOS
Flight::route('GET /docentes-x-grupos', [DocentesXGrupos::class, 'getAll']);
Flight::route('GET /docentes-x-grupos/docente/@id_docente', [DocentesXGrupos::class, 'getByDocente']);
Flight::route('GET /docentes-x-grupos/grupo/@id_grupo', [DocentesXGrupos::class, 'getByGrupo']);
Flight::route('GET /docentes-x-grupos/titular/@id_grupo', [DocentesXGrupos::class, 'getTitular']);
Flight::route('POST /docentes-x-grupos', [DocentesXGrupos::class, 'new']);
Flight::route('PUT /docentes-x-grupos/titular', [DocentesXGrupos::class, 'updateTitular']);
Flight::route('PUT /docentes-x-grupos/desactivar', [DocentesXGrupos::class, 'desactivar']);
Flight::route('PUT /docentes-x-grupos/activar', [DocentesXGrupos::class, 'activar']);

// AREA ACADÉMICA X GRUPO
Flight::route('GET /area-academica-x-grupo', [AreaAcademicaXGrupo::class, 'getAll']);
Flight::route('GET /area-academica-x-grupo/grupo/@id_grupo', [AreaAcademicaXGrupo::class, 'getByGrupo']);
Flight::route('GET /area-academica-x-grupo/docente/@id_docente', [AreaAcademicaXGrupo::class, 'getByDocente']);
Flight::route('GET /area-academica-x-grupo/area/@id_area', [AreaAcademicaXGrupo::class, 'getByAreaAcademica']);
Flight::route('GET /area-academica-x-grupo/resumen-docente/@id_docente', [AreaAcademicaXGrupo::class, 'getResumenDocente']);
Flight::route('POST /area-academica-x-grupo', [AreaAcademicaXGrupo::class, 'new']);
Flight::route('PUT /area-academica-x-grupo/docente', [AreaAcademicaXGrupo::class, 'updateDocente']);
Flight::route('PUT /area-academica-x-grupo/docente-area-grupo', [AreaAcademicaXGrupo::class, 'updateDocenteByAreaGrupo']);
Flight::route('DELETE /area-academica-x-grupo', [AreaAcademicaXGrupo::class, 'delete']);
Flight::route('DELETE /area-academica-x-grupo/area-grupo', [AreaAcademicaXGrupo::class, 'deleteByAreaGrupo']);
