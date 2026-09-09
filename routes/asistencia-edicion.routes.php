<?php
// EDICION DE REGISTROS DE ASISTENCIA
Flight::route('POST /asistencia-edicion/listado', [AsistenciaEdicion::class, 'getListado']);            // movimientos de una fecha
Flight::route('GET /asistencia-edicion/@id', [AsistenciaEdicion::class, 'getById']);                    // detalle con utiles y cobros
Flight::route('PUT /asistencia-edicion', [AsistenciaEdicion::class, 'replace']);                        // guarda la correccion
Flight::route('POST /asistencia-edicion/notificar', [AsistenciaEdicion::class, 'notificarCorreccion']); // avisa al acudiente despues de corregir
Flight::route('DELETE /asistencia-edicion', [AsistenciaEdicion::class, 'delete']);                      // elimina el movimiento y lo que arrastra
