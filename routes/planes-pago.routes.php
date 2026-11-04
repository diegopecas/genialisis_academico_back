<?php
// PLANES DE PAGO EN CUOTAS (con token)
Flight::route('GET /planes-pago/solicitud/@id_solicitud', [PlanesPago::class, 'getBySolicitud']);
