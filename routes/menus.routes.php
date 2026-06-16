<?php
// MENÚS
Flight::route('GET /menus', ['Menus', 'getAll']);
Flight::route('GET /menus/@id', ['Menus', 'getById']);
Flight::route('POST /menus', ['Menus', 'new']);
Flight::route('PUT /menus', ['Menus', 'replace']);
Flight::route('DELETE /menus', ['Menus', 'delete']);

// Items del menú
Flight::route('GET /menus/@id/items', ['Menus', 'getItemsByMenu']);
Flight::route('POST /menus/@id/items', ['Menus', 'asignarItems']);

// Productos/Servicios del menú
Flight::route('GET /menus/@id/productos-servicios', ['Menus', 'getProductosServiciosByMenu']);
Flight::route('POST /menus/@id/productos-servicios', ['Menus', 'asignarProductosServicios']);

// Minutas del menú
Flight::route('GET /menus/@id/minutas', ['MenuMinutas', 'getByMenu']);
Flight::route('POST /menus/@id/minutas', ['MenuMinutas', 'asignar']);
Flight::route('POST /menus/@id/minutas/verificar', ['MenuMinutas', 'verificarConflictos']);

// Minuta completa (vista general)
Flight::route('GET /menu-minutas', ['MenuMinutas', 'getAll']);
Flight::route('GET /menu-minutas/completa', ['MenuMinutas', 'getMinutaCompleta']);

// ITEMS DE MENÚ
Flight::route('GET /items-menu', ['ItemsMenu', 'getAll']);
Flight::route('GET /items-menu/@id', ['ItemsMenu', 'getById']);
Flight::route('POST /items-menu', ['ItemsMenu', 'new']);
Flight::route('PUT /items-menu', ['ItemsMenu', 'replace']);
Flight::route('DELETE /items-menu', ['ItemsMenu', 'delete']);

// Ingredientes del item
Flight::route('GET /items-menu/@id/ingredientes', ['ItemsMenu', 'getIngredientesByItem']);
Flight::route('POST /items-menu/@id/ingredientes', ['ItemsMenu', 'asignarIngredientes']);

// Items disponibles para un menú
Flight::route('GET /items-menu/disponibles/@id_menu', ['ItemsMenu', 'getItemsDisponiblesParaMenu']);

// PORCIONES
Flight::route('GET /porciones', ['Porciones', 'getAll']);
Flight::route('GET /porciones/@id', ['Porciones', 'getById']);
Flight::route('POST /porciones', ['Porciones', 'new']);
Flight::route('PUT /porciones', ['Porciones', 'replace']);
Flight::route('DELETE /porciones', ['Porciones', 'delete']);
Flight::route('GET /porciones-activas', ['Porciones', 'getActivas']);


// Clasificación de menús
Flight::route('GET /clasificacion-menus', [ClasificacionMenus::class, 'getAll']);
Flight::route('GET /clasificacion-menus/@id', [ClasificacionMenus::class, 'getById']);