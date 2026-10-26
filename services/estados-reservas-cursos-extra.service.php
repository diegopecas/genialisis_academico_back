<?php
/**
 * Estados de una reserva de sesion: RESERVADA, ASISTIO, NO_ASISTIO,
 * CANCELADA, REAGENDADA.
 *
 * Catalogo global (sin id_tenant). Si un estado descuenta o no del paquete
 * no se guarda aqui: ASISTIO siempre descuenta, NO_ASISTIO solo si el curso
 * tiene la regla descuenta_inasistencia, y los demas nunca.
 */
class EstadosReservasCursosExtra
{
    // Estados que ocupan un puesto en la sesion (cuentan contra el cupo).
    const OCUPAN_CUPO = array('RESERVADA', 'ASISTIO', 'NO_ASISTIO');

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, orden, activo
            FROM estados_reservas_cursos_extra
            WHERE activo = 1
            ORDER BY orden
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Id del estado a partir de su codigo, o null si no existe.
     */
    public static function idPorCodigo($db, $codigo)
    {
        $sentence = $db->prepare("SELECT id FROM estados_reservas_cursos_extra WHERE codigo = :codigo");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila['id'] : null;
    }
}
