<?php

// Configuracion de certificados (especificas primero)
Flight::route('GET /certificados-configuracion/clave/@clave', [CertificadosConfiguracion::class, 'getByClave']);
Flight::route('GET /certificados-configuracion', [CertificadosConfiguracion::class, 'getAll']);
Flight::route('PUT /certificados-configuracion', [CertificadosConfiguracion::class, 'replace']);
