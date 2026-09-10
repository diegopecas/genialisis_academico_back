<?php
class InformesSecciones
{
    /**
     * Secciones del informe del jardín.
     *
     * El nombre del origen no vive en esta tabla: se resuelve con LEFT JOIN
     * contra esferas, áreas o tipos de observación según tipo_origen, para
     * no duplicar datos ni tener que tocar la malla curricular.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT s.id, s.nombre, s.orden, s.id_seccion_padre, s.tipo_origen, s.id_origen,
                   s.se_califica, s.tipo_contenido, s.evalua_a, s.requiere_inscripcion, s.activo,
                   padre.nombre AS nombre_seccion_padre,
                   CASE s.tipo_origen
                       WHEN 'esfera'      THEN esf.nombre
                       WHEN 'area'        THEN ar.nombre
                       WHEN 'observacion' THEN tob.nombre
                       ELSE NULL
                   END AS nombre_origen
            FROM informes_secciones s
            LEFT JOIN informes_secciones padre ON s.id_seccion_padre = padre.id
            LEFT JOIN esferas_desarrollo esf ON s.tipo_origen = 'esfera' AND s.id_origen = esf.id
            LEFT JOIN areas_academicas ar ON s.tipo_origen = 'area' AND s.id_origen = ar.id
            LEFT JOIN tipos_observaciones_estudiantes tob ON s.tipo_origen = 'observacion' AND s.id_origen = tob.id
            WHERE s.id_tenant = :id_tenant
            ORDER BY COALESCE(padre.orden, s.orden), s.id_seccion_padre IS NOT NULL, s.orden");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, orden, id_seccion_padre, tipo_origen, id_origen,
                   se_califica, tipo_contenido, evalua_a, requiere_inscripcion, activo
            FROM informes_secciones
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Secciones que pueden ser padre: solo las de primer nivel, para que la
     * jerarquía no pase de dos niveles.
     */
    public static function getPosiblesPadres()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, orden
            FROM informes_secciones
            WHERE id_tenant = :id_tenant AND id_seccion_padre IS NULL
            ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $id_seccion_padre = Flight::request()->data['id_seccion_padre'] ?? null;
        $tipo_origen = Flight::request()->data['tipo_origen'] ?? 'esfera';
        $id_origen = Flight::request()->data['id_origen'] ?? null;
        $se_califica = Flight::request()->data['se_califica'] ?? 1;
        $tipo_contenido = Flight::request()->data['tipo_contenido'] ?? 'escala';
        $evalua_a = Flight::request()->data['evalua_a'] ?? 'estudiante';
        $requiere_inscripcion = Flight::request()->data['requiere_inscripcion'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        if ($nombre === null || $nombre === '') {
            Flight::json(array('error' => 'El nombre de la sección es obligatorio'), 400);
            return;
        }

        $sentence = $db->prepare("INSERT INTO informes_secciones(
                id, id_tenant, nombre, orden, id_seccion_padre, tipo_origen, id_origen,
                se_califica, tipo_contenido, evalua_a, requiere_inscripcion, activo
            ) VALUES (
                :id, :id_tenant, :nombre, :orden, :id_seccion_padre, :tipo_origen, :id_origen,
                :se_califica, :tipo_contenido, :evalua_a, :requiere_inscripcion, :activo
            )");

        $idNew = Uuid::generar();
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentence->bindValue(':id_seccion_padre', $id_seccion_padre);
        $sentence->bindParam(':tipo_origen', $tipo_origen);
        $sentence->bindValue(':id_origen', $id_origen);
        $sentence->bindValue(':se_califica', $se_califica, PDO::PARAM_INT);
        $sentence->bindParam(':tipo_contenido', $tipo_contenido);
        $sentence->bindParam(':evalua_a', $evalua_a);
        $sentence->bindValue(':requiere_inscripcion', $requiere_inscripcion, PDO::PARAM_INT);
        $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $orden = Flight::request()->data['orden'] ?? 0;
        $id_seccion_padre = Flight::request()->data['id_seccion_padre'] ?? null;
        $tipo_origen = Flight::request()->data['tipo_origen'] ?? 'esfera';
        $id_origen = Flight::request()->data['id_origen'] ?? null;
        $se_califica = Flight::request()->data['se_califica'] ?? 1;
        $tipo_contenido = Flight::request()->data['tipo_contenido'] ?? 'escala';
        $evalua_a = Flight::request()->data['evalua_a'] ?? 'estudiante';
        $requiere_inscripcion = Flight::request()->data['requiere_inscripcion'] ?? 0;
        $activo = Flight::request()->data['activo'] ?? 1;

        if ($nombre === null || $nombre === '') {
            Flight::json(array('error' => 'El nombre de la sección es obligatorio'), 400);
            return;
        }

        // Una sección no puede ser su propia madre
        if ($id_seccion_padre === $id) {
            Flight::json(array('error' => 'Una sección no puede ser su propia sección padre'), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE informes_secciones SET
                nombre = :nombre,
                orden = :orden,
                id_seccion_padre = :id_seccion_padre,
                tipo_origen = :tipo_origen,
                id_origen = :id_origen,
                se_califica = :se_califica,
                tipo_contenido = :tipo_contenido,
                evalua_a = :evalua_a,
                requiere_inscripcion = :requiere_inscripcion,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
        $sentence->bindValue(':id_seccion_padre', $id_seccion_padre);
        $sentence->bindParam(':tipo_origen', $tipo_origen);
        $sentence->bindValue(':id_origen', $id_origen);
        $sentence->bindValue(':se_califica', $se_califica, PDO::PARAM_INT);
        $sentence->bindParam(':tipo_contenido', $tipo_contenido);
        $sentence->bindParam(':evalua_a', $evalua_a);
        $sentence->bindValue(':requiere_inscripcion', $requiere_inscripcion, PDO::PARAM_INT);
        $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        // Las subsecciones y los ítems caen por CASCADE
        $sentence = $db->prepare("DELETE FROM informes_secciones WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
