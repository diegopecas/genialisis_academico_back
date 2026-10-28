<?php
// =============================================
// LLAVE MAESTRA DE CIFRADO DE SECRETOS (CifradoSecretos)
// Cifra las llaves que se guardan en la base: pasarela de pagos y servicio
// de correo. Base: g_fundadores_qa (la misma de config y config_qa).
// Si se cambia o se pierde, lo cifrado no se puede leer y hay que volver a
// escribir las llaves desde las pantallas de Configuracion.
// No va en el repositorio.
// =============================================
define('LLAVE_CIFRADO_SECRETOS', '5WORf3BSNDXB44Y0B6JtrnuZRtLhFOt6mzNxdQJHQko=');
