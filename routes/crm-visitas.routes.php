<?php
// =====================================================
// MÓDULO CRM - VISITAS
// RUTAS DEL BACKEND
// =====================================================

// ============================================
// CATÁLOGOS - TIPOS
// ============================================

// TIPOS DE CONTACTO
Flight::route('GET /tipos-contacto', [TiposContacto::class, 'getAll']);
Flight::route('GET /tipos-contacto/@id', [TiposContacto::class, 'getById']);
Flight::route('POST /tipos-contacto', [TiposContacto::class, 'new']);
Flight::route('PUT /tipos-contacto', [TiposContacto::class, 'replace']);
Flight::route('DELETE /tipos-contacto', [TiposContacto::class, 'delete']);

// TIPOS DE CÓMO NOS CONOCIÓ
Flight::route('GET /tipos-como-conocio', [TiposComoConocio::class, 'getAll']);
Flight::route('GET /tipos-como-conocio/@id', [TiposComoConocio::class, 'getById']);
Flight::route('POST /tipos-como-conocio', [TiposComoConocio::class, 'new']);
Flight::route('PUT /tipos-como-conocio', [TiposComoConocio::class, 'replace']);
Flight::route('DELETE /tipos-como-conocio', [TiposComoConocio::class, 'delete']);

// TIPOS DE PARENTESCO
Flight::route('GET /tipos-parentesco', [TiposParentesco::class, 'getAll']);
Flight::route('GET /tipos-parentesco/@id', [TiposParentesco::class, 'getById']);
Flight::route('POST /tipos-parentesco', [TiposParentesco::class, 'new']);
Flight::route('PUT /tipos-parentesco', [TiposParentesco::class, 'replace']);
Flight::route('DELETE /tipos-parentesco', [TiposParentesco::class, 'delete']);

// TIPOS DE RAZONES DE BÚSQUEDA
Flight::route('GET /tipos-razones-busqueda', [TiposRazonesBusqueda::class, 'getAll']);
Flight::route('GET /tipos-razones-busqueda/@id', [TiposRazonesBusqueda::class, 'getById']);
Flight::route('POST /tipos-razones-busqueda', [TiposRazonesBusqueda::class, 'new']);
Flight::route('PUT /tipos-razones-busqueda', [TiposRazonesBusqueda::class, 'replace']);
Flight::route('DELETE /tipos-razones-busqueda', [TiposRazonesBusqueda::class, 'delete']);

// PARÁMETROS DISC (RUTAS ESPECÍFICAS PRIMERO)
Flight::route('GET /parametros-disc/categoria/@categoria', [ParametrosDisc::class, 'getByCategoria']);
Flight::route('GET /parametros-disc', [ParametrosDisc::class, 'getAll']);
Flight::route('GET /parametros-disc/@id', [ParametrosDisc::class, 'getById']);
Flight::route('POST /parametros-disc', [ParametrosDisc::class, 'new']);
Flight::route('PUT /parametros-disc', [ParametrosDisc::class, 'replace']);
Flight::route('DELETE /parametros-disc', [ParametrosDisc::class, 'delete']);

// TIPOS DE NIVEL DE INTERÉS
Flight::route('GET /tipos-nivel-interes', [TiposNivelInteres::class, 'getAll']);
Flight::route('GET /tipos-nivel-interes/@id', [TiposNivelInteres::class, 'getById']);
Flight::route('POST /tipos-nivel-interes', [TiposNivelInteres::class, 'new']);
Flight::route('PUT /tipos-nivel-interes', [TiposNivelInteres::class, 'replace']);
Flight::route('DELETE /tipos-nivel-interes', [TiposNivelInteres::class, 'delete']);

// TIPOS DE URGENCIA
Flight::route('GET /tipos-urgencia', [TiposUrgencia::class, 'getAll']);
Flight::route('GET /tipos-urgencia/@id', [TiposUrgencia::class, 'getById']);
Flight::route('POST /tipos-urgencia', [TiposUrgencia::class, 'new']);
Flight::route('PUT /tipos-urgencia', [TiposUrgencia::class, 'replace']);
Flight::route('DELETE /tipos-urgencia', [TiposUrgencia::class, 'delete']);

// TIPOS DE CUÁNDO HACER SEGUIMIENTO
Flight::route('GET /tipos-cuando-seguimiento', [TiposCuandoSeguimiento::class, 'getAll']);
Flight::route('GET /tipos-cuando-seguimiento/@id', [TiposCuandoSeguimiento::class, 'getById']);
Flight::route('POST /tipos-cuando-seguimiento', [TiposCuandoSeguimiento::class, 'new']);
Flight::route('PUT /tipos-cuando-seguimiento', [TiposCuandoSeguimiento::class, 'replace']);
Flight::route('DELETE /tipos-cuando-seguimiento', [TiposCuandoSeguimiento::class, 'delete']);

// TIPOS DE QUIÉN DECIDE
Flight::route('GET /tipos-quien-decide', [TiposQuienDecide::class, 'getAll']);
Flight::route('GET /tipos-quien-decide/@id', [TiposQuienDecide::class, 'getById']);
Flight::route('POST /tipos-quien-decide', [TiposQuienDecide::class, 'new']);
Flight::route('PUT /tipos-quien-decide', [TiposQuienDecide::class, 'replace']);
Flight::route('DELETE /tipos-quien-decide', [TiposQuienDecide::class, 'delete']);

// TIPOS DE COMPROMISOS
Flight::route('GET /tipos-compromisos', [TiposCompromisos::class, 'getAll']);
Flight::route('GET /tipos-compromisos/@id', [TiposCompromisos::class, 'getById']);
Flight::route('POST /tipos-compromisos', [TiposCompromisos::class, 'new']);
Flight::route('PUT /tipos-compromisos', [TiposCompromisos::class, 'replace']);
Flight::route('DELETE /tipos-compromisos', [TiposCompromisos::class, 'delete']);

// PROTOCOLO - PASOS
Flight::route('GET /protocolo-pasos', [ProtocoloPasos::class, 'getAll']);
Flight::route('GET /protocolo-pasos/@id', [ProtocoloPasos::class, 'getById']);
Flight::route('POST /protocolo-pasos', [ProtocoloPasos::class, 'new']);
Flight::route('PUT /protocolo-pasos', [ProtocoloPasos::class, 'replace']);
Flight::route('DELETE /protocolo-pasos', [ProtocoloPasos::class, 'delete']);

// PROTOCOLO - CONTENIDO POR PERFIL (RUTAS ESPECÍFICAS PRIMERO)
Flight::route('GET /protocolo-contenido-perfil/por-paso/@id_paso/perfil/@perfil', [ProtocoloContenidoPerfil::class, 'getByPasoYPerfil']);
Flight::route('GET /protocolo-contenido-perfil/por-paso/@id_paso', [ProtocoloContenidoPerfil::class, 'getByPaso']);
Flight::route('GET /protocolo-contenido-perfil', [ProtocoloContenidoPerfil::class, 'getAll']);
Flight::route('GET /protocolo-contenido-perfil/@id', [ProtocoloContenidoPerfil::class, 'getById']);
Flight::route('POST /protocolo-contenido-perfil', [ProtocoloContenidoPerfil::class, 'new']);
Flight::route('PUT /protocolo-contenido-perfil', [ProtocoloContenidoPerfil::class, 'replace']);
Flight::route('DELETE /protocolo-contenido-perfil', [ProtocoloContenidoPerfil::class, 'delete']);
Flight::route('GET /protocolo-contenido-perfil-por-perfil/@perfil', [ProtocoloContenidoPerfil::class, 'getByPerfil']);

// TIPOS DE OBJECIONES
Flight::route('GET /tipos-objeciones', [TiposObjeciones::class, 'getAll']);
Flight::route('GET /tipos-objeciones/@id', [TiposObjeciones::class, 'getById']);
Flight::route('POST /tipos-objeciones', [TiposObjeciones::class, 'new']);
Flight::route('PUT /tipos-objeciones', [TiposObjeciones::class, 'replace']);
Flight::route('DELETE /tipos-objeciones', [TiposObjeciones::class, 'delete']);

