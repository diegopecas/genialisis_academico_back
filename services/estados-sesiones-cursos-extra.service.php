<?php
/**
 * Estados de una sesion de curso extra: PROGRAMADA, DICTADA, CANCELADA.
 *
 * Catalogo global (sin id_tenant). El codigo se apoya siempre en el codigo,
 * nunca en el nombre ni en el id.
 */
class EstadosSesionesCursosExtra
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, orden, activo
            FROM estados_sesiones_cursos_extra
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
        $sentence = $db->prepare("SELECT id FROM estados_sesiones_cursos_extra WHERE codigo = :codigo");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila['id'] : null;
    }
}
