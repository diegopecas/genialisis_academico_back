<?php
Flight::route('GET /permisos/arbol', [PermisosRol::class, 'getArbol']);
Flight::route('GET /permisos/rol/@id', [PermisosRol::class, 'getPermisosByRol']);
Flight::route('GET /permisos/roles', [PermisosRol::class, 'getRoles']);
Flight::route('GET /permisos/portales', [PermisosRol::class, 'getPortales']);
Flight::route('POST /permisos/rol/@id', [PermisosRol::class, 'guardarPermisos']);