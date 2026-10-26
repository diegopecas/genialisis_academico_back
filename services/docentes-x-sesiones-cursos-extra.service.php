<?php
/**
 * Profesores que dictaron una sesion de curso extra, con sus minutos.
 *
 * Es de donde salen las horas del profesor. Las filas se guardan con la
 * sesion (al crearla, al generarla o al registrar la asistencia): la pantalla
 * manda la lista completa y sincronizar() la deja igual.
 */
class DocentesXSesionesCursosExtra
{
    public static function getBySesion($id_sesion_curso_extra)
    {
        $db = Flight::db();
        $response = self::listar($db, $id_sesion_curso_extra);
        Flight::json($response);
    }

    /**
     * Profesores de una sesion, el titular primero.
     */
    public static function listar($db, $id_sesion_curso_extra)
    {
        $sentence = $db->prepare("
            SELECT dxs.id, dxs.id_sesion_curso_extra, dxs.id_docente, dxs.es_titular, dxs.minutos,
                   TRIM(CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, ''))) AS nombre_docente
            FROM docentes_x_sesiones_cursos_extra dxs
            INNER JOIN docentes d ON dxs.id_docente = d.id
            INNER JOIN personas p ON d.id_persona = p.id
            WHERE dxs.id_sesion_curso_extra = :id_sesion AND dxs.id_tenant = :id_tenant
            ORDER BY dxs.es_titular DESC, p.primer_nombre
        ");
        $sentence->bindParam(':id_sesion', $id_sesion_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * Deja los profesores de la sesion igual a la lista que llega
     * ([{id_docente, minutos, es_titular}]). Un mismo profesor no se repite.
     *
     * @return string|null Mensaje de error, o null si todo quedo bien.
     */
    public static function sincronizar($db, $id_sesion_curso_extra, $docentes)
    {
        $vistos = array();
        foreach ($docentes as $d) {
            if (empty($d['id_docente'])) {
                return 'Cada profesor de la sesión debe estar seleccionado.';
            }
            if (isset($vistos[$d['id_docente']])) {
                return 'Un profesor está repetido en la sesión.';
            }
            if (isset($d['minutos']) && $d['minutos'] !== null && $d['minutos'] !== '' && (int) $d['minutos'] < 0) {
                return 'Los minutos del profesor no pueden ser negativos.';
            }
            $vistos[$d['id_docente']] = true;
        }

        $borrar = $db->prepare("
            DELETE FROM docentes_x_sesiones_cursos_extra
            WHERE id_sesion_curso_extra = :id_sesion AND id_tenant = :id_tenant
        ");
        $borrar->bindParam(':id_sesion', $id_sesion_curso_extra);
        $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $borrar->execute();

        $insertar = $db->prepare("
            INSERT INTO docentes_x_sesiones_cursos_extra (id, id_tenant, id_sesion_curso_extra, id_docente, es_titular, minutos)
            VALUES (:id, :id_tenant, :id_sesion, :id_docente, :es_titular, :minutos)
        ");
        $hayTitular = false;
        foreach ($docentes as $d) {
            // Si nadie viene marcado como titular, el primero lo es.
            $esTitular = !empty($d['es_titular']) ? 1 : 0;
            if ($esTitular) {
                $hayTitular = true;
            }
        }
        foreach ($docentes as $i => $d) {
            $esTitular = !empty($d['es_titular']) ? 1 : (!$hayTitular && $i === 0 ? 1 : 0);
            $minutos = isset($d['minutos']) && $d['minutos'] !== '' ? $d['minutos'] : null;
            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_sesion', $id_sesion_curso_extra);
            $insertar->bindValue(':id_docente', $d['id_docente']);
            $insertar->bindValue(':es_titular', $esTitular, PDO::PARAM_INT);
            $insertar->bindValue(':minutos', $minutos);
            $insertar->execute();
        }

        return null;
    }
}