// TIPOS DE RESULTADO DE VISITA
Flight::route('GET /tipos-resultado-visita', [TiposResultadoVisita::class, 'getAll']);
Flight::route('GET /tipos-resultado-visita/@id', [TiposResultadoVisita::class, 'getById']);
Flight::route('POST /tipos-resultado-visita', [TiposResultadoVisita::class, 'new']);
Flight::route('PUT /tipos-resultado-visita', [TiposResultadoVisita::class, 'replace']);
Flight::route('DELETE /tipos-resultado-visita', [TiposResultadoVisita::class, 'delete']);

// SERVICIOS DEL JARDÍN

// TIPOS DE IMPORTANCIA DEL DETALLE
Flight::route('GET /tipos-importancia-detalle', [TiposImportanciaDetalle::class, 'getAll']);
Flight::route('GET /tipos-importancia-detalle/@id', [TiposImportanciaDetalle::class, 'getById']);
Flight::route('POST /tipos-importancia-detalle', [TiposImportanciaDetalle::class, 'new']);
Flight::route('PUT /tipos-importancia-detalle', [TiposImportanciaDetalle::class, 'replace']);
Flight::route('DELETE /tipos-importancia-detalle', [TiposImportanciaDetalle::class, 'delete']);

// TIPOS DE NIVEL DE AGRADECIMIENTO
Flight::route('GET /tipos-nivel-agradecimiento', [TiposNivelAgradecimiento::class, 'getAll']);
Flight::route('GET /tipos-nivel-agradecimiento/@id', [TiposNivelAgradecimiento::class, 'getById']);
Flight::route('POST /tipos-nivel-agradecimiento', [TiposNivelAgradecimiento::class, 'new']);
Flight::route('PUT /tipos-nivel-agradecimiento', [TiposNivelAgradecimiento::class, 'replace']);
Flight::route('DELETE /tipos-nivel-agradecimiento', [TiposNivelAgradecimiento::class, 'delete']);

// ASPECTOS A MEJORAR
Flight::route('GET /aspectos-mejorar', [AspectosMejorar::class, 'getAll']);
Flight::route('GET /aspectos-mejorar/@id', [AspectosMejorar::class, 'getById']);
Flight::route('POST /aspectos-mejorar', [AspectosMejorar::class, 'new']);
Flight::route('PUT /aspectos-mejorar', [AspectosMejorar::class, 'replace']);
Flight::route('DELETE /aspectos-mejorar', [AspectosMejorar::class, 'delete']);

// TIPOS DE VALIDEZ DEL FEEDBACK
Flight::route('GET /tipos-validez-feedback', [TiposValidezFeedback::class, 'getAll']);
Flight::route('GET /tipos-validez-feedback/@id', [TiposValidezFeedback::class, 'getById']);
Flight::route('POST /tipos-validez-feedback', [TiposValidezFeedback::class, 'new']);
Flight::route('PUT /tipos-validez-feedback', [TiposValidezFeedback::class, 'replace']);
Flight::route('DELETE /tipos-validez-feedback', [TiposValidezFeedback::class, 'delete']);

// TIPOS DE PERFIL ECONÓMICO
Flight::route('GET /tipos-perfil-economico', [TiposPerfilEconomico::class, 'getAll']);
Flight::route('GET /tipos-perfil-economico/@id', [TiposPerfilEconomico::class, 'getById']);
Flight::route('POST /tipos-perfil-economico', [TiposPerfilEconomico::class, 'new']);
Flight::route('PUT /tipos-perfil-economico', [TiposPerfilEconomico::class, 'replace']);
Flight::route('DELETE /tipos-perfil-economico', [TiposPerfilEconomico::class, 'delete']);

// TIPOS DE NIVEL DE EXIGENCIA
Flight::route('GET /tipos-nivel-exigencia', [TiposNivelExigencia::class, 'getAll']);
Flight::route('GET /tipos-nivel-exigencia/@id', [TiposNivelExigencia::class, 'getById']);
Flight::route('POST /tipos-nivel-exigencia', [TiposNivelExigencia::class, 'new']);
Flight::route('PUT /tipos-nivel-exigencia', [TiposNivelExigencia::class, 'replace']);
Flight::route('DELETE /tipos-nivel-exigencia', [TiposNivelExigencia::class, 'delete']);

