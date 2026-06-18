<?php 
class CasasColaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, imagen, color, puntos_entregar, puntos_quitar 
        FROM casas_colaboradores 
        WHERE id_tenant = :id_tenant
        ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, imagen, color, puntos_entregar, puntos_quitar 
        FROM casas_colaboradores 
        WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        
        Flight::json($response);
    }

    public static function sumarPuntosEntregar()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $puntos = Flight::request()->data['puntos'];
            
            error_log("Sumando puntos a casa colaborador: id=$id, puntos=$puntos");
            
            $sentence = $db->prepare("UPDATE casas_colaboradores SET 
                puntos_entregar = puntos_entregar + :puntos
                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':puntos', $puntos);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            self::getById($id);
            
        } catch (Exception $e) {
            error_log("Error en sumarPuntosEntregar de casas colaboradores: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar los puntos. Inténtalo más tarde.'), 500);
        }
    }

    public static function restarPuntosQuitar()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $puntos = Flight::request()->data['puntos'];
            
            error_log("Restando puntos a casa colaborador: id=$id, puntos=$puntos");
            
            $sentence = $db->prepare("UPDATE casas_colaboradores SET 
                puntos_quitar = puntos_quitar + :puntos
                WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':puntos', $puntos);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            
            self::getById($id);
            
        } catch (Exception $e) {
            error_log("Error en restarPuntosQuitar de casas colaboradores: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar los puntos. Inténtalo más tarde.'), 500);
        }
    }
}