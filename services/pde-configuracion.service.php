<?php

class PdeConfiguracion
{
    // Configuracion vigente del tenant. Si no existe fila, responde 404 en lugar de inventar valores.
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, umbral_verde, umbral_amarillo, margen_esfera_baja, tope_indice, avisar_al_rojo, activo
            FROM pde_configuracion
            WHERE id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        if (!$response) {
            Flight::json(array('error' => 'No existe configuracion PDE para este tenant'), 404);
            return;
        }

        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, umbral_verde, umbral_amarillo, margen_esfera_baja, tope_indice, avisar_al_rojo, activo
            FROM pde_configuracion
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $umbral_verde = Flight::request()->data['umbral_verde'];
        $umbral_amarillo = Flight::request()->data['umbral_amarillo'];
        $margen_esfera_baja = Flight::request()->data['margen_esfera_baja'];
        $tope_indice = Flight::request()->data['tope_indice'];
        $avisar_al_rojo = Flight::request()->data['avisar_al_rojo'];

        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO pde_configuracion (
                id, id_tenant, umbral_verde, umbral_amarillo, margen_esfera_baja, tope_indice, avisar_al_rojo, activo
            ) VALUES (
                :id, :id_tenant, :umbral_verde, :umbral_amarillo, :margen_esfera_baja, :tope_indice, :avisar_al_rojo, 1
            )
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':umbral_verde', $umbral_verde);
        $sentence->bindParam(':umbral_amarillo', $umbral_amarillo);
        $sentence->bindParam(':margen_esfera_baja', $margen_esfera_baja);
        $sentence->bindParam(':tope_indice', $tope_indice);
        $sentence->bindParam(':avisar_al_rojo', $avisar_al_rojo);
        $sentence->execute();

        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $umbral_verde = Flight::request()->data['umbral_verde'];
        $umbral_amarillo = Flight::request()->data['umbral_amarillo'];
        $margen_esfera_baja = Flight::request()->data['margen_esfera_baja'];
        $tope_indice = Flight::request()->data['tope_indice'];
        $avisar_al_rojo = Flight::request()->data['avisar_al_rojo'];

        $sentence = $db->prepare("
            UPDATE pde_configuracion
            SET umbral_verde = :umbral_verde,
                umbral_amarillo = :umbral_amarillo,
                margen_esfera_baja = :margen_esfera_baja,
                tope_indice = :tope_indice,
                avisar_al_rojo = :avisar_al_rojo
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':umbral_verde', $umbral_verde);
        $sentence->bindParam(':umbral_amarillo', $umbral_amarillo);
        $sentence->bindParam(':margen_esfera_baja', $margen_esfera_baja);
        $sentence->bindParam(':tope_indice', $tope_indice);
        $sentence->bindParam(':avisar_al_rojo', $avisar_al_rojo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    // Helper interno usado por PdeAplicaciones. Lanza excepcion si el tenant no tiene configuracion.
    public static function obtenerVigente($db)
    {
        $sentence = $db->prepare("
            SELECT umbral_verde, umbral_amarillo, margen_esfera_baja, tope_indice, avisar_al_rojo
            FROM pde_configuracion
            WHERE id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $config = $sentence->fetch();

        if (!$config) {
            throw new Exception('No existe configuracion PDE para este tenant');
        }

        return array(
            'umbral_verde' => (int)$config['umbral_verde'],
            'umbral_amarillo' => (int)$config['umbral_amarillo'],
            'margen_esfera_baja' => (int)$config['margen_esfera_baja'],
            'tope_indice' => (int)$config['tope_indice'],
            'avisar_al_rojo' => (int)$config['avisar_al_rojo']
        );
    }
}
