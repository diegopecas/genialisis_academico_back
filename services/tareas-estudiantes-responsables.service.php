<?php
/*=============================================
SERVICIO - TAREAS ESTUDIANTES RESPONSABLES
Archivo: services/tareas-estudiantes-responsables.service.php

Colaboradores responsables de una tarea: son los que reciben la alerta
cuando un acudiente hace una pregunta. Responder puede cualquiera con el
permiso del modulo, sea o no responsable.
=============================================*/

class TareasEstudiantesResponsables
{
    /**
     * Colaboradores activos del tenant para marcar responsables.
     */
    public static function getColaboradores()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, TareasEstudiantes::PERMISO);

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT c.id,
                   TRIM(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre,
                   c.sobrenombre,
                   rc.nombre AS rol_nombre
            FROM colaboradores c
            INNER JOIN personas p ON p.id = c.id_persona
            LEFT JOIN roles_colaborador rc ON rc.id = c.id_rol_colaborador
            WHERE c.id_tenant = :id_tenant
              AND c.activo = 1
            ORDER BY p.primer_nombre, p.primer_apellido
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    /**
     * @return array Ids de colaborador responsables de la tarea
     */
    public static function idsPorTarea(PDO $db, $idTarea)
    {
        $sentence = $db->prepare("SELECT id_colaborador FROM tareas_estudiantes_responsables WHERE id_tarea_estudiante = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return array_column($sentence->fetchAll(), 'id_colaborador');
    }

    /**
     * Deja exactamente los responsables recibidos, solo colaboradores del
     * tenant.
     */
    public static function sincronizar(PDO $db, $idTarea, array $colaboradores)
    {
        $borrar = $db->prepare("DELETE FROM tareas_estudiantes_responsables WHERE id_tarea_estudiante = :id AND id_tenant = :id_tenant");
        $borrar->bindParam(':id', $idTarea);
        $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $borrar->execute();

        if (count($colaboradores) === 0) {
            return;
        }

        $marcadores = implode(',', array_fill(0, count($colaboradores), '?'));
        $validos = $db->prepare("SELECT id FROM colaboradores WHERE id_tenant = ? AND id IN ($marcadores)");
        $validos->execute(array_merge(array(TenantContext::id()), $colaboradores));

        $insertar = $db->prepare("INSERT INTO tareas_estudiantes_responsables (id, id_tenant, id_tarea_estudiante, id_colaborador)
                                  VALUES (:id, :id_tenant, :id_tarea, :id_colaborador)");
        foreach (array_column($validos->fetchAll(), 'id') as $idColaborador) {
            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_tarea', $idTarea);
            $insertar->bindValue(':id_colaborador', $idColaborador);
            $insertar->execute();
        }
    }

    /**
     * Responsables de la tarea en el formato que espera
     * NotificacionesColaboradores::crear. El usuario institucional lo resuelve
     * el insert de destinatarios, que ya exige acceso_institucional.
     */
    public static function destinatariosAlerta(PDO $db, $idTarea)
    {
        $sentence = $db->prepare("
            SELECT r.id_colaborador, NULL AS id_usuario
            FROM tareas_estudiantes_responsables r
            INNER JOIN colaboradores c ON c.id = r.id_colaborador AND c.activo = 1
            WHERE r.id_tarea_estudiante = :id AND r.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $idTarea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }
}