// TIPOS DE SEMÁFORO CLIENTE
Flight::route('GET /tipos-semaforo-cliente', [TiposSemaforoCliente::class, 'getAll']);
Flight::route('GET /tipos-semaforo-cliente/@id', [TiposSemaforoCliente::class, 'getById']);
Flight::route('POST /tipos-semaforo-cliente', [TiposSemaforoCliente::class, 'new']);
Flight::route('PUT /tipos-semaforo-cliente', [TiposSemaforoCliente::class, 'replace']);
Flight::route('DELETE /tipos-semaforo-cliente', [TiposSemaforoCliente::class, 'delete']);

// TIPOS DE INCLINACIÓN DE DECISIÓN
Flight::route('GET /tipos-inclinacion-decision', [TiposInclinacionDecision::class, 'getAll']);
Flight::route('GET /tipos-inclinacion-decision/@id', [TiposInclinacionDecision::class, 'getById']);
Flight::route('POST /tipos-inclinacion-decision', [TiposInclinacionDecision::class, 'new']);
Flight::route('PUT /tipos-inclinacion-decision', [TiposInclinacionDecision::class, 'replace']);
Flight::route('DELETE /tipos-inclinacion-decision', [TiposInclinacionDecision::class, 'delete']);

// ============================================
// TABLAS PRINCIPALES - TAB 1
// ============================================

// VISITAS (RUTAS ESPECÍFICAS PRIMERO)
Flight::route('GET /visitas/completa/@id', [Visitas::class, 'getVisitaCompleta']);
Flight::route('GET /visitas/fecha/@fecha_inicio/@fecha_fin', [Visitas::class, 'getByFecha']);
Flight::route('GET /visitas', [Visitas::class, 'getAll']);
Flight::route('GET /visitas/@id', [Visitas::class, 'getById']);
Flight::route('POST /visitas', [Visitas::class, 'new']);
Flight::route('PUT /visitas', [Visitas::class, 'replace']);
Flight::route('DELETE /visitas', [Visitas::class, 'delete']);
Flight::route('GET /visitas/dashboard/stats', [Visitas::class, 'getDashboardStats']);

// VISITANTES (RUTAS ESPECÍFICAS PRIMERO)
Flight::route('PUT /visitantes/perfil-disc', [Visitantes::class, 'actualizarPerfilDisc']);
Flight::route('GET /visitantes/por-visita/@id_visita', [Visitantes::class, 'getByVisita']);
Flight::route('GET /visitantes', [Visitantes::class, 'getAll']);
Flight::route('GET /visitantes/@id', [Visitantes::class, 'getById']);
Flight::route('POST /visitantes', [Visitantes::class, 'new']);
Flight::route('PUT /visitantes', [Visitantes::class, 'replace']);
Flight::route('DELETE /visitantes', [Visitantes::class, 'delete']);

// VISITAS - RAZONES DE BÚSQUEDA
Flight::route('POST /visitas-razones-busqueda/multiples', [VisitasRazonesBusqueda::class, 'guardarMultiples']);
Flight::route('GET /visitas-razones-busqueda/por-visita/@id_visita', [VisitasRazonesBusqueda::class, 'getByVisita']);
Flight::route('GET /visitas-razones-busqueda', [VisitasRazonesBusqueda::class, 'getAll']);
Flight::route('GET /visitas-razones-busqueda/@id', [VisitasRazonesBusqueda::class, 'getById']);
Flight::route('POST /visitas-razones-busqueda', [VisitasRazonesBusqueda::class, 'new']);
Flight::route('DELETE /visitas-razones-busqueda', [VisitasRazonesBusqueda::class, 'delete']);

// VISITAS - OBSERVACIONES DISC
Flight::route('POST /visitas-observaciones-disc/multiples', [VisitasObservacionesDisc::class, 'guardarMultiples']);
Flight::route('GET /visitas-observaciones-disc/por-visitante/@id_visitante', [VisitasObservacionesDisc::class, 'getByVisitante']);
Flight::route('GET /visitas-observaciones-disc', [VisitasObservacionesDisc::class, 'getAll']);
Flight::route('GET /visitas-observaciones-disc/@id', [VisitasObservacionesDisc::class, 'getById']);
Flight::route('POST /visitas-observaciones-disc', [VisitasObservacionesDisc::class, 'new']);
Flight::route('PUT /visitas-observaciones-disc', [VisitasObservacionesDisc::class, 'replace']);
Flight::route('DELETE /visitas-observaciones-disc', [VisitasObservacionesDisc::class, 'delete']);

// VISITAS - PERFIL CALCULADO
Flight::route('POST /visitas-perfil-calculado/calcular', [VisitasPerfilCalculado::class, 'calcularPerfil']);
Flight::route('GET /visitas-perfil-calculado/por-visitante/@id_visitante', [VisitasPerfilCalculado::class, 'getByVisitante']);
Flight::route('GET /visitas-perfil-calculado', [VisitasPerfilCalculado::class, 'getAll']);
Flight::route('GET /visitas-perfil-calculado/@id', [VisitasPerfilCalculado::class, 'getById']);
Flight::route('POST /visitas-perfil-calculado', [VisitasPerfilCalculado::class, 'new']);
Flight::route('PUT /visitas-perfil-calculado', [VisitasPerfilCalculado::class, 'replace']);
Flight::route('DELETE /visitas-perfil-calculado', [VisitasPerfilCalculado::class, 'delete']);

// VISITAS - TEMPERATURA
Flight::route('POST /visitas-temperatura/guardar', [VisitasTemperatura::class, 'guardarTemperatura']);
Flight::route('GET /visitas-temperatura/por-visita/@id_visita', [VisitasTemperatura::class, 'getByVisita']);
Flight::route('GET /visitas-temperatura', [VisitasTemperatura::class, 'getAll']);
Flight::route('GET /visitas-temperatura/@id', [VisitasTemperatura::class, 'getById']);
Flight::route('POST /visitas-temperatura', [VisitasTemperatura::class, 'new']);
Flight::route('PUT /visitas-temperatura', [VisitasTemperatura::class, 'replace']);
Flight::route('DELETE /visitas-temperatura', [VisitasTemperatura::class, 'delete']);

// VISITAS - SEGUIMIENTO
Flight::route('GET /visitas-seguimiento/pendientes', [VisitasSeguimiento::class, 'getPendientesSeguimiento']);
Flight::route('POST /visitas-seguimiento/guardar', [VisitasSeguimiento::class, 'guardarSeguimiento']);
Flight::route('GET /visitas-seguimiento/por-visita/@id_visita', [VisitasSeguimiento::class, 'getByVisita']);
Flight::route('GET /visitas-seguimiento', [VisitasSeguimiento::class, 'getAll']);
Flight::route('GET /visitas-seguimiento/@id', [VisitasSeguimiento::class, 'getById']);
Flight::route('POST /visitas-seguimiento', [VisitasSeguimiento::class, 'new']);
Flight::route('PUT /visitas-seguimiento', [VisitasSeguimiento::class, 'replace']);
Flight::route('DELETE /visitas-seguimiento', [VisitasSeguimiento::class, 'delete']);

// VISITAS - COMPROMISOS
Flight::route('GET /visitas-compromisos/proximos', [VisitasCompromisos::class, 'getProximosCompromisos']);
Flight::route('GET /visitas-compromisos/vencidos', [VisitasCompromisos::class, 'getCompromisosVencidos']);
Flight::route('POST /visitas-compromisos/multiples', [VisitasCompromisos::class, 'guardarMultiples']);
Flight::route('GET /visitas-compromisos/por-visita/@id_visita', [VisitasCompromisos::class, 'getByVisita']);
Flight::route('GET /visitas-compromisos', [VisitasCompromisos::class, 'getAll']);
Flight::route('GET /visitas-compromisos/@id', [VisitasCompromisos::class, 'getById']);
Flight::route('POST /visitas-compromisos', [VisitasCompromisos::class, 'new']);
Flight::route('PUT /visitas-compromisos', [VisitasCompromisos::class, 'replace']);
Flight::route('DELETE /visitas-compromisos', [VisitasCompromisos::class, 'delete']);

// ============================================
// TABLAS PRINCIPALES - TAB 2: PROTOCOLO
// ============================================

// VISITAS - PROTOCOLO PASOS COMPLETADOS
Flight::route('GET /visitas-protocolo-pasos-completados/progreso/@id_visita', [VisitasProtocoloPasosCompletados::class, 'getProgresoProtocolo']);
Flight::route('POST /visitas-protocolo-pasos-completados/marcar', [VisitasProtocoloPasosCompletados::class, 'marcarCompletado']);
Flight::route('GET /visitas-protocolo-pasos-completados/por-visita/@id_visita', [VisitasProtocoloPasosCompletados::class, 'getByVisita']);
Flight::route('GET /visitas-protocolo-pasos-completados', [VisitasProtocoloPasosCompletados::class, 'getAll']);
Flight::route('GET /visitas-protocolo-pasos-completados/@id', [VisitasProtocoloPasosCompletados::class, 'getById']);
Flight::route('POST /visitas-protocolo-pasos-completados', [VisitasProtocoloPasosCompletados::class, 'new']);
Flight::route('PUT /visitas-protocolo-pasos-completados', [VisitasProtocoloPasosCompletados::class, 'replace']);
Flight::route('DELETE /visitas-protocolo-pasos-completados', [VisitasProtocoloPasosCompletados::class, 'delete']);

// VISITAS - PROTOCOLO CHECKLIST
Flight::route('POST /visitas-protocolo-checklist/multiples', [VisitasProtocoloChecklist::class, 'guardarMultiples']);
Flight::route('POST /visitas-protocolo-checklist/toggle', [VisitasProtocoloChecklist::class, 'toggleItem']);
Flight::route('GET /visitas-protocolo-checklist/por-visita/@id_visita/paso/@id_protocolo_paso', [VisitasProtocoloChecklist::class, 'getByVisitaYPaso']);
Flight::route('GET /visitas-protocolo-checklist/por-visita/@id_visita', [VisitasProtocoloChecklist::class, 'getByVisita']);
Flight::route('GET /visitas-protocolo-checklist', [VisitasProtocoloChecklist::class, 'getAll']);
Flight::route('GET /visitas-protocolo-checklist/@id', [VisitasProtocoloChecklist::class, 'getById']);
Flight::route('POST /visitas-protocolo-checklist', [VisitasProtocoloChecklist::class, 'new']);
Flight::route('PUT /visitas-protocolo-checklist', [VisitasProtocoloChecklist::class, 'replace']);
Flight::route('DELETE /visitas-protocolo-checklist', [VisitasProtocoloChecklist::class, 'delete']);

// ============================================
// TABLAS PRINCIPALES - TAB 3: OBJECIONES
// ============================================

// VISITAS - OBJECIONES
Flight::route('GET /visitas-objeciones/estadisticas', [VisitasObjeciones::class, 'getEstadisticas']);
Flight::route('POST /visitas-objeciones/multiples', [VisitasObjeciones::class, 'guardarMultiples']);
Flight::route('GET /visitas-objeciones/por-visita/@id_visita', [VisitasObjeciones::class, 'getByVisita']);
Flight::route('GET /visitas-objeciones', [VisitasObjeciones::class, 'getAll']);
Flight::route('GET /visitas-objeciones/@id', [VisitasObjeciones::class, 'getById']);
Flight::route('POST /visitas-objeciones', [VisitasObjeciones::class, 'new']);
Flight::route('PUT /visitas-objeciones', [VisitasObjeciones::class, 'replace']);
Flight::route('DELETE /visitas-objeciones', [VisitasObjeciones::class, 'delete']);

// ============================================
// TABLAS PRINCIPALES - TAB 4: CIERRE Y APRENDIZAJES
// ============================================

// VISITAS - RESULTADO
Flight::route('GET /visitas-resultado/estadisticas', [VisitasResultado::class, 'getEstadisticas']);
Flight::route('POST /visitas-resultado/guardar', [VisitasResultado::class, 'guardarResultado']);
Flight::route('GET /visitas-resultado/por-visita/@id_visita', [VisitasResultado::class, 'getByVisita']);
Flight::route('GET /visitas-resultado', [VisitasResultado::class, 'getAll']);
Flight::route('GET /visitas-resultado/@id', [VisitasResultado::class, 'getById']);
Flight::route('POST /visitas-resultado', [VisitasResultado::class, 'new']);
Flight::route('PUT /visitas-resultado', [VisitasResultado::class, 'replace']);
Flight::route('DELETE /visitas-resultado', [VisitasResultado::class, 'delete']);

// VISITAS - ASPECTOS POSITIVOS
Flight::route('POST /visitas-aspectos-positivos/guardar', [VisitasAspectosPositivos::class, 'guardar']);
Flight::route('GET /visitas-aspectos-positivos/por-visita/@id_visita', [VisitasAspectosPositivos::class, 'getByVisita']);
Flight::route('GET /visitas-aspectos-positivos', [VisitasAspectosPositivos::class, 'getAll']);
Flight::route('GET /visitas-aspectos-positivos/@id', [VisitasAspectosPositivos::class, 'getById']);
Flight::route('POST /visitas-aspectos-positivos', [VisitasAspectosPositivos::class, 'new']);
Flight::route('PUT /visitas-aspectos-positivos', [VisitasAspectosPositivos::class, 'replace']);
Flight::route('DELETE /visitas-aspectos-positivos', [VisitasAspectosPositivos::class, 'delete']);

// VISITAS - DETALLE/OBSEQUIO
Flight::route('POST /visitas-detalle-obsequio/guardar', [VisitasDetalleObsequio::class, 'guardar']);
Flight::route('GET /visitas-detalle-obsequio/por-visita/@id_visita', [VisitasDetalleObsequio::class, 'getByVisita']);
Flight::route('GET /visitas-detalle-obsequio', [VisitasDetalleObsequio::class, 'getAll']);
Flight::route('GET /visitas-detalle-obsequio/@id', [VisitasDetalleObsequio::class, 'getById']);
Flight::route('POST /visitas-detalle-obsequio', [VisitasDetalleObsequio::class, 'new']);
Flight::route('PUT /visitas-detalle-obsequio', [VisitasDetalleObsequio::class, 'replace']);
Flight::route('DELETE /visitas-detalle-obsequio', [VisitasDetalleObsequio::class, 'delete']);

// VISITAS - FEEDBACK PARA MEJORAR
Flight::route('GET /visitas-feedback-mejorar/mas-mencionados', [VisitasFeedbackMejorar::class, 'getAspectosMasMencionados']);
Flight::route('POST /visitas-feedback-mejorar/multiples', [VisitasFeedbackMejorar::class, 'guardarMultiples']);
Flight::route('GET /visitas-feedback-mejorar/por-visita/@id_visita', [VisitasFeedbackMejorar::class, 'getByVisita']);
Flight::route('GET /visitas-feedback-mejorar', [VisitasFeedbackMejorar::class, 'getAll']);
Flight::route('GET /visitas-feedback-mejorar/@id', [VisitasFeedbackMejorar::class, 'getById']);
Flight::route('POST /visitas-feedback-mejorar', [VisitasFeedbackMejorar::class, 'new']);
Flight::route('PUT /visitas-feedback-mejorar', [VisitasFeedbackMejorar::class, 'replace']);
Flight::route('DELETE /visitas-feedback-mejorar', [VisitasFeedbackMejorar::class, 'delete']);

// VISITAS - PERFIL DEL PROSPECTO
Flight::route('POST /visitas-perfil-prospecto/guardar', [VisitasPerfilProspecto::class, 'guardar']);
Flight::route('GET /visitas-perfil-prospecto/por-visita/@id_visita', [VisitasPerfilProspecto::class, 'getByVisita']);
Flight::route('GET /visitas-perfil-prospecto', [VisitasPerfilProspecto::class, 'getAll']);
Flight::route('GET /visitas-perfil-prospecto/@id', [VisitasPerfilProspecto::class, 'getById']);
Flight::route('POST /visitas-perfil-prospecto', [VisitasPerfilProspecto::class, 'new']);
Flight::route('PUT /visitas-perfil-prospecto', [VisitasPerfilProspecto::class, 'replace']);
Flight::route('DELETE /visitas-perfil-prospecto', [VisitasPerfilProspecto::class, 'delete']);

