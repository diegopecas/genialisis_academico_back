<?php
// ESTUDIANTES
Flight::route('GET /estudiantes', [Estudiantes::class, 'getAll']);
Flight::route('GET /estudiantes/@id', [Estudiantes::class, 'getById']);
Flight::route('POST /estudiantes', [Estudiantes::class, 'new']);
Flight::route('PUT /estudiantes', [Estudiantes::class, 'replace']);
Flight::route('DELETE /estudiantes', [Estudiantes::class, 'delete']);
Flight::route('POST /estudiantes/verificar-duplicados', [Estudiantes::class, 'verificarDuplicados']);
Flight::route('POST /estudiantes/actualizacion-masiva', [Estudiantes::class, 'actualizacionMasiva']);
Flight::route('POST /estudiantes/registro-rapido', [Estudiantes::class, 'registroRapido']);
Flight::route('GET /estudiantes-reporte-completo', [Estudiantes::class, 'getReporteCompleto']);
Flight::route('GET /estudiantes-reporte-recordatorios', [Estudiantes::class, 'getReporteRecordatorios']);

// HISTORIAL RECORDATORIOS GENERALES
Flight::route('GET /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'getAll']);
Flight::route('GET /historial-recordatorios-generales/estudiante/@id', [HistorialRecordatoriosGenerales::class, 'getByEstudiante']);
Flight::route('POST /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'new']);
Flight::route('PUT /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'replace']);

Flight::route('GET /estudiantes-x-grupos', [EstudiantesXGrupos::class, 'getAll']);
Flight::route('GET /estudiantes-x-grupos-activos', [EstudiantesXGrupos::class, 'getActivos']);
Flight::route('GET /estudiantes-x-grupos/@id_grupo', [EstudiantesXGrupos::class, 'getByGrupo']);
Flight::route('GET /estudiantes-x-grupos/estudiante/@id', [EstudiantesXGrupos::class, 'getByEstudiante']);
Flight::route('POST /estudiantes-x-grupos', [EstudiantesXGrupos::class, 'new']);
Flight::route('PUT /estudiantes-x-grupos', [EstudiantesXGrupos::class, 'replace']);
Flight::route('POST /estudiantes-x-grupos/cambio-grupo-masivo', [EstudiantesXGrupos::class, 'cambioGrupoMasivo']);


// OBSERVACIONES-ESTUDIANTES
Flight::route('GET /observaciones-estudiantes', [ObservacionesEstudiantes::class, 'getAll']);
Flight::route('GET /observaciones-estudiantes/@id', [ObservacionesEstudiantes::class, 'getById']);
Flight::route('GET /observaciones-estudiantes/estudiante/@id', [ObservacionesEstudiantes::class, 'getByIdEstudiante']);
Flight::route('GET /observaciones-estudiantes/afectado/@id', [ObservacionesEstudiantes::class, 'getByEstudianteAfectado']);
Flight::route('GET /observaciones-estudiantes/datos-registro-informe/@id_sprint', [ObservacionesEstudiantes::class, 'getDatosRegistroInforme']);
Flight::route('POST /observaciones-estudiantes', [ObservacionesEstudiantes::class, 'new']);
Flight::route('PUT /observaciones-estudiantes', [ObservacionesEstudiantes::class, 'replace']);
Flight::route('DELETE /observaciones-estudiantes', [ObservacionesEstudiantes::class, 'delete']);

// TIPOS OBSERVACIONES ESTUDIANTES
Flight::route('GET /tipos-observaciones-estudiantes', [TiposObservacionesEstudiantes::class, 'getAll']);
Flight::route('GET /tipos-observaciones-estudiantes/@id', [TiposObservacionesEstudiantes::class, 'getById']);
Flight::route('POST /tipos-observaciones-estudiantes', [TiposObservacionesEstudiantes::class, 'new']);
Flight::route('PUT /tipos-observaciones-estudiantes', [TiposObservacionesEstudiantes::class, 'replace']);
Flight::route('DELETE /tipos-observaciones-estudiantes', [TiposObservacionesEstudiantes::class, 'delete']);

// Rutas para tipos_acudiente
Flight::route('GET /tipos-acudiente', [TiposAcudiente::class, 'getAll']);
Flight::route('GET /tipos-acudiente/@id', [TiposAcudiente::class, 'getById']);
Flight::route('POST /tipos-acudiente', [TiposAcudiente::class, 'new']);
Flight::route('PUT /tipos-acudiente', [TiposAcudiente::class, 'replace']);
Flight::route('DELETE /tipos-acudiente', [TiposAcudiente::class, 'delete']);

// Rutas para acudientes
Flight::route('GET /acudientes', [Acudientes::class, 'getAll']);
Flight::route('GET /acudientes/@id', [Acudientes::class, 'getById']);
Flight::route('GET /acudientes/estudiante/@id', [Acudientes::class, 'getByEstudiante']);
Flight::route('POST /acudientes', [Acudientes::class, 'new']);
Flight::route('PUT /acudientes', [Acudientes::class, 'replace']);
Flight::route('DELETE /acudientes/@id', [Acudientes::class, 'delete']);
Flight::route('POST /acudientes/verificar-duplicados', [Acudientes::class, 'verificarDuplicados']);
// Ruta para obtener estudiantes de un acudiente específico
Flight::route('GET /acudientes/mis-estudiantes/@id_persona', [Acudientes::class, 'getEstudiantesByAcudiente']);
Flight::route('GET /acudientes/mis-estudiantes-ids/@id_persona', [Acudientes::class, 'getEstudiantesIdsOnly']);


// Tipos de necesidades especiales
Flight::route('GET /tipos-necesidades-especiales', [TiposNecesidadesEspeciales::class, 'getAll']);
Flight::route('GET /tipos-necesidades-especiales/@id', [TiposNecesidadesEspeciales::class, 'getById']);
Flight::route('POST /tipos-necesidades-especiales', [TiposNecesidadesEspeciales::class, 'new']);
Flight::route('PUT /tipos-necesidades-especiales', [TiposNecesidadesEspeciales::class, 'replace']);
Flight::route('DELETE /tipos-necesidades-especiales', [TiposNecesidadesEspeciales::class, 'delete']);

// TARIFAS POR GRUPOS
Flight::route('GET /tarifas-grupos', [TarifasGrupos::class, 'getAll']);
Flight::route('GET /tarifas-grupos/@id', [TarifasGrupos::class, 'getById']);
Flight::route('GET /tarifas-grupos/grupo/@id_grupo', [TarifasGrupos::class, 'getByGrupo']);
Flight::route('GET /tarifas-grupos/grupo/@id_grupo/anio/@anio', [TarifasGrupos::class, 'getByGrupoAnio']);
Flight::route('GET /tarifas-grupos/anio/@anio', [TarifasGrupos::class, 'getByAnio']);
Flight::route('POST /tarifas-grupos', [TarifasGrupos::class, 'new']);
Flight::route('PUT /tarifas-grupos', [TarifasGrupos::class, 'replace']);
Flight::route('DELETE /tarifas-grupos', [TarifasGrupos::class, 'delete']);

// CONTRATOS DE MATRÍCULA
Flight::route('GET /contratos-matricula', [ContratosMatricula::class, 'getAll']);
Flight::route('GET /contratos-matricula/@id', [ContratosMatricula::class, 'getById']);
Flight::route('GET /contratos-matricula/estudiante/@id_estudiante', [ContratosMatricula::class, 'getByEstudiante']);
Flight::route('GET /contratos-matricula/anio/@anio', [ContratosMatricula::class, 'getByAnio']);
Flight::route('GET /contratos-matricula/@id/acudientes', [ContratosMatricula::class, 'getAcudientesByContrato']);
Flight::route('GET /contratos-matricula/@id/datos-completos', [ContratosMatricula::class, 'getDatosContrato']);
Flight::route('POST /contratos-matricula', [ContratosMatricula::class, 'new']);
Flight::route('POST /contratos-matricula/verificar-existente', [ContratosMatricula::class, 'verificarExistente']);
Flight::route('PUT /contratos-matricula', [ContratosMatricula::class, 'replace']);
Flight::route('PUT /contratos-matricula/marcar-firmado', [ContratosMatricula::class, 'marcarFirmado']);
Flight::route('PUT /contratos-matricula/desmarcar-firmado', [ContratosMatricula::class, 'desmarcarFirmado']);
Flight::route('PUT /contratos-matricula/anular', [ContratosMatricula::class, 'anular']);
Flight::route('DELETE /contratos-matricula', [ContratosMatricula::class, 'delete']);

// CONTRATOS DE MATRÍCULA - VALORES
Flight::route('GET /contratos-matricula-valores/contrato/@id', [ContratosMatriculaValores::class, 'getByContrato']);
Flight::route('POST /contratos-matricula-valores', [ContratosMatriculaValores::class, 'guardarValores']);
Flight::route('POST /contratos-matricula-valores/generar-defecto', [ContratosMatriculaValores::class, 'generarValoresPorDefecto']);

// TIPOS DE PLANTILLAS
Flight::route('GET /tipos-plantillas', [TiposPlantillas::class, 'getAll']);
Flight::route('GET /tipos-plantillas/@id', [TiposPlantillas::class, 'getById']);
Flight::route('GET /tipos-plantillas/codigo/@codigo', [TiposPlantillas::class, 'getByCodigo']);
Flight::route('POST /tipos-plantillas', [TiposPlantillas::class, 'new']);
Flight::route('PUT /tipos-plantillas', [TiposPlantillas::class, 'replace']);
Flight::route('DELETE /tipos-plantillas', [TiposPlantillas::class, 'delete']);

// PLANTILLAS
Flight::route('GET /plantillas', [Plantillas::class, 'getAll']);
Flight::route('GET /plantillas/obtener-by-tipo-clave/@codigoTipo/@clavePlantilla', [Plantillas::class, 'getByTipoClave']);
Flight::route('GET /plantillas/obtener-by-tipo/@codigoTipo', [Plantillas::class, 'getByTipo']);
Flight::route('GET /plantillas/@id', [Plantillas::class, 'getById']);
Flight::route('PUT /plantillas', [Plantillas::class, 'replace']);

// TIPOS AUTORIZACION RECOGER
Flight::route('GET /tipos-autorizacion-recoger', [TiposAutorizacionRecoger::class, 'getAll']);
Flight::route('GET /tipos-autorizacion-recoger/@id', [TiposAutorizacionRecoger::class, 'getById']);
Flight::route('POST /tipos-autorizacion-recoger', [TiposAutorizacionRecoger::class, 'new']);
Flight::route('PUT /tipos-autorizacion-recoger', [TiposAutorizacionRecoger::class, 'replace']);
Flight::route('DELETE /tipos-autorizacion-recoger/@id', [TiposAutorizacionRecoger::class, 'delete']);

// AUTORIZADOS RECOGER
Flight::route('GET /autorizados-recoger', [AutorizadosRecoger::class, 'getAll']);
Flight::route('GET /autorizados-recoger/@id', [AutorizadosRecoger::class, 'getById']);
Flight::route('GET /autorizados-recoger/estudiante/@id', [AutorizadosRecoger::class, 'getByEstudiante']);
Flight::route('GET /autorizados-recoger/estudiante-hoy/@id', [AutorizadosRecoger::class, 'getActivosHoyByEstudiante']);
Flight::route('POST /autorizados-recoger', [AutorizadosRecoger::class, 'new']);
Flight::route('PUT /autorizados-recoger', [AutorizadosRecoger::class, 'replace']);
Flight::route('DELETE /autorizados-recoger/@id', [AutorizadosRecoger::class, 'delete']);
Flight::route('POST /autorizados-recoger/verificar-duplicados', [AutorizadosRecoger::class, 'verificarDuplicados']);

// AUTORIZADOS RECOGER HISTORIAL
Flight::route('GET /autorizados-recoger-historial', [AutorizadosRecogerHistorial::class, 'getAll']);
Flight::route('GET /autorizados-recoger-historial/@id', [AutorizadosRecogerHistorial::class, 'getById']);
Flight::route('GET /autorizados-recoger-historial/autorizado/@id', [AutorizadosRecogerHistorial::class, 'getByAutorizado']);
Flight::route('POST /autorizados-recoger-historial', [AutorizadosRecogerHistorial::class, 'new']);
Flight::route('DELETE /autorizados-recoger-historial/@id', [AutorizadosRecogerHistorial::class, 'delete']);

// TIPOS DATOS MÉDICOS
Flight::route('GET /tipos-datos-medicos', [TiposDatosMedicos::class, 'getAll']);
Flight::route('GET /tipos-datos-medicos/@id', [TiposDatosMedicos::class, 'getById']);
Flight::route('POST /tipos-datos-medicos', [TiposDatosMedicos::class, 'new']);
Flight::route('PUT /tipos-datos-medicos', [TiposDatosMedicos::class, 'replace']);
Flight::route('DELETE /tipos-datos-medicos', [TiposDatosMedicos::class, 'delete']);

// DATOS MÉDICOS
Flight::route('GET /datos-medicos', [DatosMedicos::class, 'getAll']);
Flight::route('GET /datos-medicos/@id', [DatosMedicos::class, 'getById']);
Flight::route('GET /datos-medicos/tipo/@id_tipo', [DatosMedicos::class, 'getByTipo']);
Flight::route('POST /datos-medicos', [DatosMedicos::class, 'new']);
Flight::route('PUT /datos-medicos', [DatosMedicos::class, 'replace']);
Flight::route('DELETE /datos-medicos', [DatosMedicos::class, 'delete']);

// DATOS MÉDICOS POR ESTUDIANTE
Flight::route('GET /datos-medicos-x-estudiante/@id_estudiante', [DatosMedicosXEstudiante::class, 'getByEstudiante']);
Flight::route('POST /datos-medicos-x-estudiante', [DatosMedicosXEstudiante::class, 'guardarPorEstudiante']);

// TIPOS DATOS ADICIONALES
Flight::route('GET /tipos-datos-adicionales', [TiposDatosAdicionales::class, 'getAll']);
Flight::route('GET /tipos-datos-adicionales/@id', [TiposDatosAdicionales::class, 'getById']);
Flight::route('POST /tipos-datos-adicionales', [TiposDatosAdicionales::class, 'new']);
Flight::route('PUT /tipos-datos-adicionales', [TiposDatosAdicionales::class, 'replace']);
Flight::route('DELETE /tipos-datos-adicionales', [TiposDatosAdicionales::class, 'delete']);

// DATOS ADICIONALES
Flight::route('GET /datos-adicionales', [DatosAdicionales::class, 'getAll']);
Flight::route('GET /datos-adicionales/@id', [DatosAdicionales::class, 'getById']);
Flight::route('GET /datos-adicionales/tipo/@id_tipo', [DatosAdicionales::class, 'getByTipo']);
Flight::route('POST /datos-adicionales', [DatosAdicionales::class, 'new']);
Flight::route('PUT /datos-adicionales', [DatosAdicionales::class, 'replace']);
Flight::route('DELETE /datos-adicionales', [DatosAdicionales::class, 'delete']);

// DATOS ADICIONALES POR ESTUDIANTE
Flight::route('GET /datos-adicionales-x-estudiante/@id_estudiante', [DatosAdicionalesXEstudiante::class, 'getByEstudiante']);
Flight::route('POST /datos-adicionales-x-estudiante', [DatosAdicionalesXEstudiante::class, 'guardarPorEstudiante']);

// HORARIOS ESTUDIANTE
Flight::route('GET /horarios-estudiante/@id_estudiante', [HorariosEstudiante::class, 'getByEstudiante']);
Flight::route('PUT /horarios-estudiante', [HorariosEstudiante::class, 'replace']);
Flight::route('POST /horarios-estudiante/guardar-todos', [HorariosEstudiante::class, 'guardarTodos']);
Flight::route('POST /horarios-estudiante/inicializar', [HorariosEstudiante::class, 'inicializarDesdeDefault']);


// HISTORIAL INFORMES ESTUDIANTES
Flight::route('GET /historial-informes-estudiantes', [HistorialInformesEstudiantes::class, 'getAll']);
Flight::route('GET /historial-informes-estudiantes/estudiante/@id', [HistorialInformesEstudiantes::class, 'getByEstudiante']);
Flight::route('POST /historial-informes-estudiantes', [HistorialInformesEstudiantes::class, 'new']);
Flight::route('PUT /historial-informes-estudiantes', [HistorialInformesEstudiantes::class, 'replace']);