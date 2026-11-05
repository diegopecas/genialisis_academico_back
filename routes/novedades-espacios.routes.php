<?php
// NOVEDADES DE ESPACIOS (Operaciones > Apoyo)
Flight::route('GET /novedades-espacios', [NovedadesEspacios::class, 'getAll']); // ?desde=&hasta=&estado=&id_lugar=
Flight::route('PUT /novedades-espacios/cruzar', [NovedadesEspacios::class, 'cruzar']);
Flight::route('GET /novedades-espacios/@id', [NovedadesEspacios::class, 'getById']);
Flight::route('POST /novedades-espacios', [NovedadesEspacios::class, 'new']);
Flight::route('PUT /novedades-espacios', [NovedadesEspacios::class, 'replace']);
Flight::route('DELETE /novedades-espacios', [NovedadesEspacios::class, 'delete']);
