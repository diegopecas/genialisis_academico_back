<?php
// =============================================
// LLAVE MAESTRA DE CIFRADO DE SECRETOS (CifradoSecretos)
// Cifra las llaves que se guardan en la base: pasarela de pagos y servicio
// de correo. Base: g_fundadores_prod.
// Si se cambia o se pierde, lo cifrado no se puede leer y hay que volver a
// escribir las llaves desde las pantallas de Configuracion.
// No va en el repositorio.
// =============================================
define('LLAVE_CIFRADO_SECRETOS', 'Jp0f8k7HXC4cMAAken26HKvPcr4uTwDk1hgjpoo/p7s=');
