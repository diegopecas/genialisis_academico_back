<?php
/*=============================================
SERVICIO - TIPOS DE SOLICITUD
Archivo: services/tipos-solicitud.service.php

Catalogo del jardin. Aqui vive TODA la parametrizacion del modulo: el tipo
no trae logica quemada, trae banderas que el resto de los servicios leen.
Agregar un tipo nuevo no exige tocar codigo.
=============================================*/

class TiposSolicitud
{
    /** manejo_horas */
    const HORAS_NINGUNA = 0;
    const HORAS_UNA     = 1;
    const HORAS_VARIAS  = 2;

    /** documento */
    const DOC_NO         = 0;
    const DOC_OPCIONAL   = 1;
    const DOC_OBLIGATORIO = 2;

    /**
     * Catalogo completo, activos e inactivos. Es la grilla de administracion:
     * el tipo inactivo se sigue viendo aqui para que el jardin aprenda como
     * queda configurado, aunque no se pueda escoger al crear una solicitud.
     */
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden, activo,
                                         requiere_aprobacion, manejo_horas, documento,
                                         minutos_anticipacion, requiere_confirmacion,
                                         notifica_acudiente_cumplido, exige_responsable,
                                         titular_es_responsable, titular_es_aprobador
                                  FROM tipos_solicitud
                                  WHERE id_tenant = :id_tenant
                                  ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Tipos que se pueden escoger al crear una solicitud. Solo los activos:
     * un tipo inactivo esta en el catalogo como ejemplo, no para usarse.
     */
    public static function getActivos()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden,
                                         requiere_aprobacion, manejo_horas, documento,
                                         minutos_anticipacion, requiere_confirmacion,
                                         notifica_acudiente_cumplido, exige_responsable,
                                         titular_es_responsable, titular_es_aprobador
                                  FROM tipos_solicitud
                                  WHERE id_tenant = :id_tenant AND activo = 1
                                  ORDER BY orden, nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, descripcion, icono, orden, activo,
                                         requiere_aprobacion, manejo_horas, documento,
                                         minutos_anticipacion, requiere_confirmacion,
                                         notifica_acudiente_cumplido, exige_responsable,
                                         titular_es_responsable, titular_es_aprobador
                                  FROM tipos_solicitud
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Lectura interna, sin responder al cliente. La usan los servicios que
     * necesitan las banderas para decidir (crear solicitud, generar
     * ocurrencias, marcar cumplida).
     *
     * @param  PDO    $db
     * @param  string $id
     * @return array|null Fila del tipo o null si no existe en el tenant
     */
    public static function obtener(PDO $db, $id)
    {
        $sentence = $db->prepare("SELECT * FROM tipos_solicitud WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila : null;
    }

    public static function new()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $datos = self::leerDatos();

        if ($datos['error']) {
            Flight::json(array('error' => $datos['error']), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO tipos_solicitud
            (id, id_tenant, nombre, descripcion, icono, orden, activo,
             requiere_aprobacion, manejo_horas, documento, minutos_anticipacion,
             requiere_confirmacion, notifica_acudiente_cumplido, exige_responsable,
             titular_es_responsable, titular_es_aprobador)
            VALUES
            (:id, :id_tenant, :nombre, :descripcion, :icono, :orden, :activo,
             :requiere_aprobacion, :manejo_horas, :documento, :minutos_anticipacion,
             :requiere_confirmacion, :notifica_acudiente_cumplido, :exige_responsable,
             :titular_es_responsable, :titular_es_aprobador)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        self::enlazarDatos($sentence, $datos);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $datos = self::leerDatos();

        if ($datos['error']) {
            Flight::json(array('error' => $datos['error']), 400);
            return;
        }

        $sentence = $db->prepare("UPDATE tipos_solicitud SET
                nombre = :nombre,
                descripcion = :descripcion,
                icono = :icono,
                orden = :orden,
                activo = :activo,
                requiere_aprobacion = :requiere_aprobacion,
                manejo_horas = :manejo_horas,
                documento = :documento,
                minutos_anticipacion = :minutos_anticipacion,
                requiere_confirmacion = :requiere_confirmacion,
                notifica_acudiente_cumplido = :notifica_acudiente_cumplido,
                exige_responsable = :exige_responsable,
                titular_es_responsable = :titular_es_responsable,
                titular_es_aprobador = :titular_es_aprobador
            WHERE id = :id AND id_tenant = :id_tenant");
        self::enlazarDatos($sentence, $datos);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Solo se puede borrar un tipo que nunca se uso. Si ya tiene solicitudes,
     * borrarlo dejaria el historico sin nombre; para sacarlo de la lista esta
     * el campo activo.
     */
    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("SELECT COUNT(*) AS usos FROM solicitudes WHERE id_tipo_solicitud = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['usos'] > 0) {
            Flight::json(array('error' => 'El tipo ya tiene solicitudes y no se puede eliminar. Desactivelo si no quiere que siga apareciendo.'), 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM tipos_solicitud WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Lee y valida el cuerpo de la peticion. Devuelve el arreglo de valores
     * mas una clave 'error' con el primer problema encontrado, o null.
     */
    private static function leerDatos()
    {
        $data = Flight::request()->data;

        $valores = array(
            'error'                       => null,
            'nombre'                      => isset($data['nombre']) ? trim($data['nombre']) : '',
            'descripcion'                 => isset($data['descripcion']) ? $data['descripcion'] : null,
            'icono'                       => isset($data['icono']) ? $data['icono'] : null,
            'orden'                       => isset($data['orden']) ? (int)$data['orden'] : 0,
            'activo'                      => isset($data['activo']) ? (int)$data['activo'] : 1,
            'requiere_aprobacion'         => isset($data['requiere_aprobacion']) ? (int)$data['requiere_aprobacion'] : 0,
            'manejo_horas'                => isset($data['manejo_horas']) ? (int)$data['manejo_horas'] : self::HORAS_NINGUNA,
            'documento'                   => isset($data['documento']) ? (int)$data['documento'] : self::DOC_NO,
            'minutos_anticipacion'        => (isset($data['minutos_anticipacion']) && $data['minutos_anticipacion'] !== '' && $data['minutos_anticipacion'] !== null) ? (int)$data['minutos_anticipacion'] : null,
            'requiere_confirmacion'       => isset($data['requiere_confirmacion']) ? (int)$data['requiere_confirmacion'] : 0,
            'notifica_acudiente_cumplido' => isset($data['notifica_acudiente_cumplido']) ? (int)$data['notifica_acudiente_cumplido'] : 0,
            'exige_responsable'           => isset($data['exige_responsable']) ? (int)$data['exige_responsable'] : 0,
            'titular_es_responsable'      => isset($data['titular_es_responsable']) ? (int)$data['titular_es_responsable'] : 1,
            'titular_es_aprobador'        => isset($data['titular_es_aprobador']) ? (int)$data['titular_es_aprobador'] : 0
        );

        if ($valores['nombre'] === '') {
            $valores['error'] = 'El nombre es obligatorio';
            return $valores;
        }

        if (!in_array($valores['manejo_horas'], array(self::HORAS_NINGUNA, self::HORAS_UNA, self::HORAS_VARIAS), true)) {
            $valores['error'] = 'El manejo de horas no es valido';
            return $valores;
        }

        if (!in_array($valores['documento'], array(self::DOC_NO, self::DOC_OPCIONAL, self::DOC_OBLIGATORIO), true)) {
            $valores['error'] = 'La configuracion de documento no es valida';
            return $valores;
        }

        // Sin horas no hay a que anticiparse: el aviso previo se calcula
        // contra la hora programada de la ocurrencia.
        if ($valores['manejo_horas'] === self::HORAS_NINGUNA) {
            $valores['minutos_anticipacion'] = null;
        }

        if ($valores['minutos_anticipacion'] !== null && $valores['minutos_anticipacion'] < 0) {
            $valores['error'] = 'Los minutos de anticipacion no pueden ser negativos';
            return $valores;
        }

        return $valores;
    }

    private static function enlazarDatos($sentence, $datos)
    {
        $sentence->bindValue(':nombre', $datos['nombre']);
        $sentence->bindValue(':descripcion', $datos['descripcion']);
        $sentence->bindValue(':icono', $datos['icono']);
        $sentence->bindValue(':orden', $datos['orden'], PDO::PARAM_INT);
        $sentence->bindValue(':activo', $datos['activo'], PDO::PARAM_INT);
        $sentence->bindValue(':requiere_aprobacion', $datos['requiere_aprobacion'], PDO::PARAM_INT);
        $sentence->bindValue(':manejo_horas', $datos['manejo_horas'], PDO::PARAM_INT);
        $sentence->bindValue(':documento', $datos['documento'], PDO::PARAM_INT);
        $sentence->bindValue(':minutos_anticipacion', $datos['minutos_anticipacion'], $datos['minutos_anticipacion'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':requiere_confirmacion', $datos['requiere_confirmacion'], PDO::PARAM_INT);
        $sentence->bindValue(':notifica_acudiente_cumplido', $datos['notifica_acudiente_cumplido'], PDO::PARAM_INT);
        $sentence->bindValue(':exige_responsable', $datos['exige_responsable'], PDO::PARAM_INT);
        $sentence->bindValue(':titular_es_responsable', $datos['titular_es_responsable'], PDO::PARAM_INT);
        $sentence->bindValue(':titular_es_aprobador', $datos['titular_es_aprobador'], PDO::PARAM_INT);
    }
}
