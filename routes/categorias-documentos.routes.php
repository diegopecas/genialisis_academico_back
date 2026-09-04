<?php
// CATEGORIAS DE DOCUMENTOS
// /activas va antes de /@id: Flight resuelve por orden de declaracion y si
// queda despues, la palabra "activas" entra como si fuera un id.
Flight::route('GET /categorias-documentos', [CategoriasDocumentos::class, 'getAll']);
Flight::route('GET /categorias-documentos/activas', [CategoriasDocumentos::class, 'getActivas']);
Flight::route('GET /categorias-documentos/@id', [CategoriasDocumentos::class, 'getById']);
Flight::route('POST /categorias-documentos', [CategoriasDocumentos::class, 'new']);
Flight::route('PUT /categorias-documentos', [CategoriasDocumentos::class, 'replace']);
Flight::route('DELETE /categorias-documentos', [CategoriasDocumentos::class, 'delete']);
