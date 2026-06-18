<?php 
class PuntosCasasDocentes
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select pcd.id, pcd.id_docente_entrega, pcd.id_docente_recibe, pcd.valor, pcd.fecha, pcd.id_casa_docente, pcd.observacion,
            CONCAT(pde.primer_nombre, ' ', pde.segundo_nombre, ' ', pde.primer_apellido, ' ', pde.segundo_apellido) nombre_docente_entrega,
            CONCAT(pdr.primer_nombre, ' ', pdr.segundo_nombre, ' ', pdr.primer_apellido, ' ', pdr.segundo_apellido) nombre_docente_recibe
            from puntos_casas_docentes pcd
            left outer join docentes de on pcd.id_docente_entrega = de.id
            left outer join docentes dr on pcd.id_docente_recibe = dr.id
            left outer join personas pde on de.id_persona = pde.id
            left outer join personas pdr on dr.id_persona = pdr.id
            where pcd.id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getAllByCasa($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select pcd.id, pcd.id_docente_entrega, pcd.id_docente_recibe, pcd.valor, pcd.fecha, pcd.id_casa_docente, pcd.observacion,
            CONCAT(pde.primer_nombre, ' ', pde.segundo_nombre, ' ', pde.primer_apellido, ' ', pde.segundo_apellido) nombre_docente_entrega,
            CONCAT(pdr.primer_nombre, ' ', pdr.segundo_nombre, ' ', pdr.primer_apellido, ' ', pdr.segundo_apellido) nombre_docente_recibe
            from puntos_casas_docentes pcd
            left outer join docentes de on pcd.id_docente_entrega = de.id
            left outer join docentes dr on pcd.id_docente_recibe = dr.id
            left outer join personas pde on de.id_persona = pde.id
            left outer join personas pdr on dr.id_persona = pdr.id
            where pcd.id_casa_docente = :id and pcd.id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_docente_entrega = Flight::request()->data['id_docente_entrega'];
        $id_docente_recibe = Flight::request()->data['id_docente_recibe'];
        $id_casa_docente = Flight::request()->data['id_casa_docente'];
        // $id_tipo_puntos = Flight::request()->data['id_tipo_puntos'];
        $valor = Flight::request()->data['valor'];
        $observacion = Flight::request()->data['observacion'];
        
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into puntos_casas_docentes(id, id_tenant, id_docente_entrega, id_docente_recibe, id_casa_docente, valor, observacion) values (:id, :id_tenant, :id_docente_entrega, :id_docente_recibe, :id_casa_docente, :valor, :observacion)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_docente_entrega', $id_docente_entrega);
        $sentence->bindParam(':id_docente_recibe', $id_docente_recibe);
        $sentence->bindParam(':id_casa_docente', $id_casa_docente);
        // $sentence->bindParam(':id_tipo_puntos', $id_tipo_puntos);
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':observacion', $observacion);
        
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_docente_entrega = Flight::request()->data['id_docente_entrega'];
        $id_docente_recibe = Flight::request()->data['id_docente_recibe'];
        $id_casa_docente = Flight::request()->data['id_casa_docente'];
        // $id_tipo_puntos = Flight::request()->data['id_tipo_puntos'];
        $valor = Flight::request()->data['valor'];
        $observacion = Flight::request()->data['observacion'];

        $sentence = $db->prepare("update puntos_casas_docentes set id_docente_entrega = :id_docente_entrega, id_docente_recibe = :id_docente_recibe, id_casa_docente = :id_casa_docente, valor = :valor, observacion = :observacion where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id_docente_entrega', $id_docente_entrega);
        $sentence->bindParam(':id_docente_recibe', $id_docente_recibe);
        $sentence->bindParam(':id_casa_docente', $id_casa_docente);
        // $sentence->bindParam(':id_tipo_puntos', $id_tipo_puntos);
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':observacion', $observacion);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
