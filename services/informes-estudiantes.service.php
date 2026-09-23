<?php
class InformesEstudiantes
{
    /**
     * Estudiantes de un grupo con el estado de su informe en ese corte.
     *
     * Es la pantalla de entrada: se ve de una quién está sin empezar, en
     * borrador o confirmado, sin tener que abrir uno por uno.
     */
    public static function getEstadoPorGrupo($id_grupo, $id_corte)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT e.id AS id_estudiante,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                   i.id AS id_informe,
                   COALESCE(i.estado, 'sin_generar') AS estado,
                   i.fecha_generacion,
                   i.fecha_confirmacion,
                   -- Las secciones informativas no llevan marca: contarlas
                   -- dejaba el avance imposible de completar
                   (SELECT COUNT(*) FROM informes_estudiantes_detalle d
                     INNER JOIN informes_secciones s ON d.id_seccion = s.id
                     WHERE d.id_informe = i.id AND s.se_califica = 1) AS filas_total,
                   (SELECT COUNT(*) FROM informes_estudiantes_detalle d
                     INNER JOIN informes_secciones s ON d.id_seccion = s.id
                     WHERE d.id_informe = i.id AND s.se_califica = 1
                       AND d.id_valor_parametro IS NOT NULL) AS filas_calificadas
            FROM estudiantes_x_grupos eg
            INNER JOIN estudiantes e ON eg.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            LEFT JOIN informes_estudiantes i
                   ON i.id_estudiante = e.id
                  AND i.id_corte_academico = :id_corte
                  AND i.id_tenant = :id_tenant_informe
            WHERE eg.id_grupo = :id_grupo
              AND eg.activo = 1
              AND e.activo = 1
              AND eg.id_tenant = :id_tenant
            ORDER BY p.primer_apellido, p.segundo_apellido, p.primer_nombre");
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->bindParam(':id_corte', $id_corte);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant_informe', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Informe completo de un estudiante en un corte: maestro, secciones con
     * sus filas y los textos.
     *
     * Las secciones salen de la configuración del jardín y las filas se
     * arman en tiempo de ejecución; lo que ya está calificado se trae del
     * detalle guardado.
     */
    public static function getByEstudianteCorte($id_estudiante, $id_corte)
    {
        $db = Flight::db();
        $idTenant = TenantContext::id();

        // 1. Maestro
        $sentence = $db->prepare("
            SELECT i.*, g.nombre AS nombre_grupo,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante
            FROM informes_estudiantes i
            LEFT JOIN grupos g ON i.id_grupo = g.id
            INNER JOIN estudiantes e ON i.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            WHERE i.id_estudiante = :id_estudiante
              AND i.id_corte_academico = :id_corte
              AND i.id_tenant = :id_tenant");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_corte', $id_corte);
        $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $sentence->execute();
        $maestro = $sentence->fetch();

        if (!$maestro) {
            Flight::json(array('informe' => null, 'secciones' => array()));
            return;
        }

        Flight::json(array(
            'informe'   => $maestro,
            'secciones' => self::armarSecciones($maestro['id'], $id_estudiante, $id_corte, $idTenant)
        ));
    }

    /**
     * Genera el informe de un estudiante: crea el maestro si no existe y
     * arma el detalle con las filas que le corresponden según la
     * configuración, todas sin calificar.
     *
     * Si el informe ya existe se completa con las filas que falten, sin
     * tocar lo ya calificado. Así un logro agregado a la malla después
     * aparece sin perder el trabajo hecho.
     */
    public static function generar()
    {
        $db = Flight::db();

        try {
            $id_estudiante = Flight::request()->data['id_estudiante'] ?? null;
            $id_corte = Flight::request()->data['id_corte_academico'] ?? null;
            $id_usuario = Flight::request()->data['id_usuario'] ?? null;

            if ($id_estudiante === null || $id_estudiante === '' || $id_corte === null || $id_corte === '') {
                Flight::json(array('error' => 'Faltan el estudiante y el corte'), 400);
                return;
            }

            $idTenant = TenantContext::id();
            $db->beginTransaction();

            // Grupo actual del estudiante: se congela en el maestro porque
            // el niño puede cambiarse de grupo mas adelante.
            $st = $db->prepare("SELECT id_grupo FROM estudiantes_x_grupos
                                WHERE id_estudiante = :id AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");
            $st->bindParam(':id', $id_estudiante);
            $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $st->execute();
            $grupo = $st->fetch();
            $id_grupo = $grupo ? $grupo['id_grupo'] : null;

            // Maestro
            $st = $db->prepare("SELECT id, estado FROM informes_estudiantes
                                WHERE id_estudiante = :id_estudiante AND id_corte_academico = :id_corte
                                  AND id_tenant = :id_tenant");
            $st->bindParam(':id_estudiante', $id_estudiante);
            $st->bindParam(':id_corte', $id_corte);
            $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $st->execute();
            $existente = $st->fetch();

            if ($existente) {
                $id_informe = $existente['id'];
            } else {
                $id_informe = Uuid::generar();
                $ins = $db->prepare("INSERT INTO informes_estudiantes(
                        id, id_tenant, id_estudiante, id_corte_academico, id_grupo,
                        estado, id_usuario_genero, fecha_generacion
                    ) VALUES (
                        :id, :id_tenant, :id_estudiante, :id_corte, :id_grupo,
                        'borrador', :id_usuario, NOW()
                    )");
                $ins->bindValue(':id', $id_informe);
                $ins->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $ins->bindParam(':id_estudiante', $id_estudiante);
                $ins->bindParam(':id_corte', $id_corte);
                $ins->bindValue(':id_grupo', $id_grupo);
                $ins->bindValue(':id_usuario', $id_usuario);
                $ins->execute();
            }

            $creadas = self::sembrarDetalle($db, $id_informe, $id_estudiante, $id_corte, $id_grupo, $idTenant);

            $db->commit();

            Flight::json(array(
                'id' => $id_informe,
                'filas_nuevas' => $creadas
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al generar el informe del estudiante: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al generar el informe. Inténtalo más tarde.'), 500);
        }
    }

    /**
     * Guarda las marcas y los textos del informe. Solo se aceptan cambios
     * mientras esté en borrador: un informe confirmado no se modifica sin
     * reabrirlo primero.
     */
    public static function guardar()
    {
        $db = Flight::db();

        try {
            $id_informe = Flight::request()->data['id_informe'] ?? null;
            $detalle = Flight::request()->data['detalle'] ?? array();
            $textos = Flight::request()->data['textos'] ?? array();
            $texto_cierre = Flight::request()->data['texto_cierre'] ?? null;

            if ($id_informe === null || $id_informe === '') {
                Flight::json(array('error' => 'Falta el informe'), 400);
                return;
            }

            $idTenant = TenantContext::id();

            $st = $db->prepare("SELECT estado FROM informes_estudiantes
                                WHERE id = :id AND id_tenant = :id_tenant");
            $st->bindParam(':id', $id_informe);
            $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $st->execute();
            $info = $st->fetch();

            if (!$info) {
                Flight::json(array('error' => 'No se encontró el informe'), 404);
                return;
            }

            if ($info['estado'] !== 'borrador') {
                Flight::json(array('error' => 'El informe ya está confirmado. Reábrelo para poder modificarlo.'), 400);
                return;
            }

            $db->beginTransaction();

            $up = $db->prepare("UPDATE informes_estudiantes_detalle
                                SET id_valor_parametro = :id_valor, origen = :origen
                                WHERE id = :id AND id_informe = :id_informe AND id_tenant = :id_tenant");
            foreach ($detalle as $fila) {
                $up->bindValue(':id_valor', $fila['id_valor_parametro'] ?? null);
                $up->bindValue(':origen', $fila['origen'] ?? 'manual');
                $up->bindValue(':id', $fila['id']);
                $up->bindValue(':id_informe', $id_informe);
                $up->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $up->execute();
            }

            // Los textos por sección se reemplazan uno a uno
            $delTexto = $db->prepare("DELETE FROM informes_estudiantes_textos
                                      WHERE id_informe = :id_informe AND id_seccion = :id_seccion AND id_tenant = :id_tenant");
            $insTexto = $db->prepare("INSERT INTO informes_estudiantes_textos(id, id_tenant, id_informe, id_seccion, texto)
                                      VALUES (:id, :id_tenant, :id_informe, :id_seccion, :texto)");
            foreach ($textos as $t) {
                $delTexto->bindValue(':id_informe', $id_informe);
                $delTexto->bindValue(':id_seccion', $t['id_seccion']);
                $delTexto->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $delTexto->execute();

                if (isset($t['texto']) && $t['texto'] !== '') {
                    $insTexto->bindValue(':id', Uuid::generar());
                    $insTexto->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $insTexto->bindValue(':id_informe', $id_informe);
                    $insTexto->bindValue(':id_seccion', $t['id_seccion']);
                    $insTexto->bindValue(':texto', $t['texto']);
                    $insTexto->execute();
                }
            }

            $upm = $db->prepare("UPDATE informes_estudiantes SET texto_cierre = :texto_cierre
                                 WHERE id = :id AND id_tenant = :id_tenant");
            $upm->bindValue(':texto_cierre', $texto_cierre);
            $upm->bindValue(':id', $id_informe);
            $upm->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $upm->execute();

            $db->commit();

            Flight::json(array('id' => $id_informe));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al guardar el informe del estudiante: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al guardar el informe. Inténtalo más tarde.'), 500);
        }
    }

    /**
     * Confirma el informe. Exige que no queden filas sin calificar en las
     * secciones que sí se califican, y congela las ausencias del corte.
     */
    public static function confirmar()
    {
        $db = Flight::db();

        try {
            $id_informe = Flight::request()->data['id_informe'] ?? null;
            $id_usuario = Flight::request()->data['id_usuario'] ?? null;

            if ($id_informe === null || $id_informe === '') {
                Flight::json(array('error' => 'Falta el informe'), 400);
                return;
            }

            $idTenant = TenantContext::id();

            $st = $db->prepare("
                SELECT COUNT(*) AS pendientes
                FROM informes_estudiantes_detalle d
                INNER JOIN informes_secciones s ON d.id_seccion = s.id
                WHERE d.id_informe = :id_informe
                  AND d.id_tenant = :id_tenant
                  AND s.se_califica = 1
                  AND d.id_valor_parametro IS NULL");
            $st->bindParam(':id_informe', $id_informe);
            $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $st->execute();
            $pendientes = (int) $st->fetch()['pendientes'];

            if ($pendientes > 0) {
                Flight::json(array('error' => "Faltan $pendientes filas por calificar"), 400);
                return;
            }

            $db->beginTransaction();

            $up = $db->prepare("UPDATE informes_estudiantes
                                SET estado = 'confirmado',
                                    id_usuario_confirmo = :id_usuario,
                                    fecha_confirmacion = NOW(),
                                    ausencias = :ausencias
                                WHERE id = :id AND id_tenant = :id_tenant");
            $up->bindValue(':id_usuario', $id_usuario);
            $up->bindValue(':ausencias', self::contarAusencias($db, $id_informe, $idTenant));
            $up->bindValue(':id', $id_informe);
            $up->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $up->execute();

            $db->commit();

            Flight::json(array('id' => $id_informe, 'estado' => 'confirmado'));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al confirmar el informe: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al confirmar el informe. Inténtalo más tarde.'), 500);
        }
    }

    /**
     * Devuelve el informe a borrador para poder corregirlo.
     */
    public static function reabrir()
    {
        $db = Flight::db();
        $id_informe = Flight::request()->data['id_informe'] ?? null;

        if ($id_informe === null || $id_informe === '') {
            Flight::json(array('error' => 'Falta el informe'), 400);
            return;
        }

        $st = $db->prepare("UPDATE informes_estudiantes
                            SET estado = 'borrador', id_usuario_confirmo = NULL, fecha_confirmacion = NULL
                            WHERE id = :id AND id_tenant = :id_tenant");
        $st->bindParam(':id', $id_informe);
        $st->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $st->execute();

        Flight::json(array('id' => $id_informe, 'estado' => 'borrador'));
    }

    /**
     * Secciones configuradas del jardín, para el selector de la vista masiva.
     * Solo las que aplican al grado del grupo.
     */
    public static function getSeccionesPorGrupo($id_grupo)
    {
        $db = Flight::db();
        $idTenant = TenantContext::id();

        $st = $db->prepare("SELECT id_grado FROM grados_x_grupo
                            WHERE id_grupo = :id_grupo AND id_tenant = :id_tenant LIMIT 1");
        $st->bindParam(':id_grupo', $id_grupo);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $fila = $st->fetch();
        $id_grado = $fila ? $fila['id_grado'] : null;

        $q = $db->prepare("
            SELECT s.id, s.nombre, s.orden, s.id_seccion_padre, s.tipo_origen,
                   s.se_califica, s.tipo_contenido, s.evalua_a,
                   padre.nombre AS nombre_seccion_padre
            FROM informes_secciones s
            LEFT JOIN informes_secciones padre ON s.id_seccion_padre = padre.id
            WHERE s.id_tenant = :id_tenant AND s.activo = 1
              AND (s.id_grado IS NULL OR s.id_grado = :id_grado)
            ORDER BY COALESCE(padre.orden, s.orden), s.id_seccion_padre IS NOT NULL, s.orden");
        $q->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $q->bindValue(':id_grado', $id_grado);
        $q->execute();
        Flight::json($q->fetchAll());
    }

    /**
     * Vista masiva: una sección para todo el grupo.
     *
     * Devuelve las filas de la sección (las columnas de la tabla) y, por
     * cada estudiante, lo que ya tiene marcado y su texto. Así se califica
     * una dimensión completa para los quince niños sin abrir uno por uno.
     */
    public static function getSeccionPorGrupo($id_grupo, $id_corte, $id_seccion)
    {
        $db = Flight::db();
        $idTenant = TenantContext::id();

        // 'cierre' no es una seccion configurada: es el texto general del
        // informe, que vive en el maestro. Se atiende aqui para poder
        // escribirlo para todo el grupo desde la misma vista.
        $esCierre = ($id_seccion === 'cierre');

        if ($esCierre) {
            $seccion = array(
                'id'             => 'cierre',
                'nombre'         => 'Observaciones generales',
                'se_califica'    => 0,
                'tipo_contenido' => 'texto',
                'evalua_a'       => 'estudiante'
            );
        } else {
            $st = $db->prepare("SELECT id, nombre, se_califica, tipo_contenido, evalua_a
                                FROM informes_secciones
                                WHERE id = :id_seccion AND id_tenant = :id_tenant");
            $st->bindParam(':id_seccion', $id_seccion);
            $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $st->execute();
            $seccion = $st->fetch();

            if (!$seccion) {
                Flight::json(array('error' => 'No se encontró la sección'), 404);
                return;
            }
        }

        // Estudiantes del grupo con su informe del corte
        $st = $db->prepare("
            SELECT e.id AS id_estudiante,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                   i.id AS id_informe,
                   COALESCE(i.estado, 'sin_generar') AS estado,
                   i.texto_cierre
            FROM estudiantes_x_grupos eg
            INNER JOIN estudiantes e ON eg.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            LEFT JOIN informes_estudiantes i
                   ON i.id_estudiante = e.id
                  AND i.id_corte_academico = :id_corte
                  AND i.id_tenant = :id_tenant_informe
            WHERE eg.id_grupo = :id_grupo
              AND eg.activo = 1
              AND e.activo = 1
              AND eg.id_tenant = :id_tenant
            ORDER BY p.primer_apellido, p.segundo_apellido, p.primer_nombre");
        $st->bindParam(':id_grupo', $id_grupo);
        $st->bindParam(':id_corte', $id_corte);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->bindValue(':id_tenant_informe', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $estudiantes = $st->fetchAll();

        // Filas de la sección para cada informe, y el texto si aplica
        $qFilas = $db->prepare("
            SELECT d.id, d.tipo_fila, d.id_fila, d.id_valor_parametro, d.orden,
                   CASE d.tipo_fila
                       WHEN 'logro' THEN (SELECT nombre FROM logros WHERE id = d.id_fila)
                       WHEN 'item'  THEN (SELECT texto  FROM informes_items WHERE id = d.id_fila)
                   END AS texto_fila
            FROM informes_estudiantes_detalle d
            WHERE d.id_informe = :id_informe AND d.id_seccion = :id_seccion AND d.id_tenant = :id_tenant
            ORDER BY d.orden");

        $qTexto = $db->prepare("SELECT texto FROM informes_estudiantes_textos
                                WHERE id_informe = :id_informe AND id_seccion = :id_seccion AND id_tenant = :id_tenant");

        // Las columnas de la tabla: se toman del primer estudiante que tenga
        // filas sembradas, porque todos los del grado comparten las mismas.
        $columnas = array();

        foreach ($estudiantes as $k => $est) {
            $estudiantes[$k]['filas'] = array();
            $estudiantes[$k]['texto'] = null;

            if (!$est['id_informe']) {
                continue;
            }

            if ($esCierre) {
                $estudiantes[$k]['texto'] = $est['texto_cierre'];
                continue;
            }

            $qFilas->bindValue(':id_informe', $est['id_informe']);
            $qFilas->bindValue(':id_seccion', $id_seccion);
            $qFilas->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $qFilas->execute();
            $filas = $qFilas->fetchAll();
            $estudiantes[$k]['filas'] = $filas;

            if (count($columnas) === 0 && count($filas) > 0) {
                foreach ($filas as $f) {
                    $columnas[] = array(
                        'id_fila'    => $f['id_fila'],
                        'tipo_fila'  => $f['tipo_fila'],
                        'texto_fila' => $f['texto_fila'],
                        'orden'      => $f['orden']
                    );
                }
            }

            $qTexto->bindValue(':id_informe', $est['id_informe']);
            $qTexto->bindValue(':id_seccion', $id_seccion);
            $qTexto->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $qTexto->execute();
            $t = $qTexto->fetch();
            $estudiantes[$k]['texto'] = $t ? $t['texto'] : null;
        }

        Flight::json(array(
            'seccion'     => $seccion,
            'columnas'    => $columnas,
            'estudiantes' => $estudiantes
        ));
    }

    /**
     * Guardado masivo: recibe el arreglo completo de la vista por sección y
     * lo aplica en una sola transacción, en vez de una llamada por
     * estudiante.
     *
     * Los informes confirmados se saltan sin fallar, para que un grupo
     * mixto no bloquee el guardado de los demás.
     */
    public static function guardarMasivo()
    {
        $db = Flight::db();

        try {
            $id_seccion = Flight::request()->data['id_seccion'] ?? null;
            $estudiantes = Flight::request()->data['estudiantes'] ?? array();

            if ($id_seccion === null || $id_seccion === '') {
                Flight::json(array('error' => 'Falta la sección'), 400);
                return;
            }

            $idTenant = TenantContext::id();
            $db->beginTransaction();

            $stEstado = $db->prepare("SELECT estado FROM informes_estudiantes
                                      WHERE id = :id AND id_tenant = :id_tenant");

            $upDetalle = $db->prepare("UPDATE informes_estudiantes_detalle
                                       SET id_valor_parametro = :id_valor, origen = 'manual'
                                       WHERE id = :id AND id_informe = :id_informe AND id_tenant = :id_tenant");

            $delTexto = $db->prepare("DELETE FROM informes_estudiantes_textos
                                      WHERE id_informe = :id_informe AND id_seccion = :id_seccion AND id_tenant = :id_tenant");

            $insTexto = $db->prepare("INSERT INTO informes_estudiantes_textos(id, id_tenant, id_informe, id_seccion, texto)
                                      VALUES (:id, :id_tenant, :id_informe, :id_seccion, :texto)");

            // El texto de cierre vive en el maestro, no en la tabla de textos
            $upCierre = $db->prepare("UPDATE informes_estudiantes SET texto_cierre = :texto_cierre
                                      WHERE id = :id AND id_tenant = :id_tenant");

            $esCierre = ($id_seccion === 'cierre');

            $guardados = 0;
            $omitidos = 0;

            foreach ($estudiantes as $est) {
                $id_informe = $est['id_informe'] ?? null;
                if ($id_informe === null || $id_informe === '') {
                    $omitidos++;
                    continue;
                }

                $stEstado->bindValue(':id', $id_informe);
                $stEstado->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stEstado->execute();
                $info = $stEstado->fetch();

                // Un informe confirmado no se toca: se salta en silencio
                if (!$info || $info['estado'] !== 'borrador') {
                    $omitidos++;
                    continue;
                }

                foreach (($est['filas'] ?? array()) as $fila) {
                    $upDetalle->bindValue(':id_valor', $fila['id_valor_parametro'] ?? null);
                    $upDetalle->bindValue(':id', $fila['id']);
                    $upDetalle->bindValue(':id_informe', $id_informe);
                    $upDetalle->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $upDetalle->execute();
                }

                if ($esCierre && array_key_exists('texto', $est)) {
                    $upCierre->bindValue(':texto_cierre', $est['texto']);
                    $upCierre->bindValue(':id', $id_informe);
                    $upCierre->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $upCierre->execute();
                } elseif (array_key_exists('texto', $est)) {
                    $delTexto->bindValue(':id_informe', $id_informe);
                    $delTexto->bindValue(':id_seccion', $id_seccion);
                    $delTexto->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $delTexto->execute();

                    if ($est['texto'] !== null && $est['texto'] !== '') {
                        $insTexto->bindValue(':id', Uuid::generar());
                        $insTexto->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                        $insTexto->bindValue(':id_informe', $id_informe);
                        $insTexto->bindValue(':id_seccion', $id_seccion);
                        $insTexto->bindValue(':texto', $est['texto']);
                        $insTexto->execute();
                    }
                }

                $guardados++;
            }

            $db->commit();

            Flight::json(array('guardados' => $guardados, 'omitidos' => $omitidos));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al guardar el informe masivo: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al guardar. Inténtalo más tarde.'), 500);
        }
    }

    /**
     * Genera varios informes en una sola llamada. Antes el front hacía una
     * peticion por estudiante en un bucle, y con quince niños se sentía.
     */
    public static function generarMasivo()
    {
        $db = Flight::db();

        try {
            $estudiantes = Flight::request()->data['estudiantes'] ?? array();
            $id_corte = Flight::request()->data['id_corte_academico'] ?? null;
            $id_usuario = Flight::request()->data['id_usuario'] ?? null;

            if ($id_corte === null || $id_corte === '' || count($estudiantes) === 0) {
                Flight::json(array('error' => 'Faltan los estudiantes y el corte'), 400);
                return;
            }

            $idTenant = TenantContext::id();
            $db->beginTransaction();

            $stGrupo = $db->prepare("SELECT id_grupo FROM estudiantes_x_grupos
                                     WHERE id_estudiante = :id AND activo = 1 AND id_tenant = :id_tenant LIMIT 1");

            $stExiste = $db->prepare("SELECT id, estado FROM informes_estudiantes
                                      WHERE id_estudiante = :id_estudiante AND id_corte_academico = :id_corte
                                        AND id_tenant = :id_tenant");

            $ins = $db->prepare("INSERT INTO informes_estudiantes(
                    id, id_tenant, id_estudiante, id_corte_academico, id_grupo,
                    estado, id_usuario_genero, fecha_generacion
                ) VALUES (
                    :id, :id_tenant, :id_estudiante, :id_corte, :id_grupo,
                    'borrador', :id_usuario, NOW()
                )");

            $procesados = 0;
            $filasNuevas = 0;

            foreach ($estudiantes as $id_estudiante) {
                $stGrupo->bindValue(':id', $id_estudiante);
                $stGrupo->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stGrupo->execute();
                $g = $stGrupo->fetch();
                $id_grupo = $g ? $g['id_grupo'] : null;

                $stExiste->bindValue(':id_estudiante', $id_estudiante);
                $stExiste->bindValue(':id_corte', $id_corte);
                $stExiste->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stExiste->execute();
                $existente = $stExiste->fetch();

                if ($existente) {
                    // Los confirmados no se recompletan
                    if ($existente['estado'] !== 'borrador') {
                        continue;
                    }
                    $id_informe = $existente['id'];
                } else {
                    $id_informe = Uuid::generar();
                    $ins->bindValue(':id', $id_informe);
                    $ins->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $ins->bindValue(':id_estudiante', $id_estudiante);
                    $ins->bindValue(':id_corte', $id_corte);
                    $ins->bindValue(':id_grupo', $id_grupo);
                    $ins->bindValue(':id_usuario', $id_usuario);
                    $ins->execute();
                }

                $filasNuevas += self::sembrarDetalle($db, $id_informe, $id_estudiante, $id_corte, $id_grupo, $idTenant);
                $procesados++;
            }

            $db->commit();

            Flight::json(array('procesados' => $procesados, 'filas_nuevas' => $filasNuevas));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al generar informes en lote: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al generar los informes. Inténtalo más tarde.'), 500);
        }
    }

    /**
     * Estudiantes que ya tienen informe del módulo nuevo en un corte.
     *
     * La pantalla de autorización la usa para saber cuál botón mostrar: el
     * boletín nuevo o el informe viejo del sprint.
     */
    public static function getConInformePorCorte($id_corte)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT i.id_estudiante, i.id AS id_informe, i.estado
            FROM informes_estudiantes i
            WHERE i.id_corte_academico = :id_corte
              AND i.id_tenant = :id_tenant");
        $sentence->bindParam(':id_corte', $id_corte);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Todos los informes del grupo en un corte, con sus secciones y filas.
     *
     * Una sola consulta para toda la pantalla: con esto el front no vuelve
     * al servidor al cambiar de estudiante, de vista o de sección. Un grupo
     * de veinte niños con cuarenta filas cada uno son ochocientas filas,
     * que pesan mucho menos que una peticion por cada clic.
     */
    public static function getGrupoCompleto($id_grupo, $id_corte)
    {
        $db = Flight::db();
        $idTenant = TenantContext::id();

        // Estudiantes del grupo con su informe
        $st = $db->prepare("
            SELECT e.id AS id_estudiante,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                   i.id AS id_informe,
                   COALESCE(i.estado, 'sin_generar') AS estado,
                   i.texto_cierre,
                   i.ausencias,
                   i.fecha_generacion,
                   i.fecha_confirmacion,
                   g.nombre AS nombre_grupo
            FROM estudiantes_x_grupos eg
            INNER JOIN estudiantes e ON eg.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            LEFT JOIN grupos g ON eg.id_grupo = g.id
            LEFT JOIN informes_estudiantes i
                   ON i.id_estudiante = e.id
                  AND i.id_corte_academico = :id_corte
                  AND i.id_tenant = :id_tenant_informe
            WHERE eg.id_grupo = :id_grupo
              AND eg.activo = 1
              AND e.activo = 1
              AND eg.id_tenant = :id_tenant
            ORDER BY p.primer_apellido, p.segundo_apellido, p.primer_nombre");
        $st->bindParam(':id_grupo', $id_grupo);
        $st->bindParam(':id_corte', $id_corte);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->bindValue(':id_tenant_informe', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $estudiantes = $st->fetchAll();

        foreach ($estudiantes as $k => $est) {
            $estudiantes[$k]['secciones'] = $est['id_informe']
                ? self::armarSecciones($est['id_informe'], $est['id_estudiante'], $id_corte, $idTenant)
                : array();

            // Solo cuentan las secciones que se califican: las informativas
            // listan sus filas pero no llevan marca.
            $total = 0;
            $calificadas = 0;
            foreach ($estudiantes[$k]['secciones'] as $sec) {
                if ($sec['se_califica'] != 1) {
                    continue;
                }
                foreach ($sec['filas'] as $fila) {
                    $total++;
                    if ($fila['id_valor_parametro'] !== null) {
                        $calificadas++;
                    }
                }
            }
            $estudiantes[$k]['filas_total'] = $total;
            $estudiantes[$k]['filas_calificadas'] = $calificadas;
        }

        Flight::json($estudiantes);
    }

    // =================================================================
    // PORTAL DE PADRES
    //
    // El acudiente solo ve informes confirmados y con el corte autorizado
    // para ese estudiante. El sprint no se mira: el boletin cuelga del
    // corte, no de un sprint.
    // =================================================================

    /**
     * Cortes con informe disponible para el acudiente.
     *
     * Se valida que el estudiante sea suyo antes de devolver nada.
     */
    public static function getDisponiblesAcudiente($id_persona, $id_estudiante)
    {
        $db = Flight::db();
        $idTenant = TenantContext::id();

        if (!Acudientes::esEstudianteDelAcudiente($db, $id_persona, $id_estudiante)) {
            Flight::json(array('error' => 'No tiene acceso a la información de este estudiante'), 403);
            return;
        }

        $sentence = $db->prepare("
            SELECT i.id AS id_informe,
                   i.id_corte_academico,
                   c.nombre AS nombre_corte,
                   c.orden AS orden_corte,
                   c.fecha_inicio,
                   c.fecha_fin,
                   i.fecha_confirmacion,
                   g.nombre AS nombre_grupo
            FROM informes_estudiantes i
            INNER JOIN cortes_academicos c ON i.id_corte_academico = c.id
            INNER JOIN autorizaciones_informes_estudiantes a
                    ON a.id_estudiante = i.id_estudiante
                   AND a.id_corte_academico = i.id_corte_academico
                   AND a.id_tenant = i.id_tenant
                   AND a.autorizado = 1
            LEFT JOIN grupos g ON i.id_grupo = g.id
            WHERE i.id_estudiante = :id_estudiante
              AND i.id_tenant = :id_tenant
              AND i.estado IN ('confirmado', 'publicado')
            ORDER BY c.orden");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Informe completo para el acudiente: maestro, secciones y filas.
     *
     * Repite las tres validaciones a proposito, porque este endpoint se
     * puede llamar directo sin pasar por el listado.
     */
    public static function getInformeAcudiente($id_persona, $id_estudiante, $id_corte)
    {
        $db = Flight::db();
        $idTenant = TenantContext::id();

        if (!Acudientes::esEstudianteDelAcudiente($db, $id_persona, $id_estudiante)) {
            Flight::json(array('error' => 'No tiene acceso a la información de este estudiante'), 403);
            return;
        }

        $sentence = $db->prepare("
            SELECT i.*, g.nombre AS nombre_grupo,
                   c.nombre AS nombre_corte,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante
            FROM informes_estudiantes i
            INNER JOIN cortes_academicos c ON i.id_corte_academico = c.id
            INNER JOIN autorizaciones_informes_estudiantes a
                    ON a.id_estudiante = i.id_estudiante
                   AND a.id_corte_academico = i.id_corte_academico
                   AND a.id_tenant = i.id_tenant
                   AND a.autorizado = 1
            LEFT JOIN grupos g ON i.id_grupo = g.id
            INNER JOIN estudiantes e ON i.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            WHERE i.id_estudiante = :id_estudiante
              AND i.id_corte_academico = :id_corte
              AND i.id_tenant = :id_tenant
              AND i.estado IN ('confirmado', 'publicado')");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_corte', $id_corte);
        $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $sentence->execute();
        $maestro = $sentence->fetch();

        if (!$maestro) {
            Flight::json(array('informe' => null, 'secciones' => array()));
            return;
        }

        // Se devuelve tambien la configuracion y la escala para que el portal
        // de padres no tenga que llamar endpoints del institucional.
        $cfg = $db->prepare("
            SELECT id_parametro_evaluacion, muestra_ausencias, titulo_informe,
                   encabezado, pie_pagina, firma_uno, firma_dos, firma_acudiente,
                   estilo_marca, simbolo_marca, color_principal, mostrar_convencion
            FROM informes_configuracion
            WHERE id_tenant = :id_tenant
            LIMIT 1");
        $cfg->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $cfg->execute();
        $configuracion = $cfg->fetch();

        $valores = array();
        if ($configuracion && $configuracion['id_parametro_evaluacion']) {
            $qv = $db->prepare("
                SELECT id, valor_cuantitativo, valor_cualitativo, icono, color, orden
                FROM valores_parametros_calificaciones
                WHERE id_parametros_calificaciones = :id_parametro
                  AND id_tenant = :id_tenant
                ORDER BY orden, valor_cuantitativo");
            $qv->bindValue(':id_parametro', $configuracion['id_parametro_evaluacion']);
            $qv->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $qv->execute();
            $valores = $qv->fetchAll();
        }

        Flight::json(array(
            'informe'       => $maestro,
            'secciones'     => self::armarSecciones($maestro['id'], $id_estudiante, $id_corte, $idTenant),
            'configuracion' => $configuracion ? $configuracion : null,
            'valores'       => $valores
        ));
    }

    // =================================================================
    // PRIVADOS
    // =================================================================

    /**
     * Crea en el detalle las filas que le corresponden al estudiante y que
     * todavía no existen. No toca las que ya están, para no borrar trabajo.
     */
    private static function sembrarDetalle($db, $id_informe, $id_estudiante, $id_corte, $id_grupo, $idTenant)
    {
        $creadas = 0;

        // Grado del estudiante: filtra los logros y las secciones por grado
        $st = $db->prepare("SELECT gg.id_grado FROM grados_x_grupo gg
                            WHERE gg.id_grupo = :id_grupo AND gg.id_tenant = :id_tenant LIMIT 1");
        $st->bindValue(':id_grupo', $id_grupo);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $fila = $st->fetch();
        $id_grado = $fila ? $fila['id_grado'] : null;

        // Secciones aplicables: las del jardín, sin grado o del grado del estudiante
        $st = $db->prepare("SELECT id, tipo_origen, id_origen, se_califica, requiere_inscripcion
                            FROM informes_secciones
                            WHERE id_tenant = :id_tenant AND activo = 1
                              AND (id_grado IS NULL OR id_grado = :id_grado)
                            ORDER BY orden");
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->bindValue(':id_grado', $id_grado);
        $st->execute();
        $secciones = $st->fetchAll();

        $ins = $db->prepare("INSERT IGNORE INTO informes_estudiantes_detalle(
                id, id_tenant, id_informe, id_seccion, tipo_fila, id_fila, id_valor_parametro, origen, orden
            ) VALUES (
                :id, :id_tenant, :id_informe, :id_seccion, :tipo_fila, :id_fila, NULL, 'manual', :orden
            )");

        foreach ($secciones as $sec) {
            $filas = array();

            if ($sec['tipo_origen'] === 'esfera' || $sec['tipo_origen'] === 'area') {
                if ($sec['id_origen'] === null) {
                    continue; // sección sin origen definido todavía
                }

                // Un área extracurricular no cuelga de un grado sino de un
                // nivel, y el nivel vive en la inscripción del estudiante al
                // curso. Por eso se resuelve aparte.
                $id_nivel = null;
                $esExtracurricular = false;

                if ($sec['tipo_origen'] === 'area') {
                    $esExtracurricular = self::esAreaExtracurricular($db, $sec['id_origen'], $idTenant);

                    if ($esExtracurricular) {
                        $id_nivel = self::nivelDelEstudianteEnArea($db, $id_estudiante, $sec['id_origen'], $idTenant);

                        // Sin inscripción no hay nada que calificar: la
                        // sección no aparece en el informe de ese niño.
                        if ($id_nivel === false && $sec['requiere_inscripcion'] == 1) {
                            continue;
                        }
                    }
                }

                if ($esExtracurricular) {
                    // Por nivel. Si el estudiante está inscrito pero sin nivel
                    // asignado, se traen los logros del área sin filtrar.
                    $q = $db->prepare("SELECT id FROM logros
                                       WHERE id_tenant = :id_tenant
                                         AND id_area_academica = :id_origen
                                         AND id_corte_academico = :id_corte
                                         AND (:id_nivel_nulo IS NULL OR id_nivel = :id_nivel)
                                       ORDER BY nombre");
                    $q->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $q->bindValue(':id_origen', $sec['id_origen']);
                    $q->bindValue(':id_corte', $id_corte);
                    $q->bindValue(':id_nivel', $id_nivel ? $id_nivel : null);
                    $q->bindValue(':id_nivel_nulo', $id_nivel ? $id_nivel : null);
                } else {
                    $campo = $sec['tipo_origen'] === 'esfera' ? 'id_esfera_desarrollo' : 'id_area_academica';
                    $q = $db->prepare("SELECT id FROM logros
                                       WHERE id_tenant = :id_tenant
                                         AND $campo = :id_origen
                                         AND id_corte_academico = :id_corte
                                         AND (:id_grado_nulo IS NULL OR id_grado = :id_grado)
                                       ORDER BY nombre");
                    $q->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $q->bindValue(':id_origen', $sec['id_origen']);
                    $q->bindValue(':id_corte', $id_corte);
                    $q->bindValue(':id_grado', $id_grado);
                    $q->bindValue(':id_grado_nulo', $id_grado);
                }

                $q->execute();
                foreach ($q->fetchAll() as $r) {
                    $filas[] = array('tipo' => 'logro', 'id' => $r['id']);
                }
            } elseif ($sec['tipo_origen'] === 'items') {
                $q = $db->prepare("SELECT id FROM informes_items
                                   WHERE id_seccion = :id_seccion AND activo = 1 AND id_tenant = :id_tenant
                                   ORDER BY orden");
                $q->bindValue(':id_seccion', $sec['id']);
                $q->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $q->execute();
                foreach ($q->fetchAll() as $r) {
                    $filas[] = array('tipo' => 'item', 'id' => $r['id']);
                }
            }
            // Las secciones de tipo 'observacion' solo llevan texto: no siembran detalle

            $orden = 1;
            foreach ($filas as $f) {
                $ins->bindValue(':id', Uuid::generar());
                $ins->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $ins->bindValue(':id_informe', $id_informe);
                $ins->bindValue(':id_seccion', $sec['id']);
                $ins->bindValue(':tipo_fila', $f['tipo']);
                $ins->bindValue(':id_fila', $f['id']);
                $ins->bindValue(':orden', $orden, PDO::PARAM_INT);
                $ins->execute();
                $creadas += $ins->rowCount();
                $orden++;
            }
        }

        return $creadas;
    }

    /**
     * True si el área está marcada como extracurricular.
     *
     * Un área extracurricular no se asigna a grupos y su malla cuelga de un
     * nivel, no de un grado.
     */
    private static function esAreaExtracurricular($db, $id_area, $idTenant)
    {
        $st = $db->prepare("SELECT es_extracurricular FROM areas_academicas
                            WHERE id = :id_area AND id_tenant = :id_tenant");
        $st->bindValue(':id_area', $id_area);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $fila = $st->fetch();

        return $fila && (int) $fila['es_extracurricular'] === 1;
    }

    /**
     * Nivel del estudiante en los cursos de esa área.
     *
     * El nivel vive en la inscripción y no en el curso, para que un niño
     * suba de nivel sin cambiar de curso. Un mismo curso tiene niños en
     * niveles distintos y a cada uno le aplican logros distintos.
     *
     * Devuelve false si no está inscrito, y null si lo está pero sin nivel
     * asignado: son dos casos diferentes, porque sin inscripción la sección
     * no debe aparecer y sin nivel se muestran todos los logros del área.
     *
     * @return string|null|false
     */
    private static function nivelDelEstudianteEnArea($db, $id_estudiante, $id_area, $idTenant)
    {
        $st = $db->prepare("
            SELECT exc.id_nivel
            FROM estudiantes_x_cursos_extra exc
            INNER JOIN cursos_extra ce ON exc.id_curso_extra = ce.id
            WHERE exc.id_estudiante = :id_estudiante
              AND ce.id_area_academica = :id_area
              AND exc.activo = 1
              AND ce.activo = 1
              AND exc.id_tenant = :id_tenant
            ORDER BY exc.fecha_inscripcion DESC
            LIMIT 1");
        $st->bindValue(':id_estudiante', $id_estudiante);
        $st->bindValue(':id_area', $id_area);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $fila = $st->fetch();

        if (!$fila) {
            return false;
        }

        return $fila['id_nivel'];
    }

    /**
     * Arma las secciones del informe con sus filas y su texto, resolviendo
     * el nombre de cada fila según sea un logro o un ítem.
     */
    private static function armarSecciones($id_informe, $id_estudiante, $id_corte, $idTenant)
    {
        $db = Flight::db();

        $st = $db->prepare("
            SELECT s.id, s.nombre, s.orden, s.id_seccion_padre, s.tipo_origen,
                   s.se_califica, s.tipo_contenido, s.evalua_a,
                   padre.nombre AS nombre_seccion_padre,
                   (SELECT texto FROM informes_estudiantes_textos t
                     WHERE t.id_informe = :id_informe_texto AND t.id_seccion = s.id) AS texto
            FROM informes_secciones s
            LEFT JOIN informes_secciones padre ON s.id_seccion_padre = padre.id
            WHERE s.id_tenant = :id_tenant AND s.activo = 1
              AND (
                    -- Secciones con filas sembradas
                    EXISTS (SELECT 1 FROM informes_estudiantes_detalle d
                             WHERE d.id_informe = :id_informe AND d.id_seccion = s.id)
                    -- Las de solo texto no tienen detalle y quedaban por fuera
                    OR s.tipo_contenido = 'texto'
                  )
            ORDER BY COALESCE(padre.orden, s.orden), s.id_seccion_padre IS NOT NULL, s.orden");
        $st->bindValue(':id_informe', $id_informe);
        $st->bindValue(':id_informe_texto', $id_informe);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $secciones = $st->fetchAll();

        $q = $db->prepare("
            SELECT d.id, d.tipo_fila, d.id_fila, d.id_valor_parametro, d.origen, d.orden,
                   CASE d.tipo_fila
                       WHEN 'logro' THEN (SELECT nombre FROM logros WHERE id = d.id_fila)
                       WHEN 'item'  THEN (SELECT texto  FROM informes_items WHERE id = d.id_fila)
                   END AS texto_fila
            FROM informes_estudiantes_detalle d
            WHERE d.id_informe = :id_informe AND d.id_seccion = :id_seccion AND d.id_tenant = :id_tenant
            ORDER BY d.orden");

        foreach ($secciones as $k => $sec) {
            $q->bindValue(':id_informe', $id_informe);
            $q->bindValue(':id_seccion', $sec['id']);
            $q->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $q->execute();
            $secciones[$k]['filas'] = $q->fetchAll();
        }

        return $secciones;
    }

    /**
     * Ausencias del estudiante dentro del corte.
     *
     * La asistencia se registra por ingreso: no hay marca de "no asistio".
     * Entonces se toman como dias de clase aquellos en que hubo registro de
     * al menos un estudiante del mismo grupo, y se restan los dias en que el
     * estudiante si tuvo registro. Es una aproximacion, pero es la unica
     * lectura posible con el modelo actual de asistencia.
     */
    private static function contarAusencias($db, $id_informe, $idTenant)
    {
        $st = $db->prepare("
            SELECT i.id_estudiante, i.id_grupo, c.fecha_inicio, c.fecha_fin
            FROM informes_estudiantes i
            INNER JOIN cortes_academicos c ON c.id = i.id_corte_academico
            WHERE i.id = :id_informe AND i.id_tenant = :id_tenant");
        $st->bindValue(':id_informe', $id_informe);
        $st->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $st->execute();
        $info = $st->fetch();

        if (!$info || $info['id_grupo'] === null) {
            return null;
        }

        $q = $db->prepare("
            SELECT COUNT(*) AS total FROM (
                SELECT DISTINCT DATE(a.fecha_ingreso) AS dia
                FROM asistencia_estudiantes a
                INNER JOIN estudiantes_x_grupos eg
                        ON eg.id_estudiante = a.id_estudiante
                       AND eg.id_grupo = :id_grupo
                       AND eg.activo = 1
                WHERE a.id_tenant = :id_tenant
                  AND DATE(a.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
                  AND DATE(a.fecha_ingreso) NOT IN (
                      SELECT DISTINCT DATE(b.fecha_ingreso)
                      FROM asistencia_estudiantes b
                      WHERE b.id_estudiante = :id_estudiante
                        AND b.id_tenant = :id_tenant_est
                        AND DATE(b.fecha_ingreso) BETWEEN :fecha_inicio_est AND :fecha_fin_est
                  )
            ) dias_sin_registro");
        $q->bindValue(':id_grupo', $info['id_grupo']);
        $q->bindValue(':id_estudiante', $info['id_estudiante']);
        $q->bindValue(':fecha_inicio', $info['fecha_inicio']);
        $q->bindValue(':fecha_fin', $info['fecha_fin']);
        $q->bindValue(':fecha_inicio_est', $info['fecha_inicio']);
        $q->bindValue(':fecha_fin_est', $info['fecha_fin']);
        $q->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $q->bindValue(':id_tenant_est', $idTenant, PDO::PARAM_INT);
        $q->execute();
        $r = $q->fetch();
        return $r ? (int) $r['total'] : 0;
    }
}