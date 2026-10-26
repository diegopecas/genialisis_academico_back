<?php
/**
 * Horario fijo contratado de un espacio (cancha, piscina, salon).
 *
 * Es lo que separa las horas fijas de las adicionales en el cruce con quien
 * presta el espacio. Las filas se guardan junto con el espacio: la pantalla
 * manda la lista completa y sincronizar() la deja igual.
 */
class HorariosEspaciosLugaresCursosExtra
{
    public static function getByEspacio($id_espacio_lugar_curso_extra)
    {
        $db = Flight::db();
        $response = self::listar($db, $id_espacio_lugar_curso_extra);
        Flight::json($response);
    }

    /**
     * Filas del horario de un espacio, ordenadas por dia y hora.
     */
    public static function listar($db, $id_espacio_lugar_curso_extra)
    {
        $sentence = $db->prepare("
            SELECT he.id, he.id_espacio_lugar_curso_extra, he.id_dia_semana, he.hora_inicial, he.hora_final,
                   ds.nombre AS nombre_dia
            FROM horarios_espacios_lugares_cursos_extra he
            INNER JOIN dias_semana ds ON he.id_dia_semana = ds.id
            WHERE he.id_espacio_lugar_curso_extra = :id_espacio AND he.id_tenant = :id_tenant
            ORDER BY he.id_dia_semana, he.hora_inicial
        ");
        $sentence->bindParam(':id_espacio', $id_espacio_lugar_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * Deja el horario del espacio igual a la lista que llega. Se borra y se
     * vuelve a crear porque nada apunta a estas filas.
     *
     * @return string|null Mensaje de error, o null si todo quedo bien.
     */
    public static function sincronizar($db, $id_espacio_lugar_curso_extra, $horarios)
    {
        foreach ($horarios as $h) {
            if (empty($h['id_dia_semana']) || empty($h['hora_inicial']) || empty($h['hora_final'])) {
                return 'Cada fila del horario fijo necesita día, hora de inicio y hora de fin.';
            }
            if ($h['hora_inicial'] >= $h['hora_final']) {
                return 'En el horario fijo la hora de inicio debe ser menor que la de fin.';
            }
        }

        $borrar = $db->prepare("
            DELETE FROM horarios_espacios_lugares_cursos_extra
            WHERE id_espacio_lugar_curso_extra = :id_espacio AND id_tenant = :id_tenant
        ");
        $borrar->bindParam(':id_espacio', $id_espacio_lugar_curso_extra);
        $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $borrar->execute();

        $insertar = $db->prepare("
            INSERT INTO horarios_espacios_lugares_cursos_extra
                (id, id_tenant, id_espacio_lugar_curso_extra, id_dia_semana, hora_inicial, hora_final)
            VALUES
                (:id, :id_tenant, :id_espacio, :id_dia_semana, :hora_inicial, :hora_final)
        ");
        foreach ($horarios as $h) {
            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_espacio', $id_espacio_lugar_curso_extra);
            $insertar->bindValue(':id_dia_semana', (int) $h['id_dia_semana'], PDO::PARAM_INT);
            $insertar->bindValue(':hora_inicial', $h['hora_inicial']);
            $insertar->bindValue(':hora_final', $h['hora_final']);
            $insertar->execute();
        }

        return null;
    }
}
