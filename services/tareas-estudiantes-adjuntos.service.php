<?php
/*=============================================
SERVICIO - TAREAS ESTUDIANTES ADJUNTOS
Archivo: services/tareas-estudiantes-adjuntos.service.php

Archivos de la tarea. Mismo patron de notificaciones_adjuntos: se guardan
en uploads/{tenant}/tareas_estudiantes_adjuntos/{id_tarea}/ y no se sirven
por URL directa, porque son del menor; se entregan por descargar().
=============================================*/

class TareasEstudiantesAdjuntos
{
    const SUBCARPETA = 'tareas_estudiantes_adjuntos';
    const EXTENSIONES_PERMITIDAS = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');

    /**
     * @return array Adjuntos activos de la tarea (sin la ruta fisica)
     */
    public static function listar(PDO $db, $idTarea)
    {
        $sentence = $db->prepare("SELECT id, id_tarea_estudiante, nombre_archivo, tamanio_bytes, fecha_subida
                                  FROM tareas_estudiantes_adjuntos
                                  WHERE id_tarea_estudiante = :id AND id_tenant = :id_tenant AND activo = 1
                                  ORDER BY fecha_subida");
        $sentence->bindParam(':id', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    public static function subir()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, TareasEstudiantes::PERMISO);

            $db = Flight::db();
            $idTarea = $_POST['id_tarea_estudiante'] ?? null;

            if (!$idTarea) {
                Flight::json(array('error' => 'id_tarea_estudiante es obligatorio'), 400);
                return;
            }

            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error'), 400);
                return;
            }

            if (!TareasEstudiantes::obtenerTarea($db, $idTarea)) {
                Flight::json(array('error' => 'La tarea no existe'), 404);
                return;
            }

            $archivo = $_FILES['archivo'];
            $nombreOriginal = $archivo['name'];
            $tamanioBytes = $archivo['size'];
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            if (!in_array($extension, self::EXTENSIONES_PERMITIDAS)) {
                Flight::json(array('error' => 'Extensión de archivo no permitida'), 400);
                return;
            }

            $directorio = UploadHelper::getUploadPath(self::SUBCARPETA) . $idTarea . '/';
            UploadHelper::ensureDirectoryExists($directorio);

            $nombreArchivo = time() . '_' . uniqid() . '.' . $extension;
            $rutaRelativa = UploadHelper::getRelativePath(self::SUBCARPETA, $idTarea . '/' . $nombreArchivo);

            if (!move_uploaded_file($archivo['tmp_name'], $directorio . $nombreArchivo)) {
                Flight::json(array('error' => 'Error al guardar el archivo'), 500);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO tareas_estudiantes_adjuntos
                    (id, id_tenant, id_tarea_estudiante, nombre_archivo, ruta_archivo, tamanio_bytes, id_usuario_subio)
                VALUES
                    (:id, :id_tenant, :id_tarea, :nombre_archivo, :ruta_archivo, :tamanio_bytes, :id_usuario_subio)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tarea', $idTarea);
            $sentence->bindParam(':nombre_archivo', $nombreOriginal);
            $sentence->bindParam(':ruta_archivo', $rutaRelativa);
            $sentence->bindValue(':tamanio_bytes', $tamanioBytes, PDO::PARAM_INT);
            $sentence->bindValue(':id_usuario_subio', $userData->id ?? null);
            $sentence->execute();

            Flight::json(array('id' => $id, 'nombre_archivo' => $nombreOriginal, 'tamanio_bytes' => $tamanioBytes));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesAdjuntos::subir: " . $e->getMessage());
            Flight::json(array('error' => 'Error al subir el adjunto'), 500);
        }
    }

    /**
     * Entrega el archivo. En el portal de padres exige id_estudiante (query)
     * y que ese niño sea del acudiente y este asignado a la tarea publicada.
     */
    public static function descargar($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $db = Flight::db();

            $sentence = $db->prepare("SELECT id_tarea_estudiante, nombre_archivo, ruta_archivo
                                      FROM tareas_estudiantes_adjuntos
                                      WHERE id = :id AND id_tenant = :id_tenant AND activo = 1");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $adjunto = $sentence->fetch();

            if (!$adjunto) {
                Flight::json(array('error' => 'Adjunto no encontrado'), 404);
                return;
            }

            $idEstudiante = Flight::request()->query['id_estudiante'] ?? null;
            if (!TareasEstudiantes::esDestinatario($db, $userData, $adjunto['id_tarea_estudiante'], $idEstudiante)) {
                Flight::json(array('error' => 'No tiene acceso a este adjunto'), 403);
                return;
            }

            $rutaFisica = __DIR__ . '/../' . $adjunto['ruta_archivo'];
            if (!file_exists($rutaFisica)) {
                Flight::json(array('error' => 'El archivo no existe en el servidor'), 404);
                return;
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($adjunto['nombre_archivo']) . '"');
            header('Content-Length: ' . filesize($rutaFisica));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($rutaFisica);
            exit;
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesAdjuntos::descargar: " . $e->getMessage());
            Flight::json(array('error' => 'Error al descargar el adjunto'), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, TareasEstudiantes::PERMISO);

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $sentence = $db->prepare("SELECT ruta_archivo FROM tareas_estudiantes_adjuntos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $adjunto = $sentence->fetch();

            if (!$adjunto) {
                Flight::json(array('error' => 'Adjunto no encontrado'), 404);
                return;
            }

            $rutaFisica = __DIR__ . '/../' . $adjunto['ruta_archivo'];
            if (file_exists($rutaFisica)) {
                unlink($rutaFisica);
            }

            $borrar = $db->prepare("DELETE FROM tareas_estudiantes_adjuntos WHERE id = :id AND id_tenant = :id_tenant");
            $borrar->bindParam(':id', $id);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TareasEstudiantesAdjuntos::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el adjunto'), 500);
        }
    }
}
