<?php
/**
 * Catálogo de tipos de reporte por correo. Cada tipo tiene su armado en
 * MotorReportesCorreo según su código, por eso no tiene CRUD desde pantalla:
 * los tipos nuevos entran por script cuando se programan.
 */
class TiposReporteCorreo
{
    /**
     * GET /tipos-reporte-correo
     * Tipos activos del tenant, para escoger al asociar un reporte a un colaborador.
     */
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, codigo, nombre, descripcion, alcance
                                      FROM tipos_reporte_correo
                                      WHERE activo = 1 AND id_tenant = :id_tenant
                                      ORDER BY nombre");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TiposReporteCorreo::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron consultar los tipos de reporte'), 500);
        }
    }
}
