<?php
/**
 * Catalogo de tipos de mora. Es una tabla GLOBAL (sin id_tenant): los tipos
 * son transversales a todas las instituciones, igual que periodicidad_cobro.
 * Solo lectura desde la aplicacion; se administra por script.
 */
class TiposMora
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, codigo, nombre, descripcion, activo
                FROM tipos_mora
                WHERE activo = 1
                ORDER BY id
            ");
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en tipos_mora getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener los tipos de mora'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, codigo, nombre, descripcion, activo
                FROM tipos_mora
                WHERE id = :id
            ");
            $sentence->bindValue(':id', $id, PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en tipos_mora getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el tipo de mora'), 500);
        }
    }

    /**
     * Devuelve el id del tipo a partir de su codigo. Lo usa el motor para no
     * depender de ids literales.
     *
     * @param PDO    $db
     * @param string $codigo
     * @return int|null
     */
    public static function idPorCodigo($db, $codigo)
    {
        $sentence = $db->prepare("SELECT id FROM tipos_mora WHERE codigo = :codigo LIMIT 1");
        $sentence->bindParam(':codigo', $codigo);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int) $fila['id'] : null;
    }

    /**
     * Devuelve el codigo a partir del id.
     *
     * @param PDO      $db
     * @param int|null $id
     * @return string|null
     */
    public static function codigoPorId($db, $id)
    {
        if ($id === null || $id === '') {
            return null;
        }

        $sentence = $db->prepare("SELECT codigo FROM tipos_mora WHERE id = :id LIMIT 1");
        $sentence->bindValue(':id', $id, PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return $fila ? $fila['codigo'] : null;
    }
}
