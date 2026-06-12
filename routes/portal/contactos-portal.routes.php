<?php
// ===================================================================
// RUTAS DE CONTACTOS DEL PORTAL
// ===================================================================

// Obtener todos los contactos
Flight::route('GET /contactos-portal', [ContactosPortal::class, 'getAll']);

// Obtener contacto por ID
Flight::route('GET /contactos-portal/@id', [ContactosPortal::class, 'getById']);

// Actualizar contacto (solo campos editables)
Flight::route('PUT /contactos-portal', [ContactosPortal::class, 'replace']);

// Obtener catálogos
Flight::route('GET /contactos-portal-catalogos', [ContactosPortal::class, 'getCatalogos']);

// Obtener estadísticas
Flight::route('GET /contactos-portal-estadisticas', [ContactosPortal::class, 'getEstadisticas']);