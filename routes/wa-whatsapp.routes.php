<?php
// =============================================
// CONTACTOS DE WHATSAPP
// =============================================
Flight::route('GET /wa-contactos', ['WaContactos', 'getAll']);
Flight::route('GET /wa-contactos/@id', ['WaContactos', 'getById']);
Flight::route('GET /wa-contactos/phone/@phone', ['WaContactos', 'getByPhone']);
Flight::route('POST /wa-contactos', ['WaContactos', 'new']);
Flight::route('PUT /wa-contactos/persona', ['WaContactos', 'updatePersona']);
Flight::route('POST /wa-contactos/find-or-create', ['WaContactos', 'findOrCreate']);

// =============================================
// CONVERSACIONES DE WHATSAPP
// =============================================
Flight::route('GET /wa-conversaciones', ['WaConversaciones', 'getAll']);
Flight::route('GET /wa-conversaciones/activas', ['WaConversaciones', 'getActivas']);
Flight::route('GET /wa-conversaciones/panel', ['WaConversaciones', 'getAllConUltimoMensaje']);
Flight::route('GET /wa-conversaciones/@id', ['WaConversaciones', 'getById']);
Flight::route('POST /wa-conversaciones', ['WaConversaciones', 'new']);
Flight::route('PUT /wa-conversaciones/cerrar', ['WaConversaciones', 'cerrar']);

// =============================================
// MENSAJES DE WHATSAPP
// =============================================
Flight::route('GET /wa-mensajes', ['WaMensajes', 'getAll']);
Flight::route('GET /wa-mensajes/no-leidos', ['WaMensajes', 'getNoLeidos']);
Flight::route('GET /wa-mensajes/sin-responder', ['WaMensajes', 'getSinResponder']);
Flight::route('GET /wa-mensajes/conversacion/@id_conversacion', ['WaMensajes', 'getByConversacion']);
Flight::route('GET /wa-mensajes/conversacion/@id_conversacion/anteriores/@antes_de_id', ['WaMensajes', 'getAnteriores']);
Flight::route('GET /wa-mensajes/conversacion/@id_conversacion/nuevos/@desde_id', ['WaMensajes', 'getNuevosDesde']);
Flight::route('PUT /wa-mensajes/estado', ['WaMensajes', 'updateEstado']);
Flight::route('PUT /wa-mensajes/respondido', ['WaMensajes', 'marcarRespondido']);
Flight::route('PUT /wa-mensajes/conversacion/@id_conversacion/leida', ['WaMensajes', 'marcarConversacionLeida']);
Flight::route('PUT /wa-mensajes/etiquetas', ['WaMensajes', 'updateEtiquetas']);
Flight::route('POST /wa-mensajes', ['WaMensajes', 'new']);

// =============================================
// ENVÍO DE MENSAJES
// =============================================
Flight::route('POST /wa-enviar/texto', ['WaEnviarMensaje', 'enviarTexto']);
Flight::route('POST /wa-enviar/imagen', ['WaEnviarMensaje', 'enviarImagen']);
Flight::route('POST /wa-enviar/documento', ['WaEnviarMensaje', 'enviarDocumento']);
Flight::route('POST /wa-enviar/archivo', ['WaEnviarMensaje', 'enviarArchivo']);
Flight::route('POST /wa-enviar/template', ['WaEnviarMensaje', 'enviarTemplate']);

// =============================================
// CONFIGURACIÓN WHATSAPP (clave-valor del tenant)
// =============================================
Flight::route('GET /wa-config/etiqueta-remitente/@id_persona', ['WaConfig', 'getEtiquetaRemitente']);
Flight::route('GET /wa-config/@clave', ['WaConfig', 'getByClave']);
Flight::route('GET /wa-config', ['WaConfig', 'getAll']);
Flight::route('PUT /wa-config', ['WaConfig', 'update']);

// =============================================
// SUSCRIPCIONES PUSH NOTIFICATIONS
// =============================================
Flight::route('POST /wa-push-subscriptions', ['WaPushSubscriptions', 'registrar']);
Flight::route('DELETE /wa-push-subscriptions', ['WaPushSubscriptions', 'eliminar']);


// =============================================
// TEMPLATES DE WHATSAPP (Graph API de Meta)
// =============================================
Flight::route('GET /wa-templates', ['WaTemplates', 'getAll']);
Flight::route('GET /wa-templates/aprobados', ['WaTemplates', 'getAprobados']);
Flight::route('POST /wa-templates', ['WaTemplates', 'create']);
Flight::route('DELETE /wa-templates/@name', ['WaTemplates', 'delete']);

// =============================================
// ONBOARDING DE WHATSAPP (Embedded Signup)
// =============================================
Flight::route('GET /wa-onboarding/estado', ['WaOnboarding', 'getEstado']);
Flight::route('POST /wa-onboarding/procesar', ['WaOnboarding', 'procesarOnboarding']);
Flight::route('POST /wa-onboarding/desconectar', ['WaOnboarding', 'desconectar']);