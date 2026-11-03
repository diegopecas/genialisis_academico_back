<?php
// =============================================
// LÍNEAS DE WHATSAPP DEL TENANT (tenants_whatsapp_config en la master)
// =============================================
Flight::route('GET /wa-lineas', ['WaLineas', 'getAll']);
Flight::route('PUT /wa-lineas/nombre', ['WaLineas', 'updateNombre']);
