<?php

// GENEROS
Flight::route('GET /generos', [Generos::class, 'getAll']);


Flight::route('GET /niveles-escolaridad', [NivelesEscolaridad::class, 'getAll']);

Flight::route('GET /tipos-dias', [TiposDias::class, 'getAll']);
Flight::route('GET /tipos-evento-calendario', [TiposEventoCalendario::class, 'getAll']);
Flight::route('GET /dias-semana', [DiasSemana::class, 'getAll']);

// PAISES
Flight::route('GET /paises', [Paises::class, 'getAll']);

// CIUDADES
Flight::route('GET /ciudades', [Ciudades::class, 'getAll']);

// DEPARTAMENTOS
Flight::route('GET /departamentos', [Departamentos::class, 'getAll']);

// TIPOS IDENTIFICACIÓN
Flight::route('GET /tipos-identificacion', [TiposIdentificacion::class, 'getAll']);

// CARGOS
Flight::route('GET /cargos', [Cargos::class, 'getAll']);
Flight::route('GET /cargos/@id', [Cargos::class, 'getById']);
Flight::route('POST /cargos', [Cargos::class, 'new']);
Flight::route('PUT /cargos', [Cargos::class, 'replace']);
Flight::route('DELETE /cargos', [Cargos::class, 'delete']);


// CALENDARIOS
Flight::route('GET /calendarios', [Calendarios::class, 'getAll']);
Flight::route('GET /calendarios/mes/@anio/@mes', [Calendarios::class, 'getCalendarioMes']);
Flight::route('GET /calendarios/@id', [Calendarios::class, 'getById']);
Flight::route('POST /calendarios', [Calendarios::class, 'new']);
Flight::route('PUT /calendarios', [Calendarios::class, 'replace']);
Flight::route('DELETE /calendarios', [Calendarios::class, 'delete']);
Flight::route('GET /calendarios/habiles/@fecha_inicial/@fecha_final', [Calendarios::class, 'getDiasHabiles']);
Flight::route('GET /calendarios/rango/@fecha_inicial/@fecha_final', [Calendarios::class, 'getByRangoFechas']);

// CALENDARIOS EVENTOS
Flight::route('GET /calendarios-eventos', [CalendariosEventos::class, 'getAll']);
Flight::route('GET /calendarios-eventos/mes/@anio/@mes', [CalendariosEventos::class, 'getByMes']);
Flight::route('GET /calendarios-eventos/@id', [CalendariosEventos::class, 'getById']);
Flight::route('POST /calendarios-eventos', [CalendariosEventos::class, 'new']);
Flight::route('PUT /calendarios-eventos', [CalendariosEventos::class, 'replace']);
Flight::route('DELETE /calendarios-eventos', [CalendariosEventos::class, 'delete']);


// CONFIGURACIÓN GLOBAL
Flight::route('GET /configuracion-global', [ConfiguracionGlobal::class, 'getAll']);
Flight::route('GET /configuracion-global/@id', [ConfiguracionGlobal::class, 'getById']);
Flight::route('GET /configuracion-global/clave/@clave', [ConfiguracionGlobal::class, 'getByClave']);
Flight::route('POST /configuracion-global/multiples', [ConfiguracionGlobal::class, 'getMultiples']);
Flight::route('POST /configuracion-global', [ConfiguracionGlobal::class, 'new']);
Flight::route('PUT /configuracion-global', [ConfiguracionGlobal::class, 'replace']);
Flight::route('PUT /configuracion-global/clave', [ConfiguracionGlobal::class, 'updateByClave']);
Flight::route('DELETE /configuracion-global', [ConfiguracionGlobal::class, 'delete']);


// AYUDA
Flight::route('GET /ayuda/modulos', [Ayuda::class, 'getModulos']);