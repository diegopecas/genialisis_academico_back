<?php
class Visitas
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    v.*,
                    tc.nombre as nombre_tipo_contacto,
                    tcc.nombre as nombre_como_conocio,
                    CONCAT(u.usuario, ' - ', p.primer_nombre, ' ', p.primer_apellido) as nombre_usuario_registro
                FROM visitas v
                INNER JOIN tipos_contacto tc ON v.id_tipo_contacto = tc.id
                LEFT JOIN tipos_como_conocio tcc ON v.id_como_conocio = tcc.id
                INNER JOIN usuarios u ON v.id_usuario_registro = u.id
                LEFT JOIN personas p ON u.id_persona = p.id
                ORDER BY v.fecha DESC, v.hora DESC
            ");
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    v.*,
                    tc.nombre as nombre_tipo_contacto,
                    tcc.nombre as nombre_como_conocio,
                    CONCAT(u.usuario, ' - ', p.primer_nombre, ' ', p.primer_apellido) as nombre_usuario_registro
                FROM visitas v
                INNER JOIN tipos_contacto tc ON v.id_tipo_contacto = tc.id
                LEFT JOIN tipos_como_conocio tcc ON v.id_como_conocio = tcc.id
                INNER JOIN usuarios u ON v.id_usuario_registro = u.id
                LEFT JOIN personas p ON u.id_persona = p.id
                WHERE v.id = :id
            ");
            $sentence->bindParam(':id', $id);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByFecha($fecha_inicio, $fecha_fin)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    v.*,
                    tc.nombre as nombre_tipo_contacto,
                    tcc.nombre as nombre_como_conocio,
                    CONCAT(u.usuario, ' - ', p.primer_nombre, ' ', p.primer_apellido) as nombre_usuario_registro
                FROM visitas v
                INNER JOIN tipos_contacto tc ON v.id_tipo_contacto = tc.id
                LEFT JOIN tipos_como_conocio tcc ON v.id_como_conocio = tcc.id
                INNER JOIN usuarios u ON v.id_usuario_registro = u.id
                LEFT JOIN personas p ON u.id_persona = p.id
                WHERE v.fecha BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY v.fecha DESC, v.hora DESC
            ");
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindParam(':fecha_fin', $fecha_fin);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas getByFecha: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            INSERT INTO visitas (
                fecha, hora, id_tipo_contacto, agendada, id_como_conocio, 
                detalle_como_conocio, comentarios_razones, observaciones,
                id_usuario_registro
            ) VALUES (
                :fecha, :hora, :id_tipo_contacto, :agendada, :id_como_conocio,
                :detalle_como_conocio, :comentarios_razones, :observaciones,
                :id_usuario_registro
            )
        ");

            // Manejar conversión de booleanos
            $agendada = isset($data['agendada']) ? ($data['agendada'] ? 1 : 0) : 0;

            $sentence->bindParam(':fecha', $data['fecha']);
            $sentence->bindParam(':hora', $data['hora']);
            $sentence->bindParam(':id_tipo_contacto', $data['id_tipo_contacto']);
            $sentence->bindParam(':agendada', $agendada);
            $sentence->bindParam(':id_como_conocio', $data['id_como_conocio']);
            $sentence->bindParam(':detalle_como_conocio', $data['detalle_como_conocio']);
            $sentence->bindParam(':comentarios_razones', $data['comentarios_razones']);
            $sentence->bindParam(':observaciones', $data['observaciones']);
            $sentence->bindParam(':id_usuario_registro', $data['id_usuario_registro']);

            $sentence->execute();
            $id = $db->lastInsertId();

            // ✅ Si se llamó con parámetro, retornar el ID, sino usar Flight::json
            if ($dataParam !== null) {
                return $id;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas new: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción, sino usar Flight::json
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace($dataParam = null)
    {
        try {
            $db = Flight::db();

            // ✅ Si se pasa parámetro, usarlo. Si no, tomar de Flight::request()
            $data = $dataParam ?? Flight::request()->data;

            $sentence = $db->prepare("
            UPDATE visitas SET
                fecha = :fecha,
                hora = :hora,
                id_tipo_contacto = :id_tipo_contacto,
                agendada = :agendada,
                id_como_conocio = :id_como_conocio,
                detalle_como_conocio = :detalle_como_conocio,
                comentarios_razones = :comentarios_razones,
                observaciones = :observaciones
            WHERE id = :id
        ");

            // Manejar conversión de booleanos
            $agendada = isset($data['agendada']) ? ($data['agendada'] ? 1 : 0) : 0;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':fecha', $data['fecha']);
            $sentence->bindParam(':hora', $data['hora']);
            $sentence->bindParam(':id_tipo_contacto', $data['id_tipo_contacto']);
            $sentence->bindParam(':agendada', $agendada);
            $sentence->bindParam(':id_como_conocio', $data['id_como_conocio']);
            $sentence->bindParam(':detalle_como_conocio', $data['detalle_como_conocio']);
            $sentence->bindParam(':comentarios_razones', $data['comentarios_razones']);
            $sentence->bindParam(':observaciones', $data['observaciones']);

            $sentence->execute();

            // ✅ Si se llamó con parámetro, retornar true, sino usar self::getById
            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas replace: " . $e->getMessage());

            // ✅ Si se llamó con parámetro, lanzar excepción, sino usar Flight::json
            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM visitas WHERE id = :id");
            $sentence->bindParam(':id', $id);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Método para obtener visita completa con todas sus relaciones
    public static function getVisitaCompleta($id)
    {
        try {
            $db = Flight::db();

            // Visita principal
            $stmt = $db->prepare("SELECT * FROM visitas WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $visita = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$visita) {
                Flight::json(array('error' => 'Visita no encontrada'), 404);
                return;
            }

            // Visitantes
            $stmt = $db->prepare("
            SELECT v.*, tp.nombre as nombre_parentesco 
            FROM visitantes v
            LEFT JOIN tipos_parentesco tp ON v.id_parentesco = tp.id
            WHERE v.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['visitantes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Niños con sus necesidades
            $stmt = $db->prepare("SELECT * FROM visitas_ninos WHERE id_visita = :id ORDER BY id");
            $stmt->execute(['id' => $id]);
            $ninos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada niño, obtener sus necesidades especiales
            foreach ($ninos as &$nino) {
                $stmtNecesidades = $db->prepare("
                    SELECT 
                        vnn.id,
                        vnn.id_tipo_necesidad,
                        vnn.detalle,
                        tne.nombre as nombre_necesidad,
                        tne.icono as icono_necesidad
                    FROM visitas_ninos_necesidades vnn
                    INNER JOIN tipos_necesidades_especiales tne ON vnn.id_tipo_necesidad = tne.id
                    WHERE vnn.id_visita_nino = :id_visita_nino
                ");
                $stmtNecesidades->execute(['id_visita_nino' => $nino['id']]);
                $nino['necesidades'] = $stmtNecesidades->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($nino); // Romper la referencia

            $visita['ninos'] = $ninos;

            // Razones de búsqueda
            $stmt = $db->prepare("
            SELECT vrb.*, trb.nombre 
            FROM visitas_razones_busqueda vrb
            INNER JOIN tipos_razones_busqueda trb ON vrb.id_razon = trb.id
            WHERE vrb.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['razones_busqueda'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Temperatura
            $stmt = $db->prepare("
            SELECT vt.*, tni.nombre as nombre_nivel_interes, tu.nombre as nombre_urgencia
            FROM visitas_temperatura vt
            LEFT JOIN tipos_nivel_interes tni ON vt.id_nivel_interes = tni.id
            LEFT JOIN tipos_urgencia tu ON vt.id_urgencia = tu.id
            WHERE vt.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['temperatura'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Seguimiento
            $stmt = $db->prepare("
            SELECT vs.*, tcs.nombre as nombre_cuando_seguimiento, tqd.nombre as nombre_quien_decide
            FROM visitas_seguimiento vs
            LEFT JOIN tipos_cuando_seguimiento tcs ON vs.id_cuando_seguimiento = tcs.id
            LEFT JOIN tipos_quien_decide tqd ON vs.id_quien_decide = tqd.id
            WHERE vs.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['seguimiento'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Compromisos
            $stmt = $db->prepare("
            SELECT vc.*, tc.nombre as nombre_compromiso
            FROM visitas_compromisos vc
            INNER JOIN tipos_compromisos tc ON vc.id_tipo_compromiso = tc.id
            WHERE vc.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['compromisos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Preferencias de seguimiento
            $stmt = $db->prepare("
            SELECT vps.*, tps.nombre, tps.codigo, tps.icono
            FROM visitas_preferencias_seguimiento vps
            INNER JOIN tipos_preferencias_seguimiento tps ON vps.id_preferencia_seguimiento = tps.id
            WHERE vps.id_visita = :id
            ORDER BY tps.orden
            ");
            $stmt->execute(['id' => $id]);
            $visita['preferencias_seguimiento'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ NUEVO: Observaciones DISC por visitante
            $observacionesDiscPorVisitante = [];

            foreach ($visita['visitantes'] as $visitante) {
                $stmt = $db->prepare("
                SELECT 
                    vod.id,
                    vod.id_parametro_disc,
                    vod.marcado,
                    pd.categoria,
                    pd.descripcion,
                    pd.perfil_asociado,
                    pd.peso,
                    cd.codigo as codigo_categoria
                FROM visitas_observaciones_disc vod
                INNER JOIN parametros_disc pd ON vod.id_parametro_disc = pd.id
                INNER JOIN categorias_disc cd ON pd.id_categoria = cd.id
                WHERE vod.id_visitante = :id_visitante AND vod.marcado = 1
                ORDER BY cd.orden, pd.orden
                ");
                $stmt->execute(['id_visitante' => $visitante['id']]);
                $observaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Agrupar por categoría (usando código de categoría)
                $observacionesAgrupadas = [];
                foreach ($observaciones as $obs) {
                    $categoria = $obs['codigo_categoria'];
                    if (!isset($observacionesAgrupadas[$categoria])) {
                        $observacionesAgrupadas[$categoria] = [];
                    }
                    $observacionesAgrupadas[$categoria][] = $obs['id_parametro_disc'];
                }

                // Indexar por id_visitante
                $observacionesDiscPorVisitante[$visitante['id']] = $observacionesAgrupadas;
            }
            $visita['observaciones_disc'] = $observacionesDiscPorVisitante;

            error_log("✅ Observaciones DISC cargadas para " . count($observacionesDiscPorVisitante) . " visitantes");

            // ✅ NUEVO: Perfiles calculados por visitante
            $perfilesCalculados = [];

            foreach ($visita['visitantes'] as $visitante) {
                $stmt = $db->prepare("
                SELECT perfil_sugerido, puntaje_D, puntaje_I, puntaje_S, puntaje_C, fecha_calculo
                FROM visitas_perfil_calculado
                WHERE id_visitante = :id_visitante
                ORDER BY fecha_calculo DESC
                LIMIT 1
                ");
                $stmt->execute(['id_visitante' => $visitante['id']]);
                $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($perfil) {
                    $perfilesCalculados[$visitante['id']] = [
                        'perfil_sugerido' => $perfil['perfil_sugerido'],
                        'puntaje_D' => (int)$perfil['puntaje_D'],
                        'puntaje_I' => (int)$perfil['puntaje_I'],
                        'puntaje_S' => (int)$perfil['puntaje_S'],
                        'puntaje_C' => (int)$perfil['puntaje_C']
                    ];
                }
            }
            $visita['perfiles_calculados'] = $perfilesCalculados;
            // ====================================================
            // TAB 2: PROTOCOLO - PASOS COMPLETADOS Y CHECKLIST
            // ====================================================

            // Pasos del protocolo completados
            $stmt = $db->prepare("
                SELECT 
                    vppc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso
                FROM visitas_protocolo_pasos_completados vppc
                INNER JOIN protocolo_pasos pp ON vppc.id_protocolo_paso = pp.id
                WHERE vppc.id_visita = :id
                ORDER BY pp.numero_paso ASC
            ");
            $stmt->execute(['id' => $id]);
            $visita['protocoloPasos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Checklist del protocolo
            $stmt = $db->prepare("
                SELECT 
                    vpc.*,
                    pp.nombre as nombre_paso,
                    pp.numero_paso
                FROM visitas_protocolo_checklist vpc
                INNER JOIN protocolo_pasos pp ON vpc.id_protocolo_paso = pp.id
                WHERE vpc.id_visita = :id
                ORDER BY pp.numero_paso ASC
            ");
            $stmt->execute(['id' => $id]);
            $checklistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ IMPORTANTE: Convertir checklist de texto a índices
            // El frontend necesita item_index, no item_checklist (texto)
            $visita['protocoloChecklist'] = [];

            if (count($checklistItems) > 0) {
                // Cargar los pasos con sus checklist_items para hacer el mapeo inverso
                $stmt = $db->prepare("SELECT id, checklist_items FROM protocolo_pasos WHERE activo = 1");
                $stmt->execute();
                $pasos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Crear mapeo: id_paso => array de textos
                $checklistsPorPaso = [];
                foreach ($pasos as $paso) {
                    if ($paso['checklist_items']) {
                        try {
                            $checklistsPorPaso[$paso['id']] = json_decode($paso['checklist_items'], true);
                        } catch (Exception $e) {
                            $checklistsPorPaso[$paso['id']] = [];
                        }
                    }
                }

                // Convertir cada item de texto a índice
                foreach ($checklistItems as $item) {
                    $id_protocolo_paso = $item['id_protocolo_paso'];
                    $item_texto = $item['item_checklist'];

                    // Buscar el índice del texto en el array del paso
                    if (isset($checklistsPorPaso[$id_protocolo_paso])) {
                        $index = array_search($item_texto, $checklistsPorPaso[$id_protocolo_paso]);

                        if ($index !== false) {
                            $visita['protocoloChecklist'][] = [
                                'id' => $item['id'],
                                'id_protocolo_paso' => $id_protocolo_paso,
                                'item_index' => $index,
                                'completado' => $item['completado']
                            ];
                        }
                    }
                }
            }

            error_log("✅ Datos del Tab 2 (Protocolo) cargados");
            error_log("📋 Pasos completados: " . count($visita['protocoloPasos']));
            error_log("📋 Checklist items: " . count($visita['protocoloChecklist']));
            // ====================================================
            // TAB 3: OBJECIONES
            // ====================================================
            $stmt = $db->prepare("
                SELECT 
                    vo.*,
                    tob.nombre as nombre_objecion,
                    tob.descripcion as descripcion_objecion
                FROM visitas_objeciones vo
                INNER JOIN tipos_objeciones tob ON vo.id_tipo_objecion = tob.id
                WHERE vo.id_visita = :id
                ORDER BY vo.id
            ");
            $stmt->execute(['id' => $id]);
            $visita['objeciones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("✅ Datos del Tab 3 (Objeciones) cargados");
            error_log("📋 Objeciones: " . count($visita['objeciones']));
            // ====================================================
            // TAB 4: CARGAR DATOS DE CIERRE
            // ====================================================

            // Resultado de la visita
            $stmt = $db->prepare("
                SELECT vr.*, trv.nombre as nombre_resultado, trv.codigo as codigo_resultado, trv.es_exitoso
                FROM visitas_resultado vr
                INNER JOIN tipos_resultado_visita trv ON vr.id_tipo_resultado = trv.id
                WHERE vr.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['resultado'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Servicios que gustaron
            $stmt = $db->prepare("
                SELECT vsg.*, sj.nombre as nombre_servicio
                FROM visitas_servicios_gustaron vsg
                INNER JOIN servicios_jardin sj ON vsg.id_servicio = sj.id
                WHERE vsg.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['serviciosGustaron'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aspectos positivos
            $stmt = $db->prepare("SELECT * FROM visitas_aspectos_positivos WHERE id_visita = :id");
            $stmt->execute(['id' => $id]);
            $visita['aspectosPositivos'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detalle/Obsequio
            $stmt = $db->prepare("
                SELECT vdo.*, tid.nombre as nombre_importancia_detalle, tna.nombre as nombre_nivel_agradecimiento
                FROM visitas_detalle_obsequio vdo
                LEFT JOIN tipos_importancia_detalle tid ON vdo.id_importancia_detalle = tid.id
                LEFT JOIN tipos_nivel_agradecimiento tna ON vdo.id_nivel_agradecimiento = tna.id
                WHERE vdo.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['detalleObsequio'] = $stmt->fetch(PDO::FETCH_ASSOC);


            // Servicios que no tenemos
            $stmt = $db->prepare("
                SELECT vsnt.*, sf.nombre as nombre_servicio_faltante, tisf.nombre as nombre_importancia
                FROM visitas_servicios_no_tenemos vsnt
                INNER JOIN servicios_faltantes sf ON vsnt.id_servicio_faltante = sf.id
                LEFT JOIN tipos_importancia_servicio_faltante tisf ON vsnt.id_importancia = tisf.id
                WHERE vsnt.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['serviciosNoTenemos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Feedback para mejorar
            $stmt = $db->prepare("
                SELECT vfm.*, am.nombre as nombre_aspecto, tvf.nombre as nombre_validez
                FROM visitas_feedback_mejorar vfm
                INNER JOIN aspectos_mejorar am ON vfm.id_aspecto_mejorar = am.id
                LEFT JOIN tipos_validez_feedback tvf ON vfm.id_validez_feedback = tvf.id
                WHERE vfm.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar feedbacks
            if (count($feedbacks) > 0) {
                $visita['feedbackMejorar'] = [
                    'aspectos' => $feedbacks,
                    'comentarios_mejorar' => $feedbacks[0]['comentarios_mejorar'] ?? null,
                    'id_validez_feedback' => $feedbacks[0]['id_validez_feedback'] ?? null
                ];
            } else {
                $visita['feedbackMejorar'] = null;
            }

            // Perfil del prospecto
            $stmt = $db->prepare("
                SELECT vpp.*, tpe.nombre as nombre_perfil_economico, tne.nombre as nombre_nivel_exigencia, 
                    tsc.nombre as nombre_semaforo, tsc.color as color_semaforo
                FROM visitas_perfil_prospecto vpp
                LEFT JOIN tipos_perfil_economico tpe ON vpp.id_perfil_economico = tpe.id
                LEFT JOIN tipos_nivel_exigencia tne ON vpp.id_nivel_exigencia = tne.id
                LEFT JOIN tipos_semaforo_cliente tsc ON vpp.id_semaforo_cliente = tsc.id
                WHERE vpp.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['perfilProspecto'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Competencia
            $stmt = $db->prepare("
                SELECT vc.*, tid.nombre as nombre_inclinacion
                FROM visitas_competencia vc
                LEFT JOIN tipos_inclinacion_decision tid ON vc.id_hacia_donde_se_inclinan = tid.id
                WHERE vc.id_visita = :id
            ");
            $stmt->execute(['id' => $id]);
            $visita['competencia'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Aprendizajes
            $stmt = $db->prepare("SELECT * FROM visitas_aprendizajes WHERE id_visita = :id");
            $stmt->execute(['id' => $id]);
            $visita['aprendizajes'] = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("✅ Datos del Tab 4 cargados");
            error_log("✅ Perfiles calculados cargados para " . count($perfilesCalculados) . " visitantes");
            error_log("📋 visitantes: " . count($visita['visitantes']));
            Flight::json($visita);
        } catch (Exception $e) {
            error_log("Error en visitas getVisitaCompleta: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }




    public static function getAllCatalogos()
    {
        try {
            $db = Flight::db();
            $catalogos = array();

            // TIPOS DE CONTACTO
            $stmt = $db->prepare("SELECT * FROM tipos_contacto ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_contacto'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS CÓMO CONOCIÓ
            $stmt = $db->prepare("SELECT * FROM tipos_como_conocio ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_como_conocio'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE PARENTESCO
            $stmt = $db->prepare("SELECT * FROM tipos_parentesco ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_parentesco'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE RAZONES DE BÚSQUEDA
            $stmt = $db->prepare("SELECT * FROM tipos_razones_busqueda ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_razones_busqueda'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM categorias_disc WHERE activo = 1 ORDER BY orden");
            $stmt->execute();
            $catalogos['categorias_disc'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM parametros_disc ORDER BY categoria, orden");
            $stmt->execute();
            $catalogos['parametros_disc'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE NIVEL DE INTERÉS
            $stmt = $db->prepare("SELECT * FROM tipos_nivel_interes ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_nivel_interes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE URGENCIA
            $stmt = $db->prepare("SELECT * FROM tipos_urgencia ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_urgencia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE CUÁNDO SEGUIMIENTO
            $stmt = $db->prepare("SELECT * FROM tipos_cuando_seguimiento ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_cuando_seguimiento'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE QUIÉN DECIDE
            $stmt = $db->prepare("SELECT * FROM tipos_quien_decide ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_quien_decide'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE COMPROMISOS
            $stmt = $db->prepare("SELECT * FROM tipos_compromisos ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_compromisos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE PREFERENCIAS DE SEGUIMIENTO
            $stmt = $db->prepare("SELECT * FROM tipos_preferencias_seguimiento WHERE activo = 1 ORDER BY orden");
            $stmt->execute();
            $catalogos['tipos_preferencias_seguimiento'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // PROTOCOLO PASOS
            $stmt = $db->prepare("SELECT * FROM protocolo_pasos ORDER BY orden");
            $stmt->execute();
            $catalogos['protocolo_pasos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE OBJECIONES
            $stmt = $db->prepare("SELECT * FROM tipos_objeciones ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_objeciones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE RESULTADO DE VISITA
            $stmt = $db->prepare("SELECT * FROM tipos_resultado_visita ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_resultado_visita'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // SERVICIOS DEL JARDÍN
            $stmt = $db->prepare("SELECT * FROM servicios_jardin ORDER BY nombre");
            $stmt->execute();
            $catalogos['servicios_jardin'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE IMPORTANCIA DEL DETALLE
            $stmt = $db->prepare("SELECT * FROM tipos_importancia_detalle ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_importancia_detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE NIVEL DE AGRADECIMIENTO
            $stmt = $db->prepare("SELECT * FROM tipos_nivel_agradecimiento ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_nivel_agradecimiento'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // SERVICIOS FALTANTES
            $stmt = $db->prepare("SELECT * FROM servicios_faltantes ORDER BY nombre");
            $stmt->execute();
            $catalogos['servicios_faltantes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE IMPORTANCIA SERVICIO FALTANTE
            $stmt = $db->prepare("SELECT * FROM tipos_importancia_servicio_faltante ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_importancia_servicio_faltante'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ASPECTOS A MEJORAR
            $stmt = $db->prepare("SELECT * FROM aspectos_mejorar ORDER BY nombre");
            $stmt->execute();
            $catalogos['aspectos_mejorar'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE VALIDEZ DEL FEEDBACK
            $stmt = $db->prepare("SELECT * FROM tipos_validez_feedback ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_validez_feedback'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE PERFIL ECONÓMICO
            $stmt = $db->prepare("SELECT * FROM tipos_perfil_economico ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_perfil_economico'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE NIVEL DE EXIGENCIA
            $stmt = $db->prepare("SELECT * FROM tipos_nivel_exigencia ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_nivel_exigencia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE SEMÁFORO CLIENTE
            $stmt = $db->prepare("SELECT * FROM tipos_semaforo_cliente ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_semaforo_cliente'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE INCLINACIÓN DE DECISIÓN
            $stmt = $db->prepare("SELECT * FROM tipos_inclinacion_decision ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_inclinacion_decision'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TIPOS DE IDENTIFICACIÓN
            $stmt = $db->prepare("SELECT * FROM tipos_identificacion ORDER BY nombre");
            $stmt->execute();
            $catalogos['tipos_identificacion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // GÉNEROS
            $stmt = $db->prepare("SELECT * FROM generos ORDER BY nombre");
            $stmt->execute();
            $catalogos['generos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // CIUDADES (con departamento)
            $stmt = $db->prepare("
                        SELECT c.*, d.nombre as nombre_departamento 
                        FROM ciudades c
                        INNER JOIN departamentos d ON c.id_departamento = d.id
                        ORDER BY d.nombre, c.nombre
                    ");
            $stmt->execute();
            $catalogos['ciudades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Grupos
            $sentenceGrupos = $db->prepare("SELECT id, nombre, icono, color FROM grupos ORDER BY orden");
            $sentenceGrupos->execute();
            $catalogos['grupos'] = $sentenceGrupos->fetchAll(PDO::FETCH_ASSOC);

            // Tipos de necesidades especiales
            $sentenceNecesidades = $db->prepare("
                SELECT id, nombre, icono, orden 
                FROM tipos_necesidades_especiales 
                WHERE activo = 1 
                ORDER BY orden, nombre
            ");
            $sentenceNecesidades->execute();
            $catalogos['tipos_necesidades_especiales'] = $sentenceNecesidades->fetchAll(PDO::FETCH_ASSOC);
            // Log adicional para debug
            error_log("📋 Tipos identificación: " . count($catalogos['tipos_identificacion']));
            error_log("👥 Géneros: " . count($catalogos['generos']));
            error_log("🏙️ Ciudades: " . count($catalogos['ciudades']));

            error_log("📊 Total catálogos generados: " . count($catalogos));
            error_log("📞 Tipos contacto: " . count($catalogos['tipos_contacto']));
            error_log("👨‍👩‍👧 Tipos parentesco: " . count($catalogos['tipos_parentesco']));

            // ✅ IMPORTANTE: Devolver el objeto, no un array
            Flight::json($catalogos);
        } catch (Exception $e) {
            error_log("❌ Error en getAllCatalogos: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    // =====================================================
    // CREAR VISITA COMPLETA (ORQUESTADOR)
    // =====================================================

    public static function crearVisitaCompleta()
    {
        try {
            $db = Flight::db();

            // Obtener datos del request
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
            }

            // Iniciar transacción
            $db->beginTransaction();

            // ====================================================
            // 1. CREAR VISITA PRINCIPAL - ✅ USA self::new()
            // ====================================================
            $id_visita = self::new($data['visita']);
            error_log("✅ Visita creada con ID: " . $id_visita);

            // ====================================================
            // 2. GUARDAR VISITANTES - ✅ USA Visitantes::new()
            // ====================================================
            if (isset($data['visitantes']) && is_array($data['visitantes'])) {
                foreach ($data['visitantes'] as $visitante) {
                    $visitante['id_visita'] = $id_visita;
                    Visitantes::new($visitante);
                }
                error_log("✅ Guardados " . count($data['visitantes']) . " visitantes");
            }

            // ====================================================
            // 3. GUARDAR NIÑOS - ✅ USA VisitasNinos::new()
            // ====================================================
            if (isset($data['ninos']) && is_array($data['ninos'])) {
                foreach ($data['ninos'] as $nino) {
                    $nino['id_visita'] = $id_visita;
                    VisitasNinos::new($nino);
                }
                error_log("✅ Guardados " . count($data['ninos']) . " niños");
            }

            // ====================================================
            // 4. GUARDAR RAZONES DE BÚSQUEDA - ✅ USA guardarMultiples()
            // ====================================================
            if (isset($data['razonesBusqueda']) && is_array($data['razonesBusqueda']) && count($data['razonesBusqueda']) > 0) {
                VisitasRazonesBusqueda::guardarMultiples([
                    'id_visita' => $id_visita,
                    'razones' => $data['razonesBusqueda']
                ]);
                error_log("✅ Guardadas " . count($data['razonesBusqueda']) . " razones de búsqueda");
            }

            // ====================================================
            // 5. GUARDAR TEMPERATURA - ✅ USA guardarTemperatura()
            // ====================================================
            if (isset($data['temperatura']) && !empty($data['temperatura'])) {
                $data['temperatura']['id_visita'] = $id_visita;
                VisitasTemperatura::guardarTemperatura($data['temperatura']);
                error_log("✅ Guardada temperatura");
            }

            // ====================================================
            // 6. GUARDAR SEGUIMIENTO - ✅ USA guardarSeguimiento()
            // ====================================================
            if (isset($data['seguimiento']) && !empty($data['seguimiento'])) {
                $data['seguimiento']['id_visita'] = $id_visita;
                VisitasSeguimiento::guardarSeguimiento($data['seguimiento']);
                error_log("✅ Guardado seguimiento");
            }

            // ====================================================
            // 7. GUARDAR PREFERENCIAS DE SEGUIMIENTO - ✅ USA guardarMultiples()
            // ====================================================
            if (isset($data['preferencias_seguimiento']) && is_array($data['preferencias_seguimiento']) && count($data['preferencias_seguimiento']) > 0) {
                VisitasPreferenciasSeguimiento::guardarMultiples([
                    'id_visita' => $id_visita,
                    'preferencias' => $data['preferencias_seguimiento']
                ]);
                error_log("✅ Guardadas " . count($data['preferencias_seguimiento']) . " preferencias");
            }

            // ====================================================
            // 8. GUARDAR COMPROMISOS - ✅ USA guardarMultiples()
            // ====================================================
            if (isset($data['compromisos']) && is_array($data['compromisos']) && count($data['compromisos']) > 0) {
                VisitasCompromisos::guardarMultiples([
                    'id_visita' => $id_visita,
                    'compromisos' => $data['compromisos']
                ]);
                error_log("✅ Guardados " . count($data['compromisos']) . " compromisos");
            }
            // ====================================================
            // 9. GUARDAR RESULTADO DE LA VISITA
            // ====================================================
            if (isset($data['resultado']) && !empty($data['resultado'])) {
                $resultadoData = $data['resultado'];
                $resultadoData['id_visita'] = $id_visita;
                VisitasResultado::guardarResultado($resultadoData);
                error_log("✅ Guardado resultado de la visita");
            }

            // ====================================================
            // 10. GUARDAR SERVICIOS QUE GUSTARON
            // ====================================================
            if (isset($data['serviciosGustaron']) && is_array($data['serviciosGustaron']) && count($data['serviciosGustaron']) > 0) {
                VisitasServiciosGustaron::guardarMultiples([
                    'id_visita' => $id_visita,
                    'servicios' => $data['serviciosGustaron']
                ]);
                error_log("✅ Guardados " . count($data['serviciosGustaron']) . " servicios que gustaron");
            }

            // ====================================================
            // 11. GUARDAR ASPECTOS POSITIVOS
            // ====================================================
            if (isset($data['aspectosPositivos']) && !empty($data['aspectosPositivos'])) {
                $aspectosData = $data['aspectosPositivos'];
                $aspectosData['id_visita'] = $id_visita;
                VisitasAspectosPositivos::guardar($aspectosData);
                error_log("✅ Guardados aspectos positivos");
            }

            // ====================================================
            // 12. GUARDAR DETALLE/OBSEQUIO
            // ====================================================
            if (isset($data['detalleObsequio']) && !empty($data['detalleObsequio'])) {
                $detalleData = $data['detalleObsequio'];
                $detalleData['id_visita'] = $id_visita;
                VisitasDetalleObsequio::guardar($detalleData);
                error_log("✅ Guardado detalle/obsequio");
            }

            // ====================================================
            // 13. GUARDAR SERVICIOS QUE NO TENEMOS
            // ====================================================
            if (isset($data['serviciosNoTenemos']) && is_array($data['serviciosNoTenemos']) && count($data['serviciosNoTenemos']) > 0) {
                VisitasServiciosNoTenemos::guardarMultiples([
                    'id_visita' => $id_visita,
                    'servicios' => $data['serviciosNoTenemos']
                ]);
                error_log("✅ Guardados servicios que no tenemos");
            }

            // ====================================================
            // 14. GUARDAR FEEDBACK PARA MEJORAR
            // ====================================================
            if (isset($data['feedbackMejorar']) && isset($data['feedbackMejorar']['aspectos']) && is_array($data['feedbackMejorar']['aspectos']) && count($data['feedbackMejorar']['aspectos']) > 0) {
                $feedbacks = [];
                foreach ($data['feedbackMejorar']['aspectos'] as $id_aspecto) {
                    $feedbacks[] = [
                        'id_aspecto_mejorar' => $id_aspecto,
                        'comentarios_mejorar' => $data['feedbackMejorar']['comentarios_mejorar'] ?? null,
                        'id_validez_feedback' => $data['feedbackMejorar']['id_validez_feedback'] ?? null
                    ];
                }

                VisitasFeedbackMejorar::guardarMultiples([
                    'id_visita' => $id_visita,
                    'feedbacks' => $feedbacks
                ]);
                error_log("✅ Guardados " . count($feedbacks) . " feedback para mejorar");
            }

            // ====================================================
            // 15. GUARDAR PERFIL DEL PROSPECTO
            // ====================================================
            if (isset($data['perfilProspecto']) && !empty($data['perfilProspecto'])) {
                $perfilData = $data['perfilProspecto'];
                $perfilData['id_visita'] = $id_visita;
                VisitasPerfilProspecto::guardar($perfilData);
                error_log("✅ Guardado perfil del prospecto");
            }

            // ====================================================
            // 16. GUARDAR COMPETENCIA
            // ====================================================
            if (isset($data['competencia']) && !empty($data['competencia'])) {
                $competenciaData = $data['competencia'];
                $competenciaData['id_visita'] = $id_visita;
                VisitasCompetencia::guardar($competenciaData);
                error_log("✅ Guardada información de competencia");
            }

            // ====================================================
            // 17. GUARDAR APRENDIZAJES
            // ====================================================
            if (isset($data['aprendizajes']) && !empty($data['aprendizajes'])) {
                $aprendizajesData = $data['aprendizajes'];
                $aprendizajesData['id_visita'] = $id_visita;
                VisitasAprendizajes::guardar($aprendizajesData);
                error_log("✅ Guardados aprendizajes");
            }
            // Commit de la transacción
            $db->commit();

            error_log("🎉 Visita completa creada exitosamente con ID: " . $id_visita);
            Flight::json(array('success' => true, 'id' => $id_visita));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("❌ Error en Visitas::crearVisitaCompleta: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    // =====================================================
    // ACTUALIZAR VISITA COMPLETA (ORQUESTADOR)
    // =====================================================
    public static function actualizarVisitaCompleta($id)
    {
        try {
            $db = Flight::db();

            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
            }

            $db->beginTransaction();

            if (isset($data['visita'])) {
                $data['visita']['id'] = $id;
                self::replace($data['visita']);
                error_log("✅ Visita actualizada ID: " . $id);
            }

            if (isset($data['visitantes']) && is_array($data['visitantes'])) {
                foreach ($data['visitantes'] as $visitante) {
                    $visitante['id_visita'] = $id;

                    if (isset($visitante['id']) && $visitante['id']) {
                        Visitantes::replace($visitante);
                    } else {
                        Visitantes::new($visitante);
                    }
                }
            }

            $stmt = $db->prepare("DELETE FROM visitas_ninos WHERE id_visita = :id_visita");
            $stmt->execute([':id_visita' => $id]);

            if (isset($data['ninos']) && is_array($data['ninos'])) {
                foreach ($data['ninos'] as $nino) {
                    $nino['id_visita'] = $id;
                    VisitasNinos::new($nino);
                }
                error_log("✅ Actualizados " . count($data['ninos']) . " niños");
            }

            if (isset($data['razonesBusqueda'])) {
                VisitasRazonesBusqueda::guardarMultiples([
                    'id_visita' => $id,
                    'razones' => is_array($data['razonesBusqueda']) ? $data['razonesBusqueda'] : []
                ]);
                error_log("✅ Actualizadas razones de búsqueda");
            }

            if (isset($data['temperatura'])) {
                $data['temperatura']['id_visita'] = $id;
                VisitasTemperatura::guardarTemperatura($data['temperatura']);
                error_log("✅ Actualizada temperatura");
            }

            if (isset($data['seguimiento'])) {
                $data['seguimiento']['id_visita'] = $id;
                VisitasSeguimiento::guardarSeguimiento($data['seguimiento']);
                error_log("✅ Actualizado seguimiento");
            }

            if (isset($data['preferencias_seguimiento'])) {
                VisitasPreferenciasSeguimiento::guardarMultiples([
                    'id_visita' => $id,
                    'preferencias' => is_array($data['preferencias_seguimiento']) ? $data['preferencias_seguimiento'] : []
                ]);
                error_log("✅ Actualizadas preferencias");
            }

            if (isset($data['compromisos'])) {
                VisitasCompromisos::guardarMultiples([
                    'id_visita' => $id,
                    'compromisos' => is_array($data['compromisos']) ? $data['compromisos'] : []
                ]);
                error_log("✅ Actualizados compromisos");
            }
            // ====================================================
            // ACTUALIZAR PROTOCOLO PASOS COMPLETADOS
            // ====================================================
            if (isset($data['protocoloPasos']) && is_array($data['protocoloPasos'])) {
                VisitasProtocoloPasosCompletados::guardarMultiples([
                    'id_visita' => $id,
                    'pasos' => $data['protocoloPasos']
                ]);
                error_log("✅ Actualizados " . count($data['protocoloPasos']) . " pasos del protocolo");
            }

            // ====================================================
            // ACTUALIZAR PROTOCOLO CHECKLIST
            // ====================================================
            if (isset($data['protocoloChecklist']) && is_array($data['protocoloChecklist'])) {
                VisitasProtocoloChecklist::guardarMultiples([
                    'id_visita' => $id,
                    'items' => $data['protocoloChecklist']
                ]);
                error_log("✅ Actualizados " . count($data['protocoloChecklist']) . " items de checklist");
            }
            error_log("✅ Antes de observacionesDisc");
            // ====================================================
            // ACTUALIZAR OBJECIONES
            // ====================================================
            if (isset($data['objeciones']) && is_array($data['objeciones'])) {
                VisitasObjeciones::guardarMultiples([
                    'id_visita' => $id,
                    'objeciones' => $data['objeciones']
                ]);
                error_log("✅ Actualizadas " . count($data['objeciones']) . " objeciones");
            }
            if (isset($data['observacionesDisc']) && !empty($data['observacionesDisc'])) {
                VisitasObservacionesDisc::guardarObservacionesPorVisitante([
                    'id_visita' => $id,
                    'observaciones_por_visitante' => $data['observacionesDisc']
                ]);
                error_log("✅ Actualizadas observaciones DISC");
            }
            error_log("✅ despues de  de observacionesDisc");
            // ====================================================
            // 9. ACTUALIZAR RESULTADO DE LA VISITA
            // ====================================================
            if (isset($data['resultado']) && is_array($data['resultado']) && !empty($data['resultado'])) {
                $resultadoData = $data['resultado'];
                $resultadoData['id_visita'] = $id;
                VisitasResultado::guardarResultado($resultadoData);
                error_log("✅ Actualizado resultado de la visita");
            }
            error_log("✅ despues de RESULTADO");

            // ====================================================
            // 10. ACTUALIZAR SERVICIOS QUE GUSTARON
            // ====================================================
            if (isset($data['serviciosGustaron']) && is_array($data['serviciosGustaron'])) {
                VisitasServiciosGustaron::guardarMultiples([
                    'id_visita' => $id,
                    'servicios' => $data['serviciosGustaron']
                ]);
                error_log("✅ Actualizados servicios que gustaron");
            }
            error_log("✅ despues de  de SERVICIOS QUE GUSTARON");
            // ====================================================
            // 11. ACTUALIZAR ASPECTOS POSITIVOS
            // ====================================================
            if (isset($data['aspectosPositivos']) && is_array($data['aspectosPositivos']) && !empty($data['aspectosPositivos'])) {
                $aspectosData = $data['aspectosPositivos'];
                $aspectosData['id_visita'] = $id;
                VisitasAspectosPositivos::guardar($aspectosData);
                error_log("✅ Actualizados aspectos positivos");
            }
            error_log("✅ despues de ASPECTOS POSITIVOS");
            // ====================================================
            // 12. ACTUALIZAR DETALLE/OBSEQUIO
            // ====================================================
            if (isset($data['detalleObsequio']) && is_array($data['detalleObsequio']) && !empty($data['detalleObsequio'])) {
                $detalleData = $data['detalleObsequio'];
                $detalleData['id_visita'] = $id;
                VisitasDetalleObsequio::guardar($detalleData);
                error_log("✅ Actualizado detalle/obsequio");
            }
            error_log("✅ despues de detalleObsequio");
            error_log("✅ despues de  de detalleObsequio");
            // ====================================================
            // 13. ACTUALIZAR SERVICIOS QUE NO TENEMOS
            // ====================================================
            if (isset($data['serviciosNoTenemos']) && is_array($data['serviciosNoTenemos']) && count($data['serviciosNoTenemos']) > 0) {
                VisitasServiciosNoTenemos::guardarMultiples([
                    'id_visita' => $id,
                    'servicios' => $data['serviciosNoTenemos']
                ]);
                error_log("✅ Actualizados servicios que no tenemos");
            }
            error_log("✅ despues de  de serviciosNoTenemos");
            // ====================================================
            // 14. ACTUALIZAR FEEDBACK PARA MEJORAR
            // ====================================================
            if (isset($data['feedbackMejorar']) && isset($data['feedbackMejorar']['aspectos']) && is_array($data['feedbackMejorar']['aspectos'])) {
                $feedbacks = [];
                foreach ($data['feedbackMejorar']['aspectos'] as $id_aspecto) {
                    $feedbacks[] = [
                        'id_aspecto_mejorar' => $id_aspecto,
                        'comentarios_mejorar' => $data['feedbackMejorar']['comentarios_mejorar'] ?? null,
                        'id_validez_feedback' => $data['feedbackMejorar']['id_validez_feedback'] ?? null
                    ];
                }

                VisitasFeedbackMejorar::guardarMultiples([
                    'id_visita' => $id,
                    'feedbacks' => $feedbacks
                ]);
                error_log("✅ Actualizados feedback para mejorar");
            }
            error_log("✅ despues de  de FEEDBACK");
            // ====================================================
            // 15. ACTUALIZAR PERFIL DEL PROSPECTO
            // ====================================================
            if (isset($data['perfilProspecto']) && is_array($data['perfilProspecto']) && !empty($data['perfilProspecto'])) {
                $perfilData = $data['perfilProspecto'];
                $perfilData['id_visita'] = $id;
                VisitasPerfilProspecto::guardar($perfilData);
                error_log("✅ Actualizado perfil del prospecto");
            }
            error_log("✅ despues de PERFIL DEL PROSPECTO");
            // ====================================================
            // 16. ACTUALIZAR COMPETENCIA
            // ====================================================
            if (isset($data['competencia']) && is_array($data['competencia']) && !empty($data['competencia'])) {
                $competenciaData = $data['competencia'];
                $competenciaData['id_visita'] = $id;
                VisitasCompetencia::guardar($competenciaData);
                error_log("✅ Actualizada información de competencia");
            }
            error_log("✅ despues de COMPETENCIA");
            // ====================================================
            // 17. ACTUALIZAR APRENDIZAJES
            // ====================================================
            if (isset($data['aprendizajes']) && is_array($data['aprendizajes']) && !empty($data['aprendizajes'])) {
                $aprendizajesData = $data['aprendizajes'];
                $aprendizajesData['id_visita'] = $id;
                VisitasAprendizajes::guardar($aprendizajesData);
                error_log("✅ Actualizados aprendizajes");
            }

            error_log("🔍 ANTES DE COMMIT - Todo OK hasta aquí");
            $db->commit();


            error_log("✅ COMMIT EXITOSO");

            error_log("✅ Visita completa actualizada exitosamente ID: " . $id);
            Flight::json(array('success' => true, 'id' => $id));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("❌ Error en Visitas::actualizarVisitaCompleta: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
    public static function getDashboardStats()
    {
        try {
            $db = Flight::db();

            // Fechas
            $hoy = date('Y-m-d');
            $inicioMesActual = date('Y-m-01');
            $inicioMesAnterior = date('Y-m-01', strtotime('-1 month'));
            $finMesAnterior = date('Y-m-t', strtotime('-1 month'));
            $inicioUltimos6Meses = date('Y-m-01', strtotime('-5 months'));

            $stats = [];

            // 1. Total visitas mes actual vs anterior
            $stmt = $db->prepare("
            SELECT COUNT(*) as total FROM visitas 
            WHERE fecha BETWEEN :inicio AND :fin
        ");

            $stmt->execute([':inicio' => $inicioMesActual, ':fin' => $hoy]);
            $stats['visitasMesActual'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt->execute([':inicio' => $inicioMesAnterior, ':fin' => $finMesAnterior]);
            $stats['visitasMesAnterior'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 2. Distribución por resultado
            $stmt = $db->prepare("
            SELECT 
                trv.nombre,
                trv.codigo,
                COUNT(*) as cantidad
            FROM visitas v
            INNER JOIN visitas_resultado vr ON v.id = vr.id_visita
            INNER JOIN tipos_resultado_visita trv ON vr.id_tipo_resultado = trv.id
            WHERE v.fecha >= :inicio
            GROUP BY trv.id, trv.nombre, trv.codigo
            ORDER BY cantidad DESC
        ");
            $stmt->execute([':inicio' => $inicioUltimos6Meses]);
            $stats['porResultado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Nivel de interés - conteo por tipo
            $stmt = $db->prepare("
            SELECT 
                tni.nombre,
                tni.id,
                COUNT(*) as cantidad
            FROM visitas v
            INNER JOIN visitas_temperatura vt ON v.id = vt.id_visita
            INNER JOIN tipos_nivel_interes tni ON vt.id_nivel_interes = tni.id
            WHERE v.fecha >= :inicio
            GROUP BY tni.id, tni.nombre
            ORDER BY tni.id DESC
        ");
            $stmt->execute([':inicio' => $inicioMesActual]);
            $nivelesInteres = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular promedio ponderado (asumiendo que el ID representa el nivel 1-5)
            $totalVisitas = 0;
            $sumaPonderada = 0;
            foreach ($nivelesInteres as $nivel) {
                $totalVisitas += $nivel['cantidad'];
                $sumaPonderada += $nivel['id'] * $nivel['cantidad'];
            }

            $stats['nivelInteres'] = [
                'promedio' => $totalVisitas > 0 ? $sumaPonderada / $totalVisitas : 0,
                'total' => $totalVisitas
            ];

            // 4. Evolución últimos 6 meses
            $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(fecha, '%Y-%m') as mes,
                COUNT(*) as total
            FROM visitas
            WHERE fecha >= :inicio
            GROUP BY DATE_FORMAT(fecha, '%Y-%m')
            ORDER BY mes
        ");
            $stmt->execute([':inicio' => $inicioUltimos6Meses]);
            $stats['evolucionMensual'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 5. Top 5 Cómo conoció
            $stmt = $db->prepare("
            SELECT 
                tcc.nombre,
                COUNT(*) as cantidad
            FROM visitas v
            INNER JOIN tipos_como_conocio tcc ON v.id_como_conocio = tcc.id
            WHERE v.fecha >= :inicio
            GROUP BY tcc.id, tcc.nombre
            ORDER BY cantidad DESC
            LIMIT 5
        ");
            $stmt->execute([':inicio' => $inicioUltimos6Meses]);
            $stats['topComoConocio'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 6. Objeciones más frecuentes
            $stmt = $db->prepare("
            SELECT 
                tobj.nombre,
                COUNT(*) as cantidad,
                SUM(CASE WHEN vo.superada = 1 THEN 1 ELSE 0 END) as superadas
            FROM visitas_objeciones vo
            INNER JOIN tipos_objeciones tobj ON vo.id_tipo_objecion = tobj.id
            INNER JOIN visitas v ON vo.id_visita = v.id
            WHERE v.fecha >= :inicio
            GROUP BY tobj.id, tobj.nombre
            ORDER BY cantidad DESC
            LIMIT 10
        ");
            $stmt->execute([':inicio' => $inicioUltimos6Meses]);
            $stats['objecionesFrecuentes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 7. Distribución perfiles DISC
            $stmt = $db->prepare("
            SELECT 
                perfil_disc_calculado as perfil,
                COUNT(*) as cantidad
            FROM visitantes
            WHERE perfil_disc_calculado IS NOT NULL
            AND es_contacto_principal = 1
            GROUP BY perfil_disc_calculado
            ORDER BY cantidad DESC
        ");
            $stmt->execute();
            $stats['perfilesDisc'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($stats);
        } catch (Exception $e) {
            error_log("Error en getDashboardStats: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}
