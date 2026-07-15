<?php
// =====================================================
// RUTAS DE GALERÍAS
// =====================================================

// GALERÍAS
Flight::route('GET /galerias', [Galerias::class, 'getAll']);
Flight::route('GET /galerias/activas', [Galerias::class, 'getActivas']);
Flight::route('GET /galerias/@id', [Galerias::class, 'getById']);
Flight::route('GET /galerias/acudiente/@id_persona', [Galerias::class, 'getByAcudiente']);
Flight::route('GET /galerias/usuario/@id_persona/@id_docente', [Galerias::class, 'getByUsuarioPortal']);
Flight::route('GET /galerias/full/@id_galeria/@id_persona', [Galerias::class, 'getFullByIdForAcudiente']);
Flight::route('GET /galerias/full/@id_galeria/@id_persona/@id_docente', [Galerias::class, 'getFullByIdForUsuario']);
Flight::route('POST /galerias', [Galerias::class, 'new']);
Flight::route('PUT /galerias', [Galerias::class, 'replace']);
Flight::route('DELETE /galerias', [Galerias::class, 'delete']);

// SUBGALERÍAS
Flight::route('GET /subgalerias', [Subgalerias::class, 'getAll']);
Flight::route('GET /subgalerias/@id', [Subgalerias::class, 'getById']);
Flight::route('GET /subgalerias/galeria/@id_galeria', [Subgalerias::class, 'getByGaleria']);
Flight::route('POST /subgalerias', [Subgalerias::class, 'new']);
Flight::route('PUT /subgalerias', [Subgalerias::class, 'replace']);
Flight::route('DELETE /subgalerias', [Subgalerias::class, 'delete']);

// IMÁGENES DE GALERÍA
Flight::route('GET /galeria-imagenes', [GaleriaImagenes::class, 'getAll']);
// OJO: debe ir ANTES de 'GET /galeria-imagenes/@id', si no getById la captura
// y recibe 'token' como id.
Flight::route('GET /galeria-imagenes/token', [GaleriaImagenes::class, 'generarTokenImagenes']);
Flight::route('GET /galeria-imagenes/@id', [GaleriaImagenes::class, 'getById']);
Flight::route('GET /galeria-imagenes/galeria/@id_galeria', [GaleriaImagenes::class, 'getByGaleria']);
Flight::route('GET /galeria-imagenes/subgaleria/@id_subgaleria', [GaleriaImagenes::class, 'getBySubgaleria']);
Flight::route('GET /galeria-imagenes/generales/@id_galeria', [GaleriaImagenes::class, 'getGeneralesByGaleria']);
Flight::route('GET /galeria-imagenes/servir/@id', [GaleriaImagenes::class, 'servirImagen']);
Flight::route('POST /galeria-imagenes', [GaleriaImagenes::class, 'new']);
Flight::route('POST /galeria-imagenes/bulk', [GaleriaImagenes::class, 'newBulk']);
Flight::route('PUT /galeria-imagenes', [GaleriaImagenes::class, 'replace']);
Flight::route('DELETE /galeria-imagenes', [GaleriaImagenes::class, 'delete']);
Flight::route('DELETE /galeria-imagenes/bulk', [GaleriaImagenes::class, 'deleteBulk']);
Flight::route('DELETE /galeria-imagenes/galeria/@id_galeria', [GaleriaImagenes::class, 'deleteByGaleria']);
Flight::route('DELETE /galeria-imagenes/subgaleria/@id_subgaleria', [GaleriaImagenes::class, 'deleteBySubgaleria']);

// GALERÍAS X GRUPOS (relación)
Flight::route('GET /galerias-x-grupos', [GaleriasXGrupos::class, 'getAll']);
Flight::route('GET /galerias-x-grupos/galeria/@id_galeria', [GaleriasXGrupos::class, 'getByGaleria']);
Flight::route('GET /galerias-x-grupos/grupo/@id_grupo', [GaleriasXGrupos::class, 'getByGrupo']);
Flight::route('POST /galerias-x-grupos', [GaleriasXGrupos::class, 'new']);
Flight::route('POST /galerias-x-grupos/assign', [GaleriasXGrupos::class, 'assignGrupos']);
Flight::route('DELETE /galerias-x-grupos', [GaleriasXGrupos::class, 'delete']);
Flight::route('DELETE /galerias-x-grupos/galeria/@id_galeria', [GaleriasXGrupos::class, 'deleteByGaleria']);

// UPLOAD DE IMÁGENES DE GALERÍAS
Flight::route('POST /upload/galeria-imagen', [Upload::class, 'uploadGaleriaImagen']);