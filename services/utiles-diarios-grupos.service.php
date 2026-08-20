<?php
class UtilesDiariosGrupos
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT eg.id, eg.id_util_diario, eg.id_grupo, g.nombre AS nombre_grupo
                                  FROM utiles_diarios_grupos eg
                                  INNER JOIN grupos g ON g.id = eg.id_grupo
                                  WHERE eg.id_tenant = :id_tenant
                                  ORDER BY g.orden, g.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Grupos asociados a un útil. Si devuelve vacío, el útil aplica a
     * todos los grupos: esa es la convención del módulo y evita registrar las
     * combinaciones de los útiles comunes como la agenda.
     */
    public static function getByUtil($id_util)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT eg.id, eg.id_util_diario, eg.id_grupo, g.nombre AS nombre_grupo
                                  FROM utiles_diarios_grupos eg
                                  INNER JOIN grupos g ON g.id = eg.id_grupo
                                  WHERE eg.id_util_diario = :id_util
                                  AND eg.id_tenant = :id_tenant
                                  ORDER BY g.orden, g.nombre");
        $sentence->bindParam(':id_util', $id_util);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_util_diario, id_grupo FROM utiles_diarios_grupos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_util_diario = Flight::request()->data['id_util_diario'];
        $id_grupo = Flight::request()->data['id_grupo'];

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO utiles_diarios_grupos (id, id_tenant, id_util_diario, id_grupo) VALUES (:id, :id_tenant, :id_util_diario, :id_grupo)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_util_diario', $id_util_diario);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Reemplaza de una vez los grupos de un útil. Es lo que usa el
     * formulario del catálogo, donde la usuaria marca varios grupos y graba:
     * mandar la lista completa evita tener que ir borrando y agregando uno
     * por uno desde el front.
     *
     * Una lista vacía deja el útil sin restricciones, o sea aplicando a
     * todos los grupos.
     */
    public static function replaceGruposUtil()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_util_diario = Flight::request()->data['id_util_diario'];
        $grupos = isset(Flight::request()->data['grupos']) ? Flight::request()->data['grupos'] : array();

        if (empty($id_util_diario)) {
            Flight::json(array('error' => 'El útil es obligatorio'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("DELETE FROM utiles_diarios_grupos WHERE id_util_diario = :id_util_diario AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_util_diario', $id_util_diario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if (is_array($grupos) && count($grupos) > 0) {
                $sentence = $db->prepare("INSERT INTO utiles_diarios_grupos (id, id_tenant, id_util_diario, id_grupo) VALUES (:id, :id_tenant, :id_util_diario, :id_grupo)");
                foreach ($grupos as $id_grupo) {
                    if (empty($id_grupo)) {
                        continue;
                    }
                    $idNew = Uuid::generar();
                    $sentence->bindValue(':id', $idNew);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindValue(':id_util_diario', $id_util_diario);
                    $sentence->bindValue(':id_grupo', $id_grupo);
                    $sentence->execute();
                }
            }

            $db->commit();
            Flight::json(array('id_util_diario' => $id_util_diario));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en UtilesDiariosGrupos::replaceGruposUtil: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron guardar los grupos del útil'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM utiles_diarios_grupos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}
