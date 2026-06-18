<?php
class CategoriasMedidas
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT id, nombre, descripcion, icono, orden, activo 
                FROM categorias_medidas 
                WHERE activo = 1 
                AND id_tenant = :id_tenant
                ORDER BY orden
            ");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en CategoriasMedidas::getAll: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener categorías de medidas'], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT id, nombre, descripcion, icono, orden, activo 
                FROM categorias_medidas 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en CategoriasMedidas::getById: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener categoría'], 500);
        }
    }

    public static function getAllConMedidas()
    {
        try {
            $db = Flight::db();

            $stmtCat = $db->prepare("
                SELECT id, nombre, descripcion, icono, orden 
                FROM categorias_medidas 
                WHERE activo = 1 
                AND id_tenant = :id_tenant
                ORDER BY orden
            ");
            $stmtCat->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCat->execute();
            $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            $stmtMed = $db->prepare("
                SELECT 
                    m.id, m.nombre, m.id_categoria, m.id_unidad, m.id_tipo_valor, m.orden,
                    umc.abreviatura AS unidad_abreviatura,
                    umc.nombre AS unidad_nombre,
                    tvm.nombre AS tipo_valor
                FROM medidas m
                LEFT JOIN unidades_medidas_corporales umc ON umc.id = m.id_unidad
                LEFT JOIN tipos_valor_medida tvm ON tvm.id = m.id_tipo_valor
                WHERE m.id_tenant = :id_tenant
                ORDER BY m.id_categoria, m.orden
            ");
            $stmtMed->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtMed->execute();
            $medidas = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

            $stmtOpciones = $db->prepare("
                SELECT id, id_medida, valor_numerico, etiqueta, orden
                FROM valores_medidas
                WHERE activo = 1
                AND id_tenant = :id_tenant
                ORDER BY orden
            ");
            $stmtOpciones->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtOpciones->execute();
            $opciones = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);

            $opcionesPorMedida = [];
            foreach ($opciones as $opcion) {
                $idMedida = $opcion['id_medida'];
                if (!isset($opcionesPorMedida[$idMedida])) {
                    $opcionesPorMedida[$idMedida] = [];
                }
                $opcionesPorMedida[$idMedida][] = $opcion;
            }

            $medidasPorCategoria = [];
            foreach ($medidas as &$medida) {
                $idCat = $medida['id_categoria'];
                if ($medida['tipo_valor'] === 'select' && isset($opcionesPorMedida[$medida['id']])) {
                    $medida['opciones'] = $opcionesPorMedida[$medida['id']];
                }
                if (!isset($medidasPorCategoria[$idCat])) {
                    $medidasPorCategoria[$idCat] = [];
                }
                $medidasPorCategoria[$idCat][] = $medida;
            }

            foreach ($categorias as &$categoria) {
                $categoria['medidas'] = isset($medidasPorCategoria[$categoria['id']]) 
                    ? $medidasPorCategoria[$categoria['id']] 
                    : [];
            }

            Flight::json($categorias);
        } catch (Exception $e) {
            error_log('Error en CategoriasMedidas::getAllConMedidas: ' . $e->getMessage());
            Flight::json(['error' => 'Error al obtener categorías con medidas'], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $nombre = $request->data->nombre;
            $descripcion = isset($request->data->descripcion) ? $request->data->descripcion : null;
            $icono = isset($request->data->icono) ? $request->data->icono : null;
            $orden = isset($request->data->orden) ? $request->data->orden : 0;
            $activo = isset($request->data->activo) ? $request->data->activo : 1;

            $idNew = Uuid::generar();
            $stmt = $db->prepare("INSERT INTO categorias_medidas (id, id_tenant, nombre, descripcion, icono, orden, activo) VALUES (:id, :id_tenant, :nombre, :descripcion, :icono, :orden, :activo)");
            $stmt->bindValue(':id', $idNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':icono', $icono);
            $stmt->bindParam(':orden', $orden);
            $stmt->bindParam(':activo', $activo);
            $stmt->execute();
            $id = $idNew;
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en CategoriasMedidas::new: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            $nombre = $request->data->nombre;
            $descripcion = isset($request->data->descripcion) ? $request->data->descripcion : null;
            $icono = isset($request->data->icono) ? $request->data->icono : null;
            $orden = isset($request->data->orden) ? $request->data->orden : 0;
            $activo = isset($request->data->activo) ? $request->data->activo : 1;

            $stmt = $db->prepare("UPDATE categorias_medidas SET nombre = :nombre, descripcion = :descripcion, icono = :icono, orden = :orden, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':icono', $icono);
            $stmt->bindParam(':orden', $orden);
            $stmt->bindParam(':activo', $activo);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en CategoriasMedidas::replace: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;

            $stmt = $db->prepare("DELETE FROM categorias_medidas WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log('Error en CategoriasMedidas::delete: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}