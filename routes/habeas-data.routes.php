<?php
// AUTORIZACIONES HABEAS DATA
Flight::route('GET /autorizaciones-habeas-data/usuario/@id_usuario', [AutorizacionesHabeasData::class, 'getByUsuario']);
Flight::route('GET /autorizaciones-habeas-data/verificar/@id_usuario', [AutorizacionesHabeasData::class, 'verificar']);
Flight::route('GET /autorizaciones-habeas-data/plantilla', [AutorizacionesHabeasData::class, 'getPlantilla']);
Flight::route('POST /autorizaciones-habeas-data', [AutorizacionesHabeasData::class, 'new']);