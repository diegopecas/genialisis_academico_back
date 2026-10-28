<?php
// PASARELA DE PAGOS DEL TENANT (Datos maestros > Configuracion)
Flight::route('GET /configuracion-pasarela-pago', [ConfiguracionPasarelaPago::class, 'getByTenant']);
Flight::route('PUT /configuracion-pasarela-pago', [ConfiguracionPasarelaPago::class, 'replace']);
