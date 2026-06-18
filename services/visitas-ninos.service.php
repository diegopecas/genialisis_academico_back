<?php
class VisitasNinos
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vn.*,
                    v.fecha as fecha_visita,
                    v.hora as hora_visita
                FROM visitas_ninos vn
                INNER JOIN visitas v ON vn.id_visita = v.id
                WHERE vn.id_tenant = :id_tenant
                ORDER BY v.fecha DESC, v.hora DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en visitas_ninos getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }



    /**
     * Crear un nuevo niño con todos los campos actualizados
     */
    public static function new($dataParam = null)
    {
        try {
            $db = Flight::db();

            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $requestBody = Flight::request()->getBody();
                $data = json_decode($requestBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
                }
            }

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO visitas_ninos (
                    id, 
                    id_tenant, 
                    id_visita, 
                    nombre_nino, 
                    fecha_nacimiento, 
                    id_grupo, 
                    id_genero,
                    dato_significativo, 
                    tiene_hermanos_en_jardin, 
                    nombre_hermanos_en_jardin,
                    ha_estado_escolarizado, 
                    donde_estuvo
                ) VALUES (
                    :id, 
                    :id_tenant, 
                    :id_visita, 
                    :nombre_nino, 
                    :fecha_nacimiento, 
                    :id_grupo, 
                    :id_genero,
                    :dato_significativo, 
                    :tiene_hermanos_en_jardin, 
                    :nombre_hermanos_en_jardin,
                    :ha_estado_escolarizado, 
                    :donde_estuvo
                )
            ");

            // Manejar valores NULL para campos opcionales
            $id_grupo = isset($data['id_grupo']) && $data['id_grupo'] !== '' && $data['id_grupo'] !== 'null'
                ? $data['id_grupo']
                : null;

            $id_genero = isset($data['id_genero']) && $data['id_genero'] !== '' && $data['id_genero'] !== 'null'
                ? $data['id_genero']
                : null;

            $fecha_nacimiento = isset($data['fecha_nacimiento']) && $data['fecha_nacimiento'] !== '' && $data['fecha_nacimiento'] !== 'null'
                ? $data['fecha_nacimiento']
                : null;

            $dato_significativo = isset($data['dato_significativo']) ? $data['dato_significativo'] : null;
            $nombre_hermanos = isset($data['nombre_hermanos_en_jardin']) ? $data['nombre_hermanos_en_jardin'] : null;
            $donde_estuvo = isset($data['donde_estuvo']) ? $data['donde_estuvo'] : null;

            // Convertir booleanos
            $tiene_hermanos = isset($data['tiene_hermanos_en_jardin']) ? ($data['tiene_hermanos_en_jardin'] ? 1 : 0) : 0;
            $ha_estado_escolarizado = isset($data['ha_estado_escolarizado']) ? ($data['ha_estado_escolarizado'] ? 1 : 0) : 0;

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $data['id_visita']);
            $sentence->bindParam(':nombre_nino', $data['nombre_nino']);
            $sentence->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_genero', $id_genero, PDO::PARAM_INT);
            $sentence->bindParam(':dato_significativo', $dato_significativo);
            $sentence->bindParam(':tiene_hermanos_en_jardin', $tiene_hermanos);
            $sentence->bindParam(':nombre_hermanos_en_jardin', $nombre_hermanos);
            $sentence->bindParam(':ha_estado_escolarizado', $ha_estado_escolarizado);
            $sentence->bindParam(':donde_estuvo', $donde_estuvo);

            $sentence->execute();
            $idNino = $idNew;

            // ✅ Manejar necesidades especiales si existen
            if (isset($data['necesidades']) && is_array($data['necesidades'])) {
                foreach ($data['necesidades'] as $necesidad) {
                    if (isset($necesidad['id_tipo_necesidad'])) {
                        VisitasNinosNecesidades::new([
                            'id_visita_nino' => $idNino,
                            'id_tipo_necesidad' => $necesidad['id_tipo_necesidad'],
                            'detalle' => $necesidad['detalle'] ?? null
                        ]);
                    }
                }
            }

            if ($dataParam !== null) {
                return $idNino;
            }

            Flight::json(array('id' => $idNino));
        } catch (Exception $e) {
            error_log("Error en visitas_ninos new: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un niño existente
     */
    public static function replace($dataParam = null)
    {
        try {
            $db = Flight::db();

            if ($dataParam !== null) {
                $data = $dataParam;
            } else {
                $data = Flight::request()->data;
            }

            if (!isset($data['id']) || empty($data['id'])) {
                throw new Exception('ID de niño requerido para actualizar');
            }

            $sentence = $db->prepare("
                UPDATE visitas_ninos SET
                    nombre_nino = :nombre_nino,
                    fecha_nacimiento = :fecha_nacimiento,
                    id_grupo = :id_grupo,
                    id_genero = :id_genero,
                    dato_significativo = :dato_significativo,
                    tiene_hermanos_en_jardin = :tiene_hermanos_en_jardin,
                    nombre_hermanos_en_jardin = :nombre_hermanos_en_jardin,
                    ha_estado_escolarizado = :ha_estado_escolarizado,
                    donde_estuvo = :donde_estuvo
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            // Manejar valores NULL
            $id_grupo = isset($data['id_grupo']) && $data['id_grupo'] !== '' && $data['id_grupo'] !== 'null'
                ? $data['id_grupo']
                : null;

            $id_genero = isset($data['id_genero']) && $data['id_genero'] !== '' && $data['id_genero'] !== 'null'
                ? $data['id_genero']
                : null;

            $fecha_nacimiento = isset($data['fecha_nacimiento']) && $data['fecha_nacimiento'] !== '' && $data['fecha_nacimiento'] !== 'null'
                ? $data['fecha_nacimiento']
                : null;

            $dato_significativo = isset($data['dato_significativo']) ? $data['dato_significativo'] : null;
            $nombre_hermanos = isset($data['nombre_hermanos_en_jardin']) ? $data['nombre_hermanos_en_jardin'] : null;
            $donde_estuvo = isset($data['donde_estuvo']) ? $data['donde_estuvo'] : null;

            // Convertir booleanos
            $tiene_hermanos = isset($data['tiene_hermanos_en_jardin']) ? ($data['tiene_hermanos_en_jardin'] ? 1 : 0) : 0;
            $ha_estado_escolarizado = isset($data['ha_estado_escolarizado']) ? ($data['ha_estado_escolarizado'] ? 1 : 0) : 0;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre_nino', $data['nombre_nino']);
            $sentence->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_genero', $id_genero, PDO::PARAM_INT);
            $sentence->bindParam(':dato_significativo', $dato_significativo);
            $sentence->bindParam(':tiene_hermanos_en_jardin', $tiene_hermanos);
            $sentence->bindParam(':nombre_hermanos_en_jardin', $nombre_hermanos);
            $sentence->bindParam(':ha_estado_escolarizado', $ha_estado_escolarizado);
            $sentence->bindParam(':donde_estuvo', $donde_estuvo);

            $sentence->execute();

            // ✅ Actualizar necesidades especiales
            if (isset($data['necesidades'])) {
                // Eliminar necesidades anteriores
                VisitasNinosNecesidades::deleteByNino($data['id']);

                // Insertar nuevas necesidades
                if (is_array($data['necesidades'])) {
                    foreach ($data['necesidades'] as $necesidad) {
                        if (isset($necesidad['id_tipo_necesidad'])) {
                            VisitasNinosNecesidades::new([
                                'id_visita_nino' => $data['id'],
                                'id_tipo_necesidad' => $necesidad['id_tipo_necesidad'],
                                'detalle' => $necesidad['detalle'] ?? null
                            ]);
                        }
                    }
                }
            }

            if ($dataParam !== null) {
                return true;
            }

            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en visitas_ninos replace: " . $e->getMessage());

            if ($dataParam !== null) {
                throw $e;
            }

            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtener niño por ID con sus necesidades
     */
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vn.*,
                    g.nombre as nombre_grupo,
                    gen.nombre as nombre_genero
                FROM visitas_ninos vn
                LEFT JOIN grupos g ON vn.id_grupo = g.id
                LEFT JOIN generos gen ON vn.id_genero = gen.id
                WHERE vn.id = :id AND vn.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $nino = $sentence->fetch(PDO::FETCH_ASSOC);

            // Obtener necesidades especiales del niño
            if ($nino) {
                $sentenceNecesidades = $db->prepare("
                    SELECT 
                        vnn.*,
                        tne.nombre as nombre_necesidad,
                        tne.icono as icono_necesidad
                    FROM visitas_ninos_necesidades vnn
                    INNER JOIN tipos_necesidades_especiales tne ON vnn.id_tipo_necesidad = tne.id
                    WHERE vnn.id_visita_nino = :id_visita_nino AND vnn.id_tenant = :id_tenant
                ");
                $sentenceNecesidades->bindParam(':id_visita_nino', $id);
                $sentenceNecesidades->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceNecesidades->execute();
                $nino['necesidades'] = $sentenceNecesidades->fetchAll(PDO::FETCH_ASSOC);
            }

            Flight::json($nino);
        } catch (Exception $e) {
            error_log("Error en visitas_ninos getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtener todos los niños de una visita con sus necesidades
     */
    public static function getByVisita($id_visita)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    vn.*,
                    g.nombre as nombre_grupo,
                    gen.nombre as nombre_genero
                FROM visitas_ninos vn
                LEFT JOIN grupos g ON vn.id_grupo = g.id
                LEFT JOIN generos gen ON vn.id_genero = gen.id
                WHERE vn.id_visita = :id_visita AND vn.id_tenant = :id_tenant
                ORDER BY vn.id
            ");
            $sentence->bindParam(':id_visita', $id_visita);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $ninos = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Para cada niño, obtener sus necesidades especiales
            foreach ($ninos as &$nino) {
                $sentenceNecesidades = $db->prepare("
                    SELECT 
                        vnn.*,
                        tne.nombre as nombre_necesidad,
                        tne.icono as icono_necesidad
                    FROM visitas_ninos_necesidades vnn
                    INNER JOIN tipos_necesidades_especiales tne ON vnn.id_tipo_necesidad = tne.id
                    WHERE vnn.id_visita_nino = :id_visita_nino AND vnn.id_tenant = :id_tenant
                ");
                $sentenceNecesidades->bindParam(':id_visita_nino', $nino['id']);
                $sentenceNecesidades->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceNecesidades->execute();
                $nino['necesidades'] = $sentenceNecesidades->fetchAll(PDO::FETCH_ASSOC);
            }

            Flight::json($ninos);
        } catch (Exception $e) {
            error_log("Error en visitas_ninos getByVisita: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar niño y sus necesidades asociadas
     */
    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            // Primero eliminar necesidades (CASCADE debería hacerlo, pero por si acaso)
            VisitasNinosNecesidades::deleteByNino($id);

            // Luego eliminar el niño
            $sentence = $db->prepare("DELETE FROM visitas_ninos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en visitas_ninos delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
