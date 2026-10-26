<?php
// =====================================================================
// Sesiones de cursos extra: espacios de los lugares, reglas de reserva,
// sesiones, reservas, reservas fijas y paquetes de sesiones.
// Las rutas fijas van antes de las que reciben @id para que Flight no las
// confunda.
// =====================================================================

// Catalogos globales de estados
Flight::route('GET /estados-sesiones-cursos-extra', [EstadosSesionesCursosExtra::class, 'getAll']);
Flight::route('GET /estados-reservas-cursos-extra', [EstadosReservasCursosExtra::class, 'getAll']);

// Espacios de un lugar (canchas, piscinas, salones) con su horario fijo
Flight::route('GET /espacios-lugares-cursos-extra/activos', [EspaciosLugaresCursosExtra::class, 'getActivos']);
Flight::route('GET /espacios-lugares-cursos-extra/lugar/@id_lugar_curso_extra/activos', [EspaciosLugaresCursosExtra::class, 'getActivosByLugar']);
Flight::route('GET /espacios-lugares-cursos-extra/lugar/@id_lugar_curso_extra', [EspaciosLugaresCursosExtra::class, 'getByLugar']);
Flight::route('GET /espacios-lugares-cursos-extra/@id', [EspaciosLugaresCursosExtra::class, 'getById']);
Flight::route('POST /espacios-lugares-cursos-extra', [EspaciosLugaresCursosExtra::class, 'new']);
Flight::route('PUT /espacios-lugares-cursos-extra', [EspaciosLugaresCursosExtra::class, 'replace']);
Flight::route('DELETE /espacios-lugares-cursos-extra', [EspaciosLugaresCursosExtra::class, 'delete']);
Flight::route('GET /horarios-espacios-lugares-cursos-extra/espacio/@id_espacio_lugar_curso_extra', [HorariosEspaciosLugaresCursosExtra::class, 'getByEspacio']);

// Reglas de reserva del curso (uno a uno)
Flight::route('GET /configuracion-reservas-cursos-extra/curso/@id_curso_extra', [ConfiguracionReservasCursosExtra::class, 'getByCurso']);
Flight::route('PUT /configuracion-reservas-cursos-extra', [ConfiguracionReservasCursosExtra::class, 'guardar']);

// Sesiones
Flight::route('GET /sesiones-cursos-extra/mi-docente', [SesionesCursosExtra::class, 'getMiDocente']);
Flight::route('POST /sesiones-cursos-extra/materializar', [SesionesCursosExtra::class, 'materializar']);
Flight::route('PUT /sesiones-cursos-extra/cancelar', [SesionesCursosExtra::class, 'cancelar']);
Flight::route('PUT /sesiones-cursos-extra/reabrir', [SesionesCursosExtra::class, 'reabrir']);
Flight::route('PUT /sesiones-cursos-extra/asistencia', [SesionesCursosExtra::class, 'registrarAsistencia']);
Flight::route('GET /sesiones-cursos-extra', [SesionesCursosExtra::class, 'getAll']);
Flight::route('GET /sesiones-cursos-extra/@id', [SesionesCursosExtra::class, 'getById']);
Flight::route('POST /sesiones-cursos-extra', [SesionesCursosExtra::class, 'new']);
Flight::route('PUT /sesiones-cursos-extra', [SesionesCursosExtra::class, 'replace']);
Flight::route('DELETE /sesiones-cursos-extra', [SesionesCursosExtra::class, 'delete']);
Flight::route('GET /docentes-x-sesiones-cursos-extra/sesion/@id_sesion_curso_extra', [DocentesXSesionesCursosExtra::class, 'getBySesion']);

// Reservas de una sesion
Flight::route('PUT /reservas-sesiones-cursos-extra/cancelar', [ReservasSesionesCursosExtra::class, 'cancelar']);
Flight::route('PUT /reservas-sesiones-cursos-extra/reagendar', [ReservasSesionesCursosExtra::class, 'reagendar']);
Flight::route('GET /reservas-sesiones-cursos-extra/sesion/@id_sesion_curso_extra', [ReservasSesionesCursosExtra::class, 'getBySesion']);
Flight::route('GET /reservas-sesiones-cursos-extra/inscripcion/@id_estudiante_x_curso_extra', [ReservasSesionesCursosExtra::class, 'getByInscripcion']);
Flight::route('POST /reservas-sesiones-cursos-extra', [ReservasSesionesCursosExtra::class, 'new']);

// Reservas fijas ("voy todos los sabados de 8 a 10")
Flight::route('GET /reservas-fijas-cursos-extra/curso/@id_curso_extra', [ReservasFijasCursosExtra::class, 'getByCurso']);
Flight::route('GET /reservas-fijas-cursos-extra/@id', [ReservasFijasCursosExtra::class, 'getById']);
Flight::route('POST /reservas-fijas-cursos-extra', [ReservasFijasCursosExtra::class, 'new']);
Flight::route('PUT /reservas-fijas-cursos-extra', [ReservasFijasCursosExtra::class, 'replace']);
Flight::route('DELETE /reservas-fijas-cursos-extra', [ReservasFijasCursosExtra::class, 'delete']);

// Paquetes de sesiones del estudiante
Flight::route('GET /paquetes-estudiantes-cursos-extra/curso/@id_curso_extra', [PaquetesEstudiantesCursosExtra::class, 'getByCurso']);
Flight::route('GET /paquetes-estudiantes-cursos-extra/inscripcion/@id_estudiante_x_curso_extra', [PaquetesEstudiantesCursosExtra::class, 'getByInscripcion']);
Flight::route('GET /paquetes-estudiantes-cursos-extra/@id', [PaquetesEstudiantesCursosExtra::class, 'getById']);
Flight::route('POST /paquetes-estudiantes-cursos-extra', [PaquetesEstudiantesCursosExtra::class, 'new']);
Flight::route('PUT /paquetes-estudiantes-cursos-extra', [PaquetesEstudiantesCursosExtra::class, 'replace']);
Flight::route('DELETE /paquetes-estudiantes-cursos-extra', [PaquetesEstudiantesCursosExtra::class, 'delete']);
