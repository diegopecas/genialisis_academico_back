<?php 
/**
 * Tarifas de cursos extracurriculares.
 *
 * La tarifa es la cabecera: curso, anio y convenio. Lo que se cobra vive en
 * su detalle (tarifas_cursos_extra_detalle): matricula, pension, valor unico
 * o paquetes de sesiones. Las columnas viejas de valores (valor_matricula,
 * valor_pension, valor_unico, cuotas_* e id_producto_*) ya no se escriben ni
 * se leen; se quitan de la tabla cuando esto este desplegado en todas las
 * bases.
 */
class TarifasCursosExtra
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tce.id, tce.id_curso_extra, tce.id_institucion_cliente,
        tce.id_producto_matricula, tce.valor_matricula, tce.cuotas_matricula,
        tce.id_producto_pension, tce.valor_pension,
        tce.id_producto_unico, tce.valor_unico, tce.cuotas_unico,
        tce.anio,
        pm.nombre AS nombre_producto_matricula,
        pp.nombre AS nombre_producto_pension,
        pu.nombre AS nombre_producto_unico,
        ce.nombre AS nombre_curso,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion
        FROM tarifas_cursos_extra tce
        INNER JOIN cursos_extra ce ON tce.id_curso_extra = ce.id
        LEFT JOIN productos_servicios pm ON tce.id_producto_matricula = pm.id
        LEFT JOIN productos_servicios pp ON tce.id_producto_pension = pp.id
        LEFT JOIN productos_servicios pu ON tce.id_producto_unico = pu.id
        LEFT JOIN instituciones_cliente ic ON tce.id_institucion_cliente = ic.id
        LEFT JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE tce.id_tenant = :id_tenant
        ORDER BY tce.anio DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = TarifasCursosExtraDetalle::adjuntar($db, $sentence->fetchAll());
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tce.id, tce.id_curso_extra, tce.id_institucion_cliente,
        tce.id_producto_matricula, tce.valor_matricula, tce.cuotas_matricula,
        tce.id_producto_pension, tce.valor_pension,
        tce.id_producto_unico, tce.valor_unico, tce.cuotas_unico,
        tce.anio,
        pm.nombre AS nombre_producto_matricula,
        pp.nombre AS nombre_producto_pension,
        pu.nombre AS nombre_producto_unico,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion
        FROM tarifas_cursos_extra tce
        LEFT JOIN productos_servicios pm ON tce.id_producto_matricula = pm.id
        LEFT JOIN productos_servicios pp ON tce.id_producto_pension = pp.id
        LEFT JOIN productos_servicios pu ON tce.id_producto_unico = pu.id
        LEFT JOIN instituciones_cliente ic ON tce.id_institucion_cliente = ic.id
        LEFT JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE tce.id = :id AND tce.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = TarifasCursosExtraDetalle::adjuntar($db, $sentence->fetchAll());
        Flight::json($response);
    }

    /**
     * Todas las tarifas del curso: la interna (id_institucion_cliente nulo)
     * y las de cada convenio. La pantalla las separa con ese campo. Cada una
     * viene con su detalle, incluidas las filas inactivas, porque es la que
     * usa la pantalla de configuracion.
     */
    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tce.id, tce.id_curso_extra, tce.id_institucion_cliente,
        tce.id_producto_matricula, tce.valor_matricula, tce.cuotas_matricula,
        tce.id_producto_pension, tce.valor_pension,
        tce.id_producto_unico, tce.valor_unico, tce.cuotas_unico,
        tce.anio,
        pm.nombre AS nombre_producto_matricula,
        pp.nombre AS nombre_producto_pension,
        pu.nombre AS nombre_producto_unico,
        CASE
            WHEN pi.razon_social IS NOT NULL AND pi.razon_social != '' THEN pi.razon_social
            ELSE CONCAT(IFNULL(pi.primer_nombre, ''), ' ', IFNULL(pi.primer_apellido, ''))
        END AS nombre_institucion
        FROM tarifas_cursos_extra tce
        LEFT JOIN productos_servicios pm ON tce.id_producto_matricula = pm.id
        LEFT JOIN productos_servicios pp ON tce.id_producto_pension = pp.id
        LEFT JOIN productos_servicios pu ON tce.id_producto_unico = pu.id
        LEFT JOIN instituciones_cliente ic ON tce.id_institucion_cliente = ic.id
        LEFT JOIN personas pi ON ic.id_persona = pi.id AND pi.id_tenant = ic.id_tenant
        WHERE tce.id_curso_extra = :id_curso_extra AND tce.id_tenant = :id_tenant
        ORDER BY tce.anio DESC, tce.id_institucion_cliente IS NOT NULL, nombre_institucion");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = TarifasCursosExtraDetalle::adjuntar($db, $sentence->fetchAll());
        Flight::json($response);
    }

    /**
     * Tarifa que aplica a una inscripcion: la del convenio si existe, y si no
     * la interna del jardin. Con id_institucion_cliente nulo devuelve la
     * interna directamente.
     *
     * Es el mismo criterio que usa CuentasPorCobrar::generarDesdeCursoExtra,
     * expuesto aqui para que la pantalla muestre los valores que de verdad se
     * van a cobrar.
     *
     * La tarifa viene con la llave 'detalle': solo las filas activas, que son
     * las que se pueden cobrar u ofrecer.
     *
     * @return array|null Fila de la tarifa, o null si no hay ninguna.
     */
    public static function resolverTarifa($db, $id_curso_extra, $anio, $id_institucion_cliente = null)
    {
        if (!empty($id_institucion_cliente)) {
            $stmt = $db->prepare("SELECT * FROM tarifas_cursos_extra
                                  WHERE id_curso_extra = :id_curso_extra
                                    AND anio = :anio
                                    AND id_institucion_cliente = :id_institucion_cliente
                                    AND id_tenant = :id_tenant
                                  LIMIT 1");
            $stmt->bindParam(':id_curso_extra', $id_curso_extra);
            $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
            $stmt->bindParam(':id_institucion_cliente', $id_institucion_cliente);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $tarifa = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tarifa) {
                $tarifa['detalle'] = TarifasCursosExtraDetalle::listar($db, $tarifa['id'], true);
                return $tarifa;
            }
            // Sin tarifa propia, el convenio cae a la interna.
        }

        $stmt = $db->prepare("SELECT * FROM tarifas_cursos_extra
                              WHERE id_curso_extra = :id_curso_extra
                                AND anio = :anio
                                AND id_institucion_cliente IS NULL
                                AND id_tenant = :id_tenant
                              LIMIT 1");
        $stmt->bindParam(':id_curso_extra', $id_curso_extra);
        $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $tarifa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tarifa) {
            $tarifa['detalle'] = TarifasCursosExtraDetalle::listar($db, $tarifa['id'], true);
        }

        return $tarifa ? $tarifa : null;
    }

    // Endpoint de resolverTarifa. Devuelve un arreglo, vacio si no hay tarifa,
    // para que la pantalla lo trate igual que los otros get.
    public static function getVigente($id_curso_extra, $anio)
    {
        $db = Flight::db();
        $id_institucion_cliente = isset(Flight::request()->query['id_institucion_cliente'])
            ? Flight::request()->query['id_institucion_cliente'] : null;
        $tarifa = self::resolverTarifa($db, $id_curso_extra, $anio, $id_institucion_cliente);
        Flight::json($tarifa ? array($tarifa) : array());
    }

    /**
     * Crea la tarifa con su detalle en una sola transaccion. El detalle llega
     * en la llave 'detalle': una fila por concepto que se cobra.
     */
    public static function new()
    {
        $db = Flight::db();
        $id_curso_extra = Flight::request()->data['id_curso_extra'];
        $anio = Flight::request()->data['anio'];
        $detalle = isset(Flight::request()->data['detalle']) ? Flight::request()->data['detalle'] : array();
        // Opcional: los formularios que no manejan convenios no lo envian y la
        // tarifa queda como interna del jardin, que es el comportamiento viejo.
        $id_institucion_cliente = isset(Flight::request()->data['id_institucion_cliente'])
            ? Flight::request()->data['id_institucion_cliente'] : null;
        if (empty($id_institucion_cliente)) {
            $id_institucion_cliente = null;
        }

        // El indice unico uq_tarifas_cursos_extra ya lo impide, pero reventaria
        // con un error de base. Aqui sale un mensaje que se entiende.
        $verif = $db->prepare("SELECT id FROM tarifas_cursos_extra
                               WHERE id_curso_extra = :id_curso_extra
                                 AND anio = :anio
                                 AND IFNULL(id_institucion_cliente, '') = IFNULL(:id_institucion_cliente, '')
                                 AND id_tenant = :id_tenant LIMIT 1");
        $verif->bindParam(':id_curso_extra', $id_curso_extra);
        $verif->bindValue(':anio', $anio, PDO::PARAM_INT);
        $verif->bindValue(':id_institucion_cliente', $id_institucion_cliente);
        $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $verif->execute();

        if ($verif->fetch()) {
            Flight::json(array('error' => 'Ya existe una tarifa para ese curso, año y convenio.'), 400);
            return;
        }

        $idNew = Uuid::generar();

        try {
            $db->beginTransaction();

            $sentence = $db->prepare("INSERT INTO tarifas_cursos_extra(id, id_tenant, id_curso_extra, anio, id_institucion_cliente)
            VALUES (:id, :id_tenant, :id_curso_extra, :anio, :id_institucion_cliente)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_curso_extra', $id_curso_extra);
            $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':id_institucion_cliente', $id_institucion_cliente);
            $sentence->execute();

            $error = TarifasCursosExtraDetalle::sincronizar($db, $idNew, $detalle);
            if ($error !== null) {
                $db->rollBack();
                Flight::json(array('error' => $error), 400);
                return;
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear la tarifa del curso extra: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo crear la tarifa.'), 500);
            return;
        }

        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    /**
     * Actualiza la tarifa y deja su detalle igual a la lista que llega: las
     * filas con id se actualizan, las nuevas se crean y las que ya no vienen
     * se borran. Todo en una transaccion.
     */
    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $anio = Flight::request()->data['anio'];
        $detalle = isset(Flight::request()->data['detalle']) ? Flight::request()->data['detalle'] : array();

        try {
            $db->beginTransaction();

            // A que convenio pertenece la tarifa no se cambia en edicion: para
            // moverla se borra y se crea otra, porque el indice unico va por curso,
            // anio y convenio.
            $sentence = $db->prepare("UPDATE tarifas_cursos_extra SET anio = :anio WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            $error = TarifasCursosExtraDetalle::sincronizar($db, $id, $detalle);
            if ($error !== null) {
                $db->rollBack();
                Flight::json(array('error' => $error), 400);
                return;
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al actualizar la tarifa del curso extra: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo actualizar la tarifa.'), 500);
            return;
        }

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        // El detalle va primero: la llave foranea no deja borrar la cabecera
        // con filas colgando.
        TarifasCursosExtraDetalle::borrarPorTarifa($db, $id);
        $sentence = $db->prepare("DELETE FROM tarifas_cursos_extra WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

}