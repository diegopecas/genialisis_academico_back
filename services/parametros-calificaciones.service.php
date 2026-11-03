<?php 
class ParametrosCalificaciones
{

    /**
     * Todos los parámetros, activos y apagados, en su orden. Es para la
     * administración, los informes y lo que muestra calificaciones ya hechas.
     * total_calificaciones dice cuántas calificaciones tiene cada uno.
     * Las pantallas de calificar usan getActivos.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pc.id, pc.nombre, pc.activo, pc.orden,
                (SELECT COUNT(*) FROM calificaciones c
                 WHERE c.id_parametro_calificacion = pc.id AND c.id_tenant = pc.id_tenant) AS total_calificaciones
            FROM parametros_calificaciones pc
            WHERE pc.id_tenant = ?
            ORDER BY pc.orden IS NULL, pc.orden, pc.nombre");
        $sentence->execute([TenantContext::id()]);
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * GET /parametros-calificaciones/activos
     * Solo los activos, en su orden: los que salen al calificar.
     */
    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, activo, orden
            FROM parametros_calificaciones
            WHERE id_tenant = ? AND activo = 1
            ORDER BY orden IS NULL, orden, nombre");
        $sentence->execute([TenantContext::id()]);
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, activo, orden FROM parametros_calificaciones WHERE id = ? AND id_tenant = ?");
        $sentence->execute([$id, TenantContext::id()]);
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        // Sin orden, el parámetro nuevo queda de último
        $orden = isset($data->orden) && $data->orden !== '' && $data->orden !== null ? (int) $data->orden : null;
        if ($orden === null) {
            $consulta = $db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM parametros_calificaciones WHERE id_tenant = ?");
            $consulta->execute([TenantContext::id()]);
            $orden = (int) $consulta->fetchColumn();
        }
        $activo = isset($data->activo) ? ((int) (bool) $data->activo) : 1;

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO parametros_calificaciones (id, id_tenant, nombre, activo, orden) VALUES (?, ?, ?, ?, ?)");
        $sentence->execute([
            $idNew,
            TenantContext::id(),
            $data->nombre,
            $activo,
            $orden
        ]);
        
        $lastId = $idNew;
        Flight::json(['id' => $lastId, 'message' => 'Parámetro creado exitosamente']);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;
        
        // activo y orden son opcionales: si no llegan se conserva lo que había
        $sentence = $db->prepare("UPDATE parametros_calificaciones
            SET nombre = ?,
                activo = COALESCE(?, activo),
                orden = COALESCE(?, orden)
            WHERE id = ? AND id_tenant = ?");
        $sentence->execute([
            $data->nombre,
            isset($data->activo) ? ((int) (bool) $data->activo) : null,
            isset($data->orden) && $data->orden !== '' ? (int) $data->orden : null,
            $data->id,
            TenantContext::id()
        ]);
        
        Flight::json(['message' => 'Parámetro actualizado exitosamente']);
    }

    public static function delete()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        // Con calificaciones registradas no se borra: la llave foránea lo
        // impide y además se perderían notas reales. Se apaga en su lugar.
        $consulta = $db->prepare("SELECT COUNT(*) FROM calificaciones WHERE id_parametro_calificacion = ? AND id_tenant = ?");
        $consulta->execute([$data->id, TenantContext::id()]);
        $total = (int) $consulta->fetchColumn();
        if ($total > 0) {
            Flight::json([
                'error' => "Este parámetro tiene {$total} calificaciones registradas y no se puede eliminar. Apágalo para que no salga al calificar.",
                'total_calificaciones' => $total
            ], 409);
            return;
        }
        
        // Primero eliminar los valores asociados
        $sentence = $db->prepare("DELETE FROM valores_parametros_calificaciones WHERE id_parametros_calificaciones = ? AND id_tenant = ?");
        $sentence->execute([$data->id, TenantContext::id()]);
        
        // Luego eliminar el parámetro
        $sentence = $db->prepare("DELETE FROM parametros_calificaciones WHERE id = ? AND id_tenant = ?");
        $sentence->execute([$data->id, TenantContext::id()]);
        
        Flight::json(['message' => 'Parámetro eliminado exitosamente']);
    }
    
}