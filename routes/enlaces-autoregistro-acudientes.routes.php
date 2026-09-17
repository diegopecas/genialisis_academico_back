<?php
// ENLACES DE AUTOREGISTRO DE ACUDIENTES (portal institucional)
Flight::route('GET /enlaces-autoregistro-acudientes', [EnlacesAutoregistroAcudientes::class, 'getAll']);
Flight::route('GET /enlaces-autoregistro-acudientes/@id', [EnlacesAutoregistroAcudientes::class, 'getById']);
Flight::route('POST /enlaces-autoregistro-acudientes/estudiantes-por-grupos', [EnlacesAutoregistroAcudientes::class, 'getEstudiantesPorGrupos']);
Flight::route('POST /enlaces-autoregistro-acudientes', [EnlacesAutoregistroAcudientes::class, 'new']);
Flight::route('PUT /enlaces-autoregistro-acudientes', [EnlacesAutoregistroAcudientes::class, 'replace']);
Flight::route('DELETE /enlaces-autoregistro-acudientes', [EnlacesAutoregistroAcudientes::class, 'delete']);

// ESTUDIANTES INCLUIDOS EN CADA ENLACE
Flight::route('GET /enlaces-autoregistro-estudiantes', [EnlacesAutoregistroEstudiantes::class, 'getAll']);
Flight::route('GET /enlaces-autoregistro-estudiantes/@id', [EnlacesAutoregistroEstudiantes::class, 'getById']);
Flight::route('GET /enlaces-autoregistro-estudiantes-enlace/@id_enlace', [EnlacesAutoregistroEstudiantes::class, 'getByEnlace']);
Flight::route('POST /enlaces-autoregistro-estudiantes', [EnlacesAutoregistroEstudiantes::class, 'new']);
Flight::route('PUT /enlaces-autoregistro-estudiantes-enlace', [EnlacesAutoregistroEstudiantes::class, 'replaceEstudiantesEnlace']);
Flight::route('DELETE /enlaces-autoregistro-estudiantes', [EnlacesAutoregistroEstudiantes::class, 'delete']);

// INTENTOS (seguimiento)
Flight::route('GET /enlaces-autoregistro-intentos', [EnlacesAutoregistroIntentos::class, 'getAll']);
Flight::route('GET /enlaces-autoregistro-intentos/@id', [EnlacesAutoregistroIntentos::class, 'getById']);
Flight::route('GET /enlaces-autoregistro-intentos-enlace/@id_enlace', [EnlacesAutoregistroIntentos::class, 'getByEnlace']);

// ESTUDIANTES VINCULADOS EN CADA INTENTO
Flight::route('GET /enlaces-autoregistro-vinculos', [EnlacesAutoregistroVinculos::class, 'getAll']);
Flight::route('GET /enlaces-autoregistro-vinculos/@id', [EnlacesAutoregistroVinculos::class, 'getById']);
Flight::route('GET /enlaces-autoregistro-vinculos-intento/@id_intento', [EnlacesAutoregistroVinculos::class, 'getByIntento']);

// PAGINA PUBLICA DEL PORTAL DE PADRES (sin token, ver index.php)
Flight::route('GET /autoregistro-publico/@idEnlace/contexto', [EnlacesAutoregistroAcudientes::class, 'contextoPublico']);
Flight::route('POST /autoregistro-publico/@idEnlace/iniciar', [EnlacesAutoregistroAcudientes::class, 'iniciarPublico']);
Flight::route('POST /autoregistro-publico/@idEnlace/paso', [EnlacesAutoregistroAcudientes::class, 'pasoPublico']);
Flight::route('POST /autoregistro-publico/@idEnlace/validar-documento', [EnlacesAutoregistroAcudientes::class, 'validarDocumentoPublico']);
Flight::route('POST /autoregistro-publico/@idEnlace/analizar-documento', [EnlacesAutoregistroAcudientes::class, 'analizarDocumentoPublico']);
Flight::route('POST /autoregistro-publico/@idEnlace/registrar', [EnlacesAutoregistroAcudientes::class, 'registrarPublico']);