// VISITAS - COMPETENCIA
Flight::route('GET /visitas-competencia/mas-mencionados', [VisitasCompetencia::class, 'getCompetidoresMasMencionados']);
Flight::route('POST /visitas-competencia/guardar', [VisitasCompetencia::class, 'guardar']);
Flight::route('GET /visitas-competencia/por-visita/@id_visita', [VisitasCompetencia::class, 'getByVisita']);
Flight::route('GET /visitas-competencia', [VisitasCompetencia::class, 'getAll']);
Flight::route('GET /visitas-competencia/@id', [VisitasCompetencia::class, 'getById']);
Flight::route('POST /visitas-competencia', [VisitasCompetencia::class, 'new']);
Flight::route('PUT /visitas-competencia', [VisitasCompetencia::class, 'replace']);
Flight::route('DELETE /visitas-competencia', [VisitasCompetencia::class, 'delete']);

// VISITAS - APRENDIZAJES
Flight::route('GET /visitas-aprendizajes/recientes/@limite', [VisitasAprendizajes::class, 'getAprendizajesRecientes']);
Flight::route('POST /visitas-aprendizajes/guardar', [VisitasAprendizajes::class, 'guardar']);
Flight::route('GET /visitas-aprendizajes/por-visita/@id_visita', [VisitasAprendizajes::class, 'getByVisita']);
Flight::route('GET /visitas-aprendizajes', [VisitasAprendizajes::class, 'getAll']);
Flight::route('GET /visitas-aprendizajes/@id', [VisitasAprendizajes::class, 'getById']);
Flight::route('POST /visitas-aprendizajes', [VisitasAprendizajes::class, 'new']);
Flight::route('PUT /visitas-aprendizajes', [VisitasAprendizajes::class, 'replace']);
Flight::route('DELETE /visitas-aprendizajes', [VisitasAprendizajes::class, 'delete']);

Flight::route('GET /visitas-catalogos', [Visitas::class, 'getAllCatalogos']);
Flight::route('POST /visitas/crear-completa', [Visitas::class, 'crearVisitaCompleta']);
Flight::route('PUT /visitas/@id/actualizar-completa', [Visitas::class, 'actualizarVisitaCompleta']);

// =====================================================
// FIN DE RUTAS CRM - VISITAS
// =====================================================

// TIPOS DE PREFERENCIAS DE SEGUIMIENTO
Flight::route('GET /tipos-preferencias-seguimiento', [TiposPreferenciasSeguimiento::class, 'getAll']);
Flight::route('GET /tipos-preferencias-seguimiento/@id', [TiposPreferenciasSeguimiento::class, 'getById']);
Flight::route('POST /tipos-preferencias-seguimiento', [TiposPreferenciasSeguimiento::class, 'new']);
Flight::route('PUT /tipos-preferencias-seguimiento', [TiposPreferenciasSeguimiento::class, 'replace']);
Flight::route('DELETE /tipos-preferencias-seguimiento', [TiposPreferenciasSeguimiento::class, 'delete']);

// VISITAS - PREFERENCIAS DE SEGUIMIENTO
Flight::route('POST /visitas-preferencias-seguimiento/multiples', [VisitasPreferenciasSeguimiento::class, 'guardarMultiples']);
Flight::route('GET /visitas-preferencias-seguimiento/por-visita/@id_visita', [VisitasPreferenciasSeguimiento::class, 'getByVisita']);
Flight::route('GET /visitas-preferencias-seguimiento', [VisitasPreferenciasSeguimiento::class, 'getAll']);
Flight::route('GET /visitas-preferencias-seguimiento/@id', [VisitasPreferenciasSeguimiento::class, 'getById']);
Flight::route('POST /visitas-preferencias-seguimiento', [VisitasPreferenciasSeguimiento::class, 'new']);
Flight::route('DELETE /visitas-preferencias-seguimiento', [VisitasPreferenciasSeguimiento::class, 'delete']);