<?php
/**
 * Estudiantes que el acudiente escogio en cada intento de autoregistro, con
 * el parentesco y, cuando el registro termina, el acudiente que quedo creado.
 */
class EnlacesAutoregistroVinculos
{
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_intento, id_estudiante, id_tipo_acudiente, id_acudiente, resultado, fecha_registro
                                  FROM enlaces_autoregistro_vinculos
                                  WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_intento, id_estudiante, id_tipo_acudiente, id_acudiente, resultado, fecha_registro
                                  FROM enlaces_autoregistro_vinculos
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getByIntento($idIntento)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.enlaces_autoregistro');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT v.id, v.id_intento, v.id_estudiante, v.id_tipo_acudiente, v.id_acudiente, v.resultado,
                                         ta.nombre AS tipo_acudiente,
                                         TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_estudiante
                                  FROM enlaces_autoregistro_vinculos v
                                  INNER JOIN estudiantes e ON e.id = v.id_estudiante AND e.id_tenant = v.id_tenant
                                  INNER JOIN personas p ON p.id = e.id_persona
                                  LEFT JOIN tipos_acudiente ta ON ta.id = v.id_tipo_acudiente
                                  WHERE v.id_intento = :id_intento AND v.id_tenant = :id_tenant
                                  ORDER BY p.primer_nombre");
        $sentence->bindParam(':id_intento', $idIntento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * Reemplaza los estudiantes escogidos en un intento. Uso interno: lo
     * llama el paso de seleccion de la pagina publica.
     *
     * @param PDO    $db
     * @param string $idIntento
     * @param array  $seleccion [ ['id_estudiante' => ..., 'id_tipo_acudiente' => ...], ... ]
     * @return void
     */
    public static function reemplazarPorIntento(PDO $db, $idIntento, $seleccion)
    {
        $borrar = $db->prepare("DELETE FROM enlaces_autoregistro_vinculos WHERE id_intento = :id_intento AND id_tenant = :id_tenant");
        $borrar->bindParam(':id_intento', $idIntento);
        $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $borrar->execute();

        $insertar = $db->prepare("INSERT INTO enlaces_autoregistro_vinculos (id, id_tenant, id_intento, id_estudiante, id_tipo_acudiente)
                                  VALUES (:id, :id_tenant, :id_intento, :id_estudiante, :id_tipo_acudiente)");

        foreach ($seleccion as $item) {
            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_intento', $idIntento);
            $insertar->bindValue(':id_estudiante', $item['id_estudiante']);
            $insertar->bindValue(':id_tipo_acudiente', (int) $item['id_tipo_acudiente'], PDO::PARAM_INT);
            $insertar->execute();
        }
    }

    /**
     * Deja registrado el acudiente con el que quedo cada estudiante del intento.
     *
     * @param PDO    $db
     * @param string $idIntento
     * @param string $idEstudiante
     * @param string|null $idAcudiente
     * @param string $resultado creado | ya_existia | omitido
     * @return void
     */
    public static function registrarResultado(PDO $db, $idIntento, $idEstudiante, $idAcudiente, $resultado)
    {
        $sentence = $db->prepare("UPDATE enlaces_autoregistro_vinculos
                                  SET id_acudiente = :id_acudiente, resultado = :resultado
                                  WHERE id_intento = :id_intento AND id_estudiante = :id_estudiante AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_acudiente', $idAcudiente);
        $sentence->bindValue(':resultado', $resultado);
        $sentence->bindValue(':id_intento', $idIntento);
        $sentence->bindValue(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }
}
