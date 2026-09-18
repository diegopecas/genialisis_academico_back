<?php

// Certificados expedidos (especificas primero)
Flight::route('GET /certificados-expedidos/disponibles/@idEstudiante/@origen', [CertificadosExpedidos::class, 'getDisponibles']);
Flight::route('GET /certificados-expedidos/estudiante/@idEstudiante', [CertificadosExpedidos::class, 'getByEstudiante']);
Flight::route('GET /certificados-expedidos/anios/@idEstudiante', [CertificadosExpedidos::class, 'getAnios']);
Flight::route('GET /certificados-expedidos/compartidos/@idEstudiante', [CertificadosExpedidos::class, 'getCompartidos']);
Flight::route('GET /certificados-expedidos/@id', [CertificadosExpedidos::class, 'getById']);
Flight::route('POST /certificados-expedidos', [CertificadosExpedidos::class, 'new']);
Flight::route('PUT /certificados-expedidos/compartir', [CertificadosExpedidos::class, 'compartir']);
