<?php
/**
 * Instituciones cliente: las instituciones a las que el tenant le presta un
 * servicio (por ejemplo las clases extracurriculares que JC dicta en otros
 * jardines y colegios).
 *
 * Sigue el mismo patron de Proveedores: la tabla solo guarda el vinculo y el
 * estado, mientras que la razon social, el NIT, la direccion, la ciudad, el
 * telefono y el correo viven en `personas`. Ser institucion cliente es un rol
 * que una persona cumple dentro del tenant, no una entidad aparte.
 *
 * No confundir con la clase Instituciones: esa es la institucion DUENA del
 * tenant (el jardin mismo), unica por tenant.
 */
class InstitucionesCliente
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ic.id, ic.id_persona, ic.id_tipo_institucion, ic.activo, ic.fecha_registro,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
        p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion,
        p.telefono, p.correo_electronico, p.id_ciudad, c.nombre nombre_ciudad,
        tin.nombre nombre_tipo_institucion, p.razon_social,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo
        FROM instituciones_cliente ic
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE ic.id_tenant = :id_tenant
        ORDER BY ic.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ic.id, ic.id_persona, ic.id_tipo_institucion, ic.activo, ic.fecha_registro,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
        p.numero_identificacion, p.razon_social, p.telefono, p.correo_electronico,
        tin.nombre nombre_tipo_institucion,
        CASE
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo
        FROM instituciones_cliente ic
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        WHERE ic.activo = 1
        AND ic.id_tenant = :id_tenant
        ORDER BY nombre_completo");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ic.id,
               ic.id_persona,
               ic.id_tipo_institucion,
               ic.activo,
               ic.fecha_registro,
               p.primer_nombre,
               p.segundo_nombre,
               p.primer_apellido,
               p.segundo_apellido,
               p.id_tipo_identificacion,
               ti.nombre AS tipo_identificacion,
               p.numero_identificacion,
               p.fecha_nacimiento,
               p.id_genero,
               g.nombre AS nombre_genero,
               p.direccion,
               p.telefono,
               p.correo_electronico,
               p.nacionalidad,
               p.id_ciudad,
               c.nombre AS nombre_ciudad,
               p.razon_social,
               p.ocupacion,
               tin.nombre AS nombre_tipo_institucion,
               CASE
                   WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
                   ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                              IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
               END AS nombre_completo
        FROM instituciones_cliente ic
        INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE ic.id = :id
        AND ic.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_institucion = Flight::request()->data['id_tipo_institucion'];

            error_log("Datos recibidos para crear institucion cliente: id_persona=$id_persona, id_tipo_institucion=$id_tipo_institucion");

            if (!$id_persona || !$id_tipo_institucion) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // El indice unico uq_instituciones_cliente ya lo impide, pero
            // reventaria con un error de base. Aqui sale un mensaje que se
            // entiende.
            $verif = $db->prepare("SELECT id FROM instituciones_cliente
                                   WHERE id_persona = :id_persona AND id_tenant = :id_tenant LIMIT 1");
            $verif->bindParam(':id_persona', $id_persona);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();

            if ($verif->fetch()) {
                Flight::json(array('error' => 'Esta persona ya está registrada como institución cliente.'), 400);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO instituciones_cliente(
                id,
                id_tenant,
                id_persona,
                id_tipo_institucion,
                activo,
                fecha_registro
            ) VALUES (
                :id,
                :id_tenant,
                :id_persona,
                :id_tipo_institucion,
                1,
                NOW()
            )");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_institucion', $id_tipo_institucion);
            $ok = $sentence->execute();

            if (!$ok) {
                error_log("Error: no se pudo insertar la institucion cliente.");
                Flight::json(array('error' => 'No se pudo crear la institución cliente.'), 500);
                return;
            }

            error_log("ID institucion cliente insertado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_institucion = Flight::request()->data['id_tipo_institucion'];
            $activo = Flight::request()->data['activo'];

            if (!$id || !$id_persona || !$id_tipo_institucion) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE instituciones_cliente SET
                                    id_persona = :id_persona,
                                    id_tipo_institucion = :id_tipo_institucion,
                                    activo = :activo
                                    WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_institucion', $id_tipo_institucion);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en la ejecución del método replace de instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar la institución cliente.'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID de la institución cliente a eliminar'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM instituciones_cliente WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método delete de instituciones cliente: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar la institución cliente.'), 500);
        }
    }

    /**
     * Dice si la persona ya esta registrada como institucion cliente en el
     * tenant. Lo consume el formulario al verificar el documento, para no
     * dejar crear el mismo registro dos veces.
     */
    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Verificando duplicados para institucion cliente: id_persona=$id_persona");

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM instituciones_cliente
                                  WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function getByTipo($id_tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ic.id, ic.id_persona, ic.id_tipo_institucion, ic.activo, ic.fecha_registro,
            p.numero_identificacion, p.razon_social, p.telefono, p.correo_electronico,
            tin.nombre as tipo_institucion_nombre,
            CASE
                WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
                ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ',
                           IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
            END AS nombre_completo
            FROM instituciones_cliente ic
            INNER JOIN personas p ON ic.id_persona = p.id AND p.id_tenant = ic.id_tenant
            INNER JOIN tipos_institucion tin ON ic.id_tipo_institucion = tin.id
            WHERE ic.id_tipo_institucion = :id_tipo AND ic.activo = 1 AND ic.id_tenant = :id_tenant
            ORDER BY nombre_completo");
        $sentence->bindParam(':id_tipo', $id_tipo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
