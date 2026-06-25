<?php
// =====================================================
// RUTAS DE INSTAGRAM
// Requieren tenant + JWT (la autenticación central de index.php las cubre).
// La ruta pública /ig-media/{tenant}/{file} se maneja aparte en index.php.
// =====================================================

Flight::route('GET /instagram/estado', [Instagram::class, 'getEstado']);
Flight::route('POST /instagram/publicar', [Instagram::class, 'publicar']);
Flight::route('POST /instagram/refrescar-token', [Instagram::class, 'refrescarTokenManual']);