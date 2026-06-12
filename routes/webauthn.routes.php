<?php
// Registro de biométrico (requiere JWT)
Flight::route('POST /webauthn/registro/opciones', [WebAuthn::class, 'generarOpcionesRegistro']);
Flight::route('POST /webauthn/registro/verificar', [WebAuthn::class, 'verificarRegistro']);
 
// Autenticación con biométrico CON tenant (requiere tenant configurado)
Flight::route('POST /webauthn/auth/opciones', [WebAuthn::class, 'generarOpcionesAutenticacion']);
Flight::route('POST /webauthn/auth/verificar', [WebAuthn::class, 'verificarAutenticacion']);
 
// Utilidades (requieren tenant)
Flight::route('POST /webauthn/disponible', [WebAuthn::class, 'verificarDisponibilidad']);
Flight::route('GET /webauthn/credenciales', [WebAuthn::class, 'listarCredenciales']);
Flight::route('DELETE /webauthn/credenciales', [WebAuthn::class, 'eliminarCredencial']);
