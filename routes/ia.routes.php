<?php

// IA-CHAT
Flight::route('POST /ia-chat/enviar', [IaChat::class, 'enviarMensaje']);
Flight::route('GET /ia-chat/conversaciones/@id_persona', [IaChat::class, 'listarConversaciones']);
Flight::route('GET /ia-chat/conversacion/@id_conversacion', [IaChat::class, 'obtenerConversacion']);
Flight::route('DELETE /ia-chat/conversacion/@id_conversacion', [IaChat::class, 'eliminarConversacion']);
Flight::route('GET /ia-chat/admin/log', [IaChat::class, 'obtenerLog']);
Flight::route('GET /ia-chat/acceso-institucional/@id_persona', [IaChat::class, 'verificarAccesoInstitucional']);
Flight::route('GET /ia-chat/acceso-padres/@id_persona', [IaChat::class, 'verificarAccesoPadres']);

// IA-MENSAJES
Flight::route('POST /ia/mensaje-personalizado', [IaMensajes::class, 'obtenerMensajePersonalizado']);
Flight::route('GET /ia/estadisticas', [IaMensajes::class, 'obtenerEstadisticas']);
Flight::route('PUT /ia/configuracion', [IaMensajes::class, 'actualizarConfiguracion']);

// IA-CONFIGURACION (CRUD admin)
Flight::route('GET /ia-configuracion', [IaConfiguracion::class, 'getAll']);
Flight::route('GET /ia-configuracion/@id', [IaConfiguracion::class, 'getById']);
Flight::route('PUT /ia-configuracion', [IaConfiguracion::class, 'replace']);

// GINI
Flight::route('POST /gini/session', [Gini::class, 'generarSesion']);

// IA-COBERTURA CURRICULAR
Flight::route('GET /ia-cobertura-curricular/obtener', [IaCoberturaCurricular::class, 'obtenerAnalisisGuardado']);
Flight::route('POST /ia-cobertura-curricular/guardar', [IaCoberturaCurricular::class, 'guardarAnalisis']);
Flight::route('POST /ia-cobertura-curricular/analizar', [IaCoberturaCurricular::class, 'analizarCobertura']);

// IA-MÁQUINA DE ACTIVIDADES
Flight::route('POST /ia-maquina-actividades/generar', [IaMaquinaActividades::class, 'generarActividades']);
Flight::route('POST /ia-maquina-actividades/grabar', [IaMaquinaActividades::class, 'grabarActividades']);
Flight::route('POST /ia-maquina-actividades/sugerir-individual', [IaMaquinaActividades::class, 'sugerirIndividual']);

// IA-MÁQUINA DE ACTIVIDADES DE EVALUACIÓN (por logros del corte)
Flight::route('POST /ia-maquina-actividades/logros-evaluacion', [IaMaquinaActividades::class, 'obtenerLogrosEvaluacion']);
Flight::route('GET /ia-maquina-actividades/actividades-por-logro/@id_sprint', [IaMaquinaActividades::class, 'obtenerActividadesPorLogroEnSprint']);
Flight::route('POST /ia-maquina-actividades/generar-evaluacion', [IaMaquinaActividades::class, 'generarActividadesEvaluacion']);
Flight::route('POST /ia-maquina-actividades/grabar-evaluacion', [IaMaquinaActividades::class, 'grabarActividadesEvaluacion']);

// IA-MEJORAR TEXTO
Flight::route('POST /ia-mejorar-texto/mejorar', [IaMejorarTexto::class, 'mejorar']);

// IA-TRANSCRIPCION AUDIO
Flight::route('POST /ia-transcripcion-audio/transcribir', [IaTranscripcionAudio::class, 'transcribir']);