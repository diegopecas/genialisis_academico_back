<?php
Flight::route('POST /auth/webauthn/opciones', [WebAuthn::class, 'generarOpcionesLoginDirecto']);
Flight::route('POST /auth/webauthn/verificar', [WebAuthn::class, 'verificarLoginDirecto']);