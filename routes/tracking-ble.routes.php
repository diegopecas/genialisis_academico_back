<?php
// ===================================================================
// TRACKING BLE - Rutas
// ===================================================================

// Tipos de eventos tracking (catálogo)
Flight::route('GET /tipos-eventos-tracking', ['TiposEventosTracking', 'getAll']);
Flight::route('GET /tipos-eventos-tracking-activos', ['TiposEventosTracking', 'getActivos']);
Flight::route('GET /tipos-eventos-tracking/@id', ['TiposEventosTracking', 'getById']);

// Dispositivos BLE (ESP32 Gateways)
Flight::route('GET /dispositivos-ble', ['DispositivosBle', 'getAll']);
Flight::route('GET /dispositivos-ble-activos', ['DispositivosBle', 'getActivos']);
Flight::route('GET /dispositivos-ble/@id', ['DispositivosBle', 'getById']);
Flight::route('POST /dispositivos-ble', ['DispositivosBle', 'create']);
Flight::route('PUT /dispositivos-ble', ['DispositivosBle', 'update']);
Flight::route('DELETE /dispositivos-ble', ['DispositivosBle', 'delete']);
Flight::route('PUT /dispositivos-ble/regenerar-key/@id', ['DispositivosBle', 'regenerarApiKey']);

// Beacons BLE (Manillas)
Flight::route('GET /beacons-ble', ['BeaconsBle', 'getAll']);
Flight::route('GET /beacons-ble-activos', ['BeaconsBle', 'getActivos']);
Flight::route('GET /beacons-ble/@id', ['BeaconsBle', 'getById']);
Flight::route('POST /beacons-ble', ['BeaconsBle', 'create']);
Flight::route('PUT /beacons-ble', ['BeaconsBle', 'update']);
Flight::route('DELETE /beacons-ble', ['BeaconsBle', 'delete']);

// Tracking BLE (registro de eventos)
Flight::route('GET /tracking-ble', ['TrackingBle', 'getAll']);
Flight::route('GET /tracking-ble/@id', ['TrackingBle', 'getById']);

// Reporte desde ESP32 (endpoint IoT)
Flight::route('POST /tracking-ble/reporte', ['TrackingBle', 'recibirReporte']);

// Consultas de ubicación (desde Angular)
Flight::route('GET /tracking-ble/ubicacion-actual', ['TrackingBle', 'getUbicacionActual']);
Flight::route('GET /tracking-ble/ubicacion-area/@id_area', ['TrackingBle', 'getUbicacionPorArea']);
Flight::route('GET /tracking-ble/historial/@id_beacon', ['TrackingBle', 'getHistorialBeacon']);
Flight::route('GET /tracking-ble/resumen-zonas', ['TrackingBle', 'getResumenPorZona']);