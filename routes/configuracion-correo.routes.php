<?php
// SERVICIO DE CORREO DEL TENANT (Datos maestros > Configuracion)
Flight::route('GET /configuracion-correo', [ConfiguracionCorreo::class, 'getByTenant']);
Flight::route('PUT /configuracion-correo', [ConfiguracionCorreo::class, 'replace']);
Flight::route('POST /configuracion-correo/probar', [ConfiguracionCorreo::class, 'probar']);
