<?php
// MI AGENDA
// El catalogo va primero: si no, 'GET /mi-agenda/@id_estudiante/@fecha'
// no lo captura pero si lo haria cualquier ruta de dos segmentos que se
// agregue despues. Se deja arriba por costumbre del proyecto.
Flight::route('GET /mi-agenda-fuentes', [MiAgenda::class, 'getFuentes']);
Flight::route('GET /mi-agenda/@id_estudiante/@fecha', [MiAgenda::class, 'getDia']);
