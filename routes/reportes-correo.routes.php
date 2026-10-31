<?php
// Reportes por correo a colaboradores (ver MotorReportesCorreo)
Flight::route('GET /tipos-reporte-correo', [TiposReporteCorreo::class, 'getAll']);

Flight::route('GET /reportes-correo-colaboradores', [ReportesCorreoColaboradores::class, 'getAll']);
Flight::route('GET /reportes-correo-colaboradores/colaborador/@id', [ReportesCorreoColaboradores::class, 'getByColaborador']);
Flight::route('GET /reportes-correo-colaboradores/@id', [ReportesCorreoColaboradores::class, 'getById']);
Flight::route('POST /reportes-correo-colaboradores/enviar', [ReportesCorreoColaboradores::class, 'enviarAhora']);
Flight::route('POST /reportes-correo-colaboradores/asignar', [ReportesCorreoColaboradores::class, 'asignar']);
Flight::route('POST /reportes-correo-colaboradores', [ReportesCorreoColaboradores::class, 'new']);
Flight::route('PUT /reportes-correo-colaboradores', [ReportesCorreoColaboradores::class, 'replace']);
Flight::route('DELETE /reportes-correo-colaboradores', [ReportesCorreoColaboradores::class, 'delete']);

Flight::route('GET /historial-reportes-correo', [HistorialReportesCorreo::class, 'getAll']);
