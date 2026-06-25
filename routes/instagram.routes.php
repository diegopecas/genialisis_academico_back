<?php
// =====================================================
// RUTAS DE INSTAGRAM
// Requieren tenant + JWT (la autenticación central de index.php las cubre).
// La ruta pública /ig-media/{tenant}/{file} se maneja aparte en index.php.
// =====================================================

Flight::route('GET /instagram/estado', [Instagram::class, 'getEstado']);
Flight::route('GET /instagram/imagenes-publicadas/@id_galeria', [Instagram::class, 'imagenesPublicadas']);
Flight::route('POST /instagram/publicar', [Instagram::class, 'publicar']);
Flight::route('POST /instagram/publicar-historia', [Instagram::class, 'publicarHistoria']);
Flight::route('POST /instagram/refrescar-token', [Instagram::class, 'refrescarTokenManual']);