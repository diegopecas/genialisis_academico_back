<?php
// ===================================================================
// CLAVE SECRETA JWT - NO VERSIONAR (agregar a .gitignore)
//
// Debe ser IDENTICA en todos los entornos para que un token firmado en
// uno se valide en cualquier otro. Se mantiene el mismo valor que estaba
// hardcodeado en jwt.service.php para no invalidar los tokens vigentes.
// ===================================================================
define('JWT_SECRET_KEY', 'LuM3n_4c4d3m1c0_2024_S3cr3t_K3y_Pr0t3ct3d_G4l3r14s_X7k9Lm2Pq');
