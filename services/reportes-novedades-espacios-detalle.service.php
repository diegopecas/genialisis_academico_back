<?php
/**
 * Detalle de los reportes de novedades (reportes_novedades_espacios_detalle):
 * una copia de cada novedad tal como salio en el reporte (fecha, cancha,
 * horario, tipo y observaciones). Con ella el PDF se vuelve a generar
 * identico aunque despues alguien cambie la novedad o el nombre de la cancha.
 *
 * La logica del reporte esta en ReportesNovedadesEspacios.
 */
class ReportesNovedadesEspaciosDetalle
{
    /**
     * Guarda la copia de una novedad. Corre dentro de la transaccion de
     * quien genera el reporte.
     *
     * @param array $novedad la que devuelve NovedadesEspacios::paraReporte
     */
    public static function registrar($db, $id_reporte, array $novedad)
    {
        $sentence = $db->prepare("INSERT INTO reportes_novedades_espacios_detalle
            (id, id_tenant, id_reporte_novedades_espacios, id_novedad_espacio, fecha, nombre_espacio,
             hora_inicial, hora_final, nombre_tipo, observaciones)
            VALUES (:id, :id_tenant, :id_reporte, :id_novedad, :fecha, :nombre_espacio,
             :hora_inicial, :hora_final, :nombre_tipo, :observaciones)");
        $sentence->bindValue(':id', Uuid::generar());
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_reporte', $id_reporte);
        $sentence->bindValue(':id_novedad', $novedad['id']);
        $sentence->bindValue(':fecha', $novedad['fecha']);
        $sentence->bindValue(':nombre_espacio', $novedad['nombre_espacio']);
        $sentence->bindValue(':hora_inicial', $novedad['hora_inicial']);
        $sentence->bindValue(':hora_final', $novedad['hora_final']);
        $sentence->bindValue(':nombre_tipo', $novedad['nombre_tipo']);
        $sentence->bindValue(':observaciones', $novedad['observaciones']);
        $sentence->execute();
    }

    /** Las novedades de un reporte, en orden de fecha, cancha y hora */
    public static function listarDeReporte($db, $id_reporte)
    {
        $sentence = $db->prepare("SELECT fecha, nombre_espacio, hora_inicial, hora_final, nombre_tipo, observaciones,
                   TIMESTAMPDIFF(MINUTE, hora_inicial, hora_final) AS minutos
            FROM reportes_novedades_espacios_detalle
            WHERE id_reporte_novedades_espacios = :id_reporte AND id_tenant = :id_tenant
            ORDER BY fecha, nombre_espacio, hora_inicial");
        $sentence->bindValue(':id_reporte', $id_reporte);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
