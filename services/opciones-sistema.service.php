<?php
/**
 * Servicio para gestionar opciones_sistema en la BD maestra.
 * Se usa para la documentación del sistema (descripcion, imagenes, tags).
 */
class OpcionesSistema
{
    /**
     * Obtener todas las opciones con sus permisos asociados
     */
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db_master();
        $sentence = $db->prepare("
            SELECT 
                os.id, os.id_padre, os.nombre, os.ruta, os.ruta_principal,
                os.descripcion, os.descripcion_texto,
                os.componente, os.icono, os.orden, 
                os.imagenes, os.tags, os.portal, os.activo,
                padre.nombre AS nombre_padre,
                GROUP_CONCAT(p.codigo ORDER BY p.codigo SEPARATOR ', ') AS permisos_asociados
            FROM opciones_sistema os
            LEFT JOIN opciones_sistema padre ON os.id_padre = padre.id
            LEFT JOIN permisos p ON p.id_modulo = os.id
            GROUP BY 
                os.id, os.id_padre, os.nombre, os.ruta, os.ruta_principal,
                os.descripcion, os.descripcion_texto,
                os.componente, os.icono, os.orden,
                os.imagenes, os.tags, os.portal, os.activo,
                padre.nombre
            ORDER BY os.id_padre, os.orden, os.nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Obtener una opción por ID con sus permisos
     */
    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        $db = Flight::db_master();
        $sentence = $db->prepare("
            SELECT 
                os.id, os.id_padre, os.nombre, os.ruta, os.ruta_principal,
                os.descripcion, os.descripcion_texto,
                os.componente, os.icono, os.orden,
                os.imagenes, os.tags, os.portal, os.activo,
                padre.nombre AS nombre_padre
            FROM opciones_sistema os
            LEFT JOIN opciones_sistema padre ON os.id_padre = padre.id
            WHERE os.id = :id
        ");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Actualizar SOLO los campos de documentación de una opción.
     * No toca: nombre, ruta, componente, icono, orden, portal, activo.
     */
    public static function updateDocumentacion()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db_master();
            $request = Flight::request();

            $id = $request->data['id'];
            $descripcion = isset($request->data['descripcion']) ? $request->data['descripcion'] : null;
            $descripcion_texto = isset($request->data['descripcion_texto']) ? $request->data['descripcion_texto'] : null;
            $imagenes = isset($request->data['imagenes']) ? $request->data['imagenes'] : null;
            $tags = isset($request->data['tags']) ? $request->data['tags'] : null;

            // Si imagenes viene como array, convertir a JSON
            if (is_array($imagenes)) {
                $imagenes = json_encode($imagenes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $sentence = $db->prepare("
                UPDATE opciones_sistema SET
                    descripcion = :descripcion,
                    descripcion_texto = :descripcion_texto,
                    imagenes = :imagenes,
                    tags = :tags
                WHERE id = :id
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindParam(':descripcion_texto', $descripcion_texto);
            $sentence->bindParam(':imagenes', $imagenes);
            $sentence->bindParam(':tags', $tags);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la opción con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en OpcionesSistema::updateDocumentacion: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}