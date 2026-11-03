<?php
// Reporte de ejecución del horario por grupo en un rango de fechas
Flight::route('GET /ejecucion-horario-grupo', [EjecucionHorarioGrupo::class, 'get']);
