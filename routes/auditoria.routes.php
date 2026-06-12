<?php
Flight::route('GET /auditoria/resumen-completo', [Auditoria::class, 'getResumenCompleto']);
Flight::route('GET /auditoria/detalle-medidas', [Auditoria::class, 'getDetalleMedidas']);
Flight::route('GET /auditoria/detalle-asistencia', [Auditoria::class, 'getDetalleAsistencia']);
Flight::route('GET /auditoria/detalle-clases', [Auditoria::class, 'getDetalleClases']);