<?php
class Galerias
{
    /**
     * Obtener todas las galerías (para admin)
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion, thumbnail, fecha, es_publica, activo, orden 
            FROM galerias 
            WHERE id_tenant = :id_tenant
            ORDER BY orden, fecha DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener galerías activas (públicas)
     */
    public static function getActivas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion, thumbnail, fecha, es_publica, orden 
            FROM galerias 
            WHERE activo = 1 
            AND id_tenant = :id_tenant
            ORDER BY orden, fecha DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener galería por ID
     */
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, descripcion, thumbnail, fecha, es_publica, activo, orden 
            FROM galerias 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    /**
     * Obtener galerías visibles para un acudiente (MANTENER POR COMPATIBILIDAD)
     * Incluye: públicas + privadas de los grupos de sus estudiantes
     */
    public static function getByAcudiente($id_persona)
    {
        $db = Flight::db();
        
        $sentence = $db->prepare("
            SELECT DISTINCT 
                g.id, 
                g.nombre, 
                g.descripcion, 
                g.thumbnail, 
                g.fecha, 
                g.es_publica, 
                g.orden,
                CASE 
                    WHEN g.thumbnail IS NULL OR g.thumbnail = '' THEN (
                        SELECT gi.guid 
                        FROM galeria_imagenes gi 
                        WHERE gi.id_galeria = g.id 
                        LIMIT 1
                    )
                    ELSE NULL 
                END AS primera_imagen_guid
            FROM galerias g
            WHERE g.activo = 1
            AND g.id_tenant = :id_tenant
            AND (
                g.es_publica = 1
                OR g.id IN (
                    SELECT gxg.id_galeria
                    FROM galerias_x_grupos gxg
                    INNER JOIN estudiantes_x_grupos exg ON gxg.id_grupo = exg.id_grupo
                    INNER JOIN acudientes a ON exg.id_estudiante = a.id_estudiante
                    WHERE a.id_persona = :id_persona
                    AND exg.activo = 1
                )
            )
            AND EXISTS (
                SELECT 1 FROM galeria_imagenes gi WHERE gi.id_galeria = g.id
            )
            ORDER BY g.orden, g.fecha DESC
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener galerías visibles para un usuario del portal de padres
     * Combina acceso como acudiente + acceso como docente
     * 
     * @param int $id_persona ID de la persona
     * @param int $id_docente ID del docente (0 si no es docente)
     */
    public static function getByUsuarioPortal($id_persona, $id_docente)
    {
        $db = Flight::db();
        
        $id_docente = (!empty($id_docente) && $id_docente !== '0') ? $id_docente : null;

        // Query base: públicas + acceso como acudiente
        $sql = "
            SELECT DISTINCT 
                g.id, 
                g.nombre, 
                g.descripcion, 
                g.thumbnail, 
                g.fecha, 
                g.es_publica, 
                g.orden,
                CASE 
                    WHEN g.thumbnail IS NULL OR g.thumbnail = '' THEN (
                        SELECT gi.guid 
                        FROM galeria_imagenes gi 
                        WHERE gi.id_galeria = g.id 
                        LIMIT 1
                    )
                    ELSE NULL 
                END AS primera_imagen_guid
            FROM galerias g
            WHERE g.activo = 1
            AND g.id_tenant = :id_tenant
            AND (
                g.es_publica = 1
                OR g.id IN (
                    SELECT gxg.id_galeria
                    FROM galerias_x_grupos gxg
                    INNER JOIN estudiantes_x_grupos exg ON gxg.id_grupo = exg.id_grupo
                    INNER JOIN acudientes a ON exg.id_estudiante = a.id_estudiante
                    WHERE a.id_persona = :id_persona
                    AND exg.activo = 1
                )
        ";

        // Si es docente, agregar acceso por sus grupos
        if ($id_docente !== null) {
            $sql .= "
                OR g.id IN (
                    SELECT gxg2.id_galeria
                    FROM galerias_x_grupos gxg2
                    INNER JOIN docentes_x_grupos dxg ON gxg2.id_grupo = dxg.id_grupo
                    WHERE dxg.id_docente = :id_docente
                    AND dxg.activo = 1
                )
            ";
        }

        $sql .= "
            )
            AND EXISTS (
                SELECT 1 FROM galeria_imagenes gi WHERE gi.id_galeria = g.id
            )
            ORDER BY g.orden, g.fecha DESC
        ";

        $sentence = $db->prepare($sql);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        
        if ($id_docente !== null) {
            $sentence->bindParam(':id_docente', $id_docente);
        }

        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener galería completa con subgalerías e imágenes
     * Valida acceso del usuario (acudiente y/o docente)
     * 
     * @param int $id_galeria ID de la galería
     * @param int $id_persona ID de la persona
     * @param int $id_docente ID del docente (0 si no es docente)
     */
    public static function getFullByIdForUsuario($id_galeria, $id_persona, $id_docente)
    {
        $db = Flight::db();
        
        $id_docente = (!empty($id_docente) && $id_docente !== '0') ? $id_docente : null;

        // Verificar que el usuario tiene acceso a esta galería (acudiente o docente)
        $sqlAccess = "
            SELECT g.id
            FROM galerias g
            WHERE g.id = :id_galeria
            AND g.activo = 1
            AND g.id_tenant = :id_tenant
            AND (
                g.es_publica = 1
                OR g.id IN (
                    SELECT gxg.id_galeria
                    FROM galerias_x_grupos gxg
                    INNER JOIN estudiantes_x_grupos exg ON gxg.id_grupo = exg.id_grupo
                    INNER JOIN acudientes a ON exg.id_estudiante = a.id_estudiante
                    WHERE a.id_persona = :id_persona
                    AND exg.activo = 1
                )
        ";

        if ($id_docente !== null) {
            $sqlAccess .= "
                OR g.id IN (
                    SELECT gxg2.id_galeria
                    FROM galerias_x_grupos gxg2
                    INNER JOIN docentes_x_grupos dxg ON gxg2.id_grupo = dxg.id_grupo
                    WHERE dxg.id_docente = :id_docente
                    AND dxg.activo = 1
                )
            ";
        }

        $sqlAccess .= ")";

        $checkAccess = $db->prepare($sqlAccess);
        $checkAccess->bindParam(':id_galeria', $id_galeria);
        $checkAccess->bindParam(':id_persona', $id_persona);
        $checkAccess->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if ($id_docente !== null) {
            $checkAccess->bindParam(':id_docente', $id_docente);
        }

        $checkAccess->execute();
        
        if (!$checkAccess->fetch()) {
            Flight::json(['error' => 'No tienes acceso a esta galería'], 403);
            return;
        }

        // Obtener datos de la galería
        $galeriaStmt = $db->prepare("
            SELECT id, nombre, descripcion, thumbnail, fecha, es_publica 
            FROM galerias 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $galeriaStmt->bindParam(':id', $id_galeria);
        $galeriaStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $galeriaStmt->execute();
        $galeria = $galeriaStmt->fetch(PDO::FETCH_ASSOC);

        // Obtener subgalerías
        $subgaleriasStmt = $db->prepare("
            SELECT id, nombre, orden 
            FROM subgalerias 
            WHERE id_galeria = :id_galeria 
            AND id_tenant = :id_tenant
            ORDER BY orden
        ");
        $subgaleriasStmt->bindParam(':id_galeria', $id_galeria);
        $subgaleriasStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $subgaleriasStmt->execute();
        $subgalerias = $subgaleriasStmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener imágenes generales (sin subgalería)
        $imagenesStmt = $db->prepare("
            SELECT id, guid, url, alt, orden 
            FROM galeria_imagenes 
            WHERE id_galeria = :id_galeria 
            AND id_subgaleria IS NULL 
            AND id_tenant = :id_tenant
            ORDER BY orden
        ");
        $imagenesStmt->bindParam(':id_galeria', $id_galeria);
        $imagenesStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $imagenesStmt->execute();
        $galeria['images'] = $imagenesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener imágenes de cada subgalería
        foreach ($subgalerias as &$sub) {
            $subImagenesStmt = $db->prepare("
                SELECT id, guid, url, alt, orden 
                FROM galeria_imagenes 
                WHERE id_subgaleria = :id_subgaleria 
                AND id_tenant = :id_tenant
                ORDER BY orden
            ");
            $subImagenesStmt->bindParam(':id_subgaleria', $sub['id']);
            $subImagenesStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $subImagenesStmt->execute();
            $sub['images'] = $subImagenesStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $galeria['subgalerias'] = $subgalerias;

        Flight::json($galeria);
    }

    /**
     * Obtener galería completa (MANTENER POR COMPATIBILIDAD con admin u otros)
     */
    public static function getFullByIdForAcudiente($id_galeria, $id_persona)
    {
        // Redirigir al nuevo método con id_docente = 0
        self::getFullByIdForUsuario($id_galeria, $id_persona, 0);
    }

    /**
     * Crear nueva galería
     */
    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $fecha = $data['fecha'];
        $es_publica = isset($data['es_publica']) ? $data['es_publica'] : 1;
        $activo = isset($data['activo']) ? $data['activo'] : 1;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("
            INSERT INTO galerias (id, id_tenant, nombre, descripcion, thumbnail, fecha, es_publica, activo, orden) 
            VALUES (:id, :id_tenant, :nombre, :descripcion, :thumbnail, :fecha, :es_publica, :activo, :orden)
        ");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':thumbnail', $thumbnail);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':es_publica', $es_publica);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':orden', $orden);
        $sentence->execute();
        
        $id = $idNew;
        Flight::json(['id' => $id]);
    }

    /**
     * Actualizar galería
     */
    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        $id = $data['id'];
        $nombre = $data['nombre'];
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $fecha = $data['fecha'];
        $es_publica = isset($data['es_publica']) ? $data['es_publica'] : 1;
        $activo = isset($data['activo']) ? $data['activo'] : 1;
        $orden = isset($data['orden']) ? $data['orden'] : 0;
        
        $sentence = $db->prepare("
            UPDATE galerias 
            SET nombre = :nombre, 
                descripcion = :descripcion, 
                thumbnail = :thumbnail, 
                fecha = :fecha, 
                es_publica = :es_publica, 
                activo = :activo, 
                orden = :orden 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':thumbnail', $thumbnail);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':es_publica', $es_publica);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':orden', $orden);
        $sentence->execute();
        
        self::getById($id);
    }

    /**
     * Eliminar galería
     */
    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM galerias WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        Flight::json(['deleted' => true, 'id' => $id]);
    }
}