<?php
class ElementosInventarioGrupos
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT eg.id, eg.id_elemento_inventario, eg.id_grupo, g.nombre AS nombre_grupo
                                  FROM elementos_inventario_grupos eg
                                  INNER JOIN grupos g ON g.id = eg.id_grupo
                                  WHERE eg.id_tenant = :id_tenant
                                  ORDER BY g.orden, g.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Grupos asociados a un elemento. Si devuelve vacío, el elemento aplica a
     * todos los grupos: esa es la convención del módulo y evita registrar las
     * combinaciones de los elementos comunes como la agenda.
     */
    public static function getByElemento($id_elemento)
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT eg.id, eg.id_elemento_inventario, eg.id_grupo, g.nombre AS nombre_grupo
                                  FROM elementos_inventario_grupos eg
                                  INNER JOIN grupos g ON g.id = eg.id_grupo
                                  WHERE eg.id_elemento_inventario = :id_elemento
                                  AND eg.id_tenant = :id_tenant
                                  ORDER BY g.orden, g.nombre");
        $sentence->bindParam(':id_elemento', $id_elemento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_elemento_inventario, id_grupo FROM elementos_inventario_grupos WHERE id = :id AND id_tenant = :id_tenant");
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

        $id_elemento_inventario = Flight::request()->data['id_elemento_inventario'];
        $id_grupo = Flight::request()->data['id_grupo'];

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO elementos_inventario_grupos (id, id_tenant, id_elemento_inventario, id_grupo) VALUES (:id, :id_tenant, :id_elemento_inventario, :id_grupo)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_elemento_inventario', $id_elemento_inventario);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Reemplaza de una vez los grupos de un elemento. Es lo que usa el
     * formulario del catálogo, donde la usuaria marca varios grupos y graba:
     * mandar la lista completa evita tener que ir borrando y agregando uno
     * por uno desde el front.
     *
     * Una lista vacía deja el elemento sin restricciones, o sea aplicando a
     * todos los grupos.
     */
    public static function replaceGruposElemento()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id_elemento_inventario = Flight::request()->data['id_elemento_inventario'];
        $grupos = isset(Flight::request()->data['grupos']) ? Flight::request()->data['grupos'] : array();

        if (empty($id_elemento_inventario)) {
            Flight::json(array('error' => 'El elemento es obligatorio'), 400);
            return;
        }

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("DELETE FROM elementos_inventario_grupos WHERE id_elemento_inventario = :id_elemento_inventario AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_elemento_inventario', $id_elemento_inventario);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if (is_array($grupos) && count($grupos) > 0) {
                $sentence = $db->prepare("INSERT INTO elementos_inventario_grupos (id, id_tenant, id_elemento_inventario, id_grupo) VALUES (:id, :id_tenant, :id_elemento_inventario, :id_grupo)");
                foreach ($grupos as $id_grupo) {
                    if (empty($id_grupo)) {
                        continue;
                    }
                    $idNew = Uuid::generar();
                    $sentence->bindValue(':id', $idNew);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindValue(':id_elemento_inventario', $id_elemento_inventario);
                    $sentence->bindValue(':id_grupo', $id_grupo);
                    $sentence->execute();
                }
            }

            $db->commit();
            Flight::json(array('id_elemento_inventario' => $id_elemento_inventario));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ElementosInventarioGrupos::replaceGruposElemento: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudieron guardar los grupos del elemento'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM elementos_inventario_grupos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}
