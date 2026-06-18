<?php
class WaContactos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT wc.*, 
                   p.primer_nombre, 
                   p.segundo_nombre, 
                   p.primer_apellido, 
                   p.segundo_apellido,
                   p.razon_social,
                   CASE 
                       WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
                       ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                                  IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
                   END AS nombre_completo
            FROM wa_contactos wc
            LEFT JOIN personas p ON wc.id_persona = p.id
            WHERE wc.id_tenant = :id_tenant
            ORDER BY wc.fecha_primera_interaccion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM wa_contactos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByPhone($phone)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM wa_contactos WHERE numero_telefono = :phone AND id_tenant = :id_tenant");
        $sentence->bindParam(':phone', $phone);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            
            $numero_telefono = Flight::request()->data['numero_telefono'];
            $nombre_whatsapp = Flight::request()->data['nombre_whatsapp'] ?? null;
            $id_persona = Flight::request()->data['id_persona'] ?? null;
            
            $sentence = $db->prepare("
                INSERT INTO wa_contactos(
                    id,
                    id_tenant,
                    numero_telefono, 
                    nombre_whatsapp,
                    id_persona,
                    fecha_primera_interaccion
                ) VALUES (
                    :id,
                    :id_tenant,
                    :numero_telefono, 
                    :nombre_whatsapp,
                    :id_persona,
                    NOW()
                )
            ");
            
            $idContacto = Uuid::generar();
            $sentence->bindValue(':id', $idContacto);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':numero_telefono', $numero_telefono);
            $sentence->bindParam(':nombre_whatsapp', $nombre_whatsapp);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->execute();
            
            Flight::json(array('id' => $idContacto));
            
        } catch (Exception $e) {
            error_log("Error creando contacto WA: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function updatePersona()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_persona = Flight::request()->data['id_persona'];
        
        $sentence = $db->prepare("UPDATE wa_contactos SET id_persona = :id_persona WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        self::getById($id);
    }

    public static function findOrCreate()
    {
        $db = Flight::db();
        $numero_telefono = Flight::request()->data['numero_telefono'];
        $nombre_whatsapp = Flight::request()->data['nombre_whatsapp'] ?? null;
        
        // Buscar existente
        $sentence = $db->prepare("SELECT * FROM wa_contactos WHERE numero_telefono = :phone AND id_tenant = :id_tenant");
        $sentence->bindParam(':phone', $numero_telefono);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $contacto = $sentence->fetch();
        
        if ($contacto) {
            Flight::json($contacto);
        } else {
            // Crear nuevo
            self::new();
        }
    }
}