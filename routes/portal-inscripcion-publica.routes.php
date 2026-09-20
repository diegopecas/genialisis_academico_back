<?php
// CONFIGURACION DEL PORTAL PUBLICO (administracion, con token)
Flight::route('GET /configuracion-portal-publico', [ConfiguracionPortalPublico::class, 'getByTenant']);
Flight::route('PUT /configuracion-portal-publico', [ConfiguracionPortalPublico::class, 'replace']);

// SOLICITUDES DE INSCRIPCION (administracion, con token)
Flight::route('GET /solicitudes-inscripcion-publica', [SolicitudesInscripcionPublica::class, 'getAll']);
Flight::route('GET /solicitudes-inscripcion-publica/institucion/@id_institucion_cliente', [SolicitudesInscripcionPublica::class, 'getByInstitucion']);
Flight::route('GET /solicitudes-inscripcion-publica/@id', [SolicitudesInscripcionPublica::class, 'getById']);
Flight::route('PUT /solicitudes-inscripcion-publica/aprobar', [SolicitudesInscripcionPublica::class, 'aprobar']);
Flight::route('PUT /solicitudes-inscripcion-publica/rechazar', [SolicitudesInscripcionPublica::class, 'rechazar']);
Flight::route('DELETE /solicitudes-inscripcion-publica', [SolicitudesInscripcionPublica::class, 'delete']);

// PAGINA PUBLICA DE INSCRIPCION (sin token, ver index.php)
// El tenant llega por el header X-Tenant, que la pagina fija con el codigo
// que viene en la URL. Cada endpoint valida que el portal este encendido.
Flight::route('GET /inscripcion-publica/configuracion', [ConfiguracionPortalPublico::class, 'getPublico']);
Flight::route('GET /inscripcion-publica/catalogos', [SolicitudesInscripcionPublica::class, 'catalogosPublico']);
Flight::route('GET /inscripcion-publica/instituciones', [SolicitudesInscripcionPublica::class, 'institucionesPublico']);
Flight::route('POST /inscripcion-publica/cursos', [SolicitudesInscripcionPublica::class, 'cursosPublico']);
Flight::route('GET /inscripcion-publica/horarios/@id_curso_extra', [SolicitudesInscripcionPublica::class, 'horariosPublico']);
Flight::route('POST /inscripcion-publica/registrar', [SolicitudesInscripcionPublica::class, 'registrarPublico']);
