<?php
class NotificacionesAdjuntos
{
    /**
     * Subcarpeta dentro de uploads/{tenant}/ donde viven los archivos de
     * este modulo. Se usa con UploadHelper igual que documentos_personas.
     */
    const SUBCARPETA = 'notificaciones_adjuntos';

    public static function getByNotificacion($id_notificacion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_notificacion, nombre_archivo, tamanio_bytes, fecha_subida, id_usuario_subio FROM notificaciones_adjuntos WHERE id_notificacion = :id_notificacion AND id_tenant = :id_tenant AND activo = 1 ORDER BY fecha_subida");
        $sentence->bindParam(':id_notificacion', $id_notificacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_notificacion, nombre_archivo, tamanio_bytes, fecha_subida FROM notificaciones_adjuntos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Sube un archivo y lo asocia a la notificacion.
     *
     * El tope de tamano no se fija aqui con un numero propio: se lee de la
     * configuracion de PHP, que es el limite que realmente manda. Asi el
     * usuario recibe un mensaje claro en vez de un error en blanco cuando
     * el servidor rechaza el POST.
     */
    public static function subir()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuarioSubio = $userData->data->id ?? null;

            $idNotificacion = $_POST['id_notificacion'] ?? null;

            if (!$idNotificacion) {
                Flight::json(array('error' => 'id_notificacion es obligatorio'), 400);
                return;
            }

            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error'), 400);
                return;
            }

            $verificar = $db->prepare("SELECT id FROM notificaciones WHERE id = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $idNotificacion);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();

            if (!$verificar->fetch()) {
                Flight::json(array('error' => 'La notificación no existe'), 404);
                return;
            }

            $archivo = $_FILES['archivo'];
            $nombreOriginal = $archivo['name'];
            $tamanioBytes = $archivo['size'];
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            $extensionesPermitidas = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');
            if (!in_array($extension, $extensionesPermitidas)) {
                Flight::json(array('error' => 'Extensión de archivo no permitida'), 400);
                return;
            }

            $limiteBytes = self::getLimiteSubidaBytes();
            if ($limiteBytes > 0 && $tamanioBytes > $limiteBytes) {
                Flight::json(array(
                    'error' => 'El archivo supera el máximo permitido por el servidor (' . round($limiteBytes / 1048576, 1) . ' MB)'
                ), 400);
                return;
            }

            $directorioBase = UploadHelper::getUploadPath(self::SUBCARPETA) . $idNotificacion . '/';
            UploadHelper::ensureDirectoryExists($directorioBase);

            $nombreArchivo = time() . '_' . uniqid() . '.' . $extension;
            $rutaCompleta = $directorioBase . $nombreArchivo;
            $rutaRelativa = UploadHelper::getRelativePath(self::SUBCARPETA, $idNotificacion . '/' . $nombreArchivo);

            if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
                Flight::json(array('error' => 'Error al guardar el archivo'), 500);
                return;
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO notificaciones_adjuntos
                    (id, id_tenant, id_notificacion, nombre_archivo, ruta_archivo, tamanio_bytes, id_usuario_subio)
                VALUES
                    (:id, :id_tenant, :id_notificacion, :nombre_archivo, :ruta_archivo, :tamanio_bytes, :id_usuario_subio)
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_notificacion', $idNotificacion);
            $sentence->bindParam(':nombre_archivo', $nombreOriginal);
            $sentence->bindParam(':ruta_archivo', $rutaRelativa);
            $sentence->bindValue(':tamanio_bytes', $tamanioBytes, PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario_subio', $idUsuarioSubio);
            $sentence->execute();

            Flight::json(array(
                'id'             => $idNew,
                'nombre_archivo' => $nombreOriginal,
                'tamanio_bytes'  => $tamanioBytes,
            ));
        } catch (Exception $e) {
            error_log("Error en NotificacionesAdjuntos::subir: " . $e->getMessage());
            Flight::json(array('error' => 'Error al subir el adjunto'), 500);
        }
    }

    /**
     * Entrega el archivo.
     *
     * No se sirve por URL directa a la carpeta: estos adjuntos llevan datos
     * de menores. Un usuario del portal de padres solo descarga si figura
     * como destinatario de esa notificacion; el institucional descarga
     * cualquiera de su tenant.
     */
    public static function descargar($id)
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $idUsuario = $userData->data->id ?? null;
            $portal = isset($userData->portal) ? $userData->portal : JWTService::PORTAL_INSTITUCIONAL;

            $sentence = $db->prepare("SELECT id, id_notificacion, nombre_archivo, ruta_archivo FROM notificaciones_adjuntos WHERE id = :id AND id_tenant = :id_tenant AND activo = 1");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $adjunto = $sentence->fetch();

            if (!$adjunto) {
                Flight::json(array('error' => 'Adjunto no encontrado'), 404);
                return;
            }

            if ($portal === JWTService::PORTAL_PADRES) {
                $autorizar = $db->prepare("
                    SELECT d.id
                    FROM notificaciones_destinatarios d
                    WHERE d.id_notificacion = :id_notificacion
                      AND d.id_usuario = :id_usuario
                      AND d.id_tenant = :id_tenant
                    LIMIT 1
                ");
                $autorizar->bindValue(':id_notificacion', $adjunto['id_notificacion']);
                $autorizar->bindParam(':id_usuario', $idUsuario);
                $autorizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $autorizar->execute();

                if (!$autorizar->fetch()) {
                    Flight::json(array('error' => 'No tiene acceso a este adjunto'), 403);
                    return;
                }
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
            error_log("Error en NotificacionesAdjuntos::descargar: " . $e->getMessage());
            Flight::json(array('error' => 'Error al descargar el adjunto'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'ID es obligatorio'), 400);
                return;
            }

            $sentence = $db->prepare("SELECT ruta_archivo FROM notificaciones_adjuntos WHERE id = :id AND id_tenant = :id_tenant");
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

            $borrar = $db->prepare("DELETE FROM notificaciones_adjuntos WHERE id = :id AND id_tenant = :id_tenant");
            $borrar->bindParam(':id', $id);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en NotificacionesAdjuntos::delete: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el adjunto'), 500);
        }
    }

    /**
     * Devuelve el tope real de subida en bytes, tomando el menor entre
     * upload_max_filesize y post_max_size.
     *
     * @return int Bytes, o 0 si PHP no declara limite
     */
    private static function getLimiteSubidaBytes()
    {
        $uploadMax = self::convertirAEnteroBytes(ini_get('upload_max_filesize'));
        $postMax   = self::convertirAEnteroBytes(ini_get('post_max_size'));

        $limites = array_filter(array($uploadMax, $postMax), function ($valor) {
            return $valor > 0;
        });

        return count($limites) > 0 ? min($limites) : 0;
    }

    /**
     * Traduce valores tipo '32M' u '8G' de php.ini a bytes.
     *
     * @param  string $valor
     * @return int
     */
    private static function convertirAEnteroBytes($valor)
    {
        $valor = trim((string)$valor);

        if ($valor === '') {
            return 0;
        }

        $unidad = strtolower(substr($valor, -1));
        $numero = (int)$valor;

        switch ($unidad) {
            case 'g':
                return $numero * 1024 * 1024 * 1024;
            case 'm':
                return $numero * 1024 * 1024;
            case 'k':
                return $numero * 1024;
            default:
                return $numero;
        }
    }
}
