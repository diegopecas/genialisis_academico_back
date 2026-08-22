<?php
// JORNADA LABORAL (horario de atencion del jardin por dia)
Flight::route('GET /jornada-laboral', [JornadaLaboral::class, 'getAll']);
Flight::route('GET /jornada-laboral-vigente', [JornadaLaboral::class, 'getVigente']);
Flight::route('GET /jornada-laboral-fecha/@fecha', [JornadaLaboral::class, 'getHorasFecha']);
Flight::route('GET /jornada-laboral/@id', [JornadaLaboral::class, 'getById']);
Flight::route('POST /jornada-laboral', [JornadaLaboral::class, 'new']);
Flight::route('PUT /jornada-laboral', [JornadaLaboral::class, 'replace']);
Flight::route('DELETE /jornada-laboral', [JornadaLaboral::class, 'delete']);
