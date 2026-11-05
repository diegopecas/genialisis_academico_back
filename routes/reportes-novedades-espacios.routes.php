<?php
// REPORTES DE NOVEDADES DE ESPACIOS (historial de lo que se le manda al club)
Flight::route('GET /reportes-novedades-espacios', [ReportesNovedadesEspacios::class, 'getAll']);
Flight::route('GET /reportes-novedades-espacios/@id', [ReportesNovedadesEspacios::class, 'getById']);
Flight::route('POST /reportes-novedades-espacios', [ReportesNovedadesEspacios::class, 'generar']);
