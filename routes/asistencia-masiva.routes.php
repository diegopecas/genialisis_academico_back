<?php
// REGISTRO MASIVO DE ASISTENCIA
Flight::route('POST /asistencia-masiva/candidatos', [AsistenciaMasiva::class, 'getCandidatos']); // estudiantes que se pueden ingresar o sacar en una fecha
Flight::route('POST /asistencia-masiva/procesar', [AsistenciaMasiva::class, 'procesar']);        // procesa el lote de ingresos o de salidas
