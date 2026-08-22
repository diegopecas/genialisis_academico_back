<?php
// TIPOS DE SOLICITUD (catalogo del jardin)
Flight::route('GET /tipos-solicitud', [TiposSolicitud::class, 'getAll']);
Flight::route('GET /tipos-solicitud-activos', [TiposSolicitud::class, 'getActivos']);
Flight::route('GET /tipos-solicitud/@id', [TiposSolicitud::class, 'getById']);
Flight::route('POST /tipos-solicitud', [TiposSolicitud::class, 'new']);
Flight::route('PUT /tipos-solicitud', [TiposSolicitud::class, 'replace']);
Flight::route('DELETE /tipos-solicitud', [TiposSolicitud::class, 'delete']);

// CARGOS QUE ENTRAN SOLOS A LA LISTA DE PERSONAS
Flight::route('GET /tipos-solicitud-cargos', [TiposSolicitudCargos::class, 'getAll']);
Flight::route('GET /tipos-solicitud-cargos/@id', [TiposSolicitudCargos::class, 'getById']);
Flight::route('GET /tipos-solicitud-cargos-tipo/@id_tipo_solicitud', [TiposSolicitudCargos::class, 'getByTipo']);
Flight::route('POST /tipos-solicitud-cargos', [TiposSolicitudCargos::class, 'new']);
Flight::route('PUT /tipos-solicitud-cargos', [TiposSolicitudCargos::class, 'replace']);
Flight::route('DELETE /tipos-solicitud-cargos', [TiposSolicitudCargos::class, 'delete']);

// SOLICITUDES
Flight::route('GET /solicitudes', [Solicitudes::class, 'getAll']);
Flight::route('GET /solicitudes/@id', [Solicitudes::class, 'getById']);
Flight::route('GET /solicitudes-estudiante/@id_estudiante/@fecha', [Solicitudes::class, 'getPorEstudiante']);
Flight::route('GET /solicitudes-por-aprobar', [Solicitudes::class, 'getPorAprobar']);
Flight::route('GET /solicitudes-mias', [Solicitudes::class, 'getMisSolicitudes']);
Flight::route('POST /solicitudes', [Solicitudes::class, 'new']);
Flight::route('PUT /solicitudes', [Solicitudes::class, 'replace']);
Flight::route('PUT /solicitudes/aprobar', [Solicitudes::class, 'aprobar']);
Flight::route('PUT /solicitudes/rechazar', [Solicitudes::class, 'rechazar']);
Flight::route('PUT /solicitudes/anular', [Solicitudes::class, 'anular']);
Flight::route('DELETE /solicitudes', [Solicitudes::class, 'delete']);

// PERSONAS DE LA SOLICITUD (responsables y aprobadores)
Flight::route('GET /solicitudes-personas', [SolicitudesPersonas::class, 'getAll']);
Flight::route('GET /solicitudes-personas/@id', [SolicitudesPersonas::class, 'getById']);
Flight::route('GET /solicitudes-personas-solicitud/@id_solicitud', [SolicitudesPersonas::class, 'getBySolicitud']);
Flight::route('POST /solicitudes-personas', [SolicitudesPersonas::class, 'new']);
Flight::route('PUT /solicitudes-personas', [SolicitudesPersonas::class, 'replace']);
Flight::route('DELETE /solicitudes-personas', [SolicitudesPersonas::class, 'delete']);

// HORAS DE LA SOLICITUD
Flight::route('GET /solicitudes-horarios', [SolicitudesHorarios::class, 'getAll']);
Flight::route('GET /solicitudes-horarios/@id', [SolicitudesHorarios::class, 'getById']);
Flight::route('GET /solicitudes-horarios-solicitud/@id_solicitud', [SolicitudesHorarios::class, 'getBySolicitud']);
Flight::route('POST /solicitudes-horarios', [SolicitudesHorarios::class, 'new']);
Flight::route('PUT /solicitudes-horarios', [SolicitudesHorarios::class, 'replace']);
Flight::route('DELETE /solicitudes-horarios', [SolicitudesHorarios::class, 'delete']);

// OCURRENCIAS (la agenda del dia)
Flight::route('GET /solicitudes-ocurrencias', [SolicitudesOcurrencias::class, 'getAll']);
Flight::route('GET /solicitudes-ocurrencias/@id', [SolicitudesOcurrencias::class, 'getById']);
Flight::route('GET /solicitudes-ocurrencias-solicitud/@id_solicitud', [SolicitudesOcurrencias::class, 'getBySolicitud']);
Flight::route('GET /solicitudes-agenda/@fecha', [SolicitudesOcurrencias::class, 'getAgendaDia']);
Flight::route('PUT /solicitudes-ocurrencias', [SolicitudesOcurrencias::class, 'replace']);
Flight::route('PUT /solicitudes-ocurrencias/cumplida', [SolicitudesOcurrencias::class, 'marcarCumplida']);
Flight::route('PUT /solicitudes-ocurrencias/no-cumplida', [SolicitudesOcurrencias::class, 'marcarNoCumplida']);
Flight::route('PUT /solicitudes-ocurrencias/desmarcar', [SolicitudesOcurrencias::class, 'desmarcar']);
Flight::route('DELETE /solicitudes-ocurrencias', [SolicitudesOcurrencias::class, 'delete']);

// CATALOGOS GLOBALES DEL MODULO (solo lectura)
Flight::route('GET /estados-solicitud', [EstadosSolicitud::class, 'getAll']);
Flight::route('GET /estados-solicitud/@id', [EstadosSolicitud::class, 'getById']);
Flight::route('GET /estados-ocurrencia', [EstadosOcurrencia::class, 'getAll']);
Flight::route('GET /estados-ocurrencia/@id', [EstadosOcurrencia::class, 'getById']);
Flight::route('GET /roles-solicitud-persona', [RolesSolicitudPersona::class, 'getAll']);
Flight::route('GET /roles-solicitud-persona/@id', [RolesSolicitudPersona::class, 'getById']);
Flight::route('GET /origenes-solicitud', [OrigenesSolicitud::class, 'getAll']);
Flight::route('GET /origenes-solicitud/@id', [OrigenesSolicitud::class, 'getById']);
