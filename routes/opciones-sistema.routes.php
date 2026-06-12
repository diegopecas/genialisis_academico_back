<?php
// OPCIONES DEL SISTEMA (BD Maestra - Documentación)
Flight::route('GET /opciones-sistema', [OpcionesSistema::class, 'getAll']);
Flight::route('GET /opciones-sistema/@id', [OpcionesSistema::class, 'getById']);
Flight::route('PUT /opciones-sistema/documentacion', [OpcionesSistema::class, 'updateDocumentacion']);