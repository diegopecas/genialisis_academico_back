<?php
/**
 * Reglas de reserva de un curso extra (uno a uno con cursos_extra).
 *
 * Si el curso no tiene fila se comporta como fijo, que es como funcionaban
 * todos los cursos antes de las sesiones: los inscritos quedan reservados en
 * todas las sesiones y no pueden cancelar ni reagendar. Por eso los cursos
 * que ya existian no necesitan datos: la fila nace cuando alguien guarda la
 * configuracion.
 */
class ConfiguracionReservasCursosExtra
{
    // Valores de un curso sin fila: fijo.
    const POR_DEFECTO = array(
        'reserva_automatica' => 1,
        'permite_cancelar' => 0,
        'permite_reagendar' => 0,
        'permite_reserva_suelta' => 0,
        'descuenta_inasistencia' => 1,
        'horas_cancelacion_tardia' => null
    );

    /**
     * Reglas del curso. Siempre devuelve una fila: si el curso no la tiene,
     * van los valores por defecto con id nulo y configurado = 0.
     */
    public static function getByCurso($id_curso_extra)
    {
        $db = Flight::db();
        $response = self::obtener($db, $id_curso_extra);
        Flight::json(array($response));
    }

    /**
     * Crea o actualiza las reglas del curso.
     */
    public static function guardar()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id_curso_extra = $data['id_curso_extra'];
        $reglas = array();
        foreach (self::POR_DEFECTO as $campo => $valor) {
            if ($campo === 'horas_cancelacion_tardia') {
                $reglas[$campo] = isset($data[$campo]) && $data[$campo] !== '' ? (int) $data[$campo] : null;
            } else {
                $reglas[$campo] = isset($data[$campo]) ? ($data[$campo] ? 1 : 0) : $valor;
            }
        }

        $actual = self::obtener($db, $id_curso_extra);

        if ($actual['id']) {
            $sentence = $db->prepare("
                UPDATE configuracion_reservas_cursos_extra SET
                    reserva_automatica = :reserva_automatica,
                    permite_cancelar = :permite_cancelar,
                    permite_reagendar = :permite_reagendar,
                    permite_reserva_suelta = :permite_reserva_suelta,
                    descuenta_inasistencia = :descuenta_inasistencia,
                    horas_cancelacion_tardia = :horas_cancelacion_tardia
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id', $actual['id']);
        } else {
            $sentence = $db->prepare("
                INSERT INTO configuracion_reservas_cursos_extra
                    (id, id_tenant, id_curso_extra, reserva_automatica, permite_cancelar, permite_reagendar,
                     permite_reserva_suelta, descuenta_inasistencia, horas_cancelacion_tardia)
                VALUES
                    (:id, :id_tenant, :id_curso_extra, :reserva_automatica, :permite_cancelar, :permite_reagendar,
                     :permite_reserva_suelta, :descuenta_inasistencia, :horas_cancelacion_tardia)
            ");
            $sentence->bindValue(':id', Uuid::generar());
            $sentence->bindValue(':id_curso_extra', $id_curso_extra);
        }
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':reserva_automatica', $reglas['reserva_automatica'], PDO::PARAM_INT);
        $sentence->bindValue(':permite_cancelar', $reglas['permite_cancelar'], PDO::PARAM_INT);
        $sentence->bindValue(':permite_reagendar', $reglas['permite_reagendar'], PDO::PARAM_INT);
        $sentence->bindValue(':permite_reserva_suelta', $reglas['permite_reserva_suelta'], PDO::PARAM_INT);
        $sentence->bindValue(':descuenta_inasistencia', $reglas['descuenta_inasistencia'], PDO::PARAM_INT);
        $sentence->bindValue(':horas_cancelacion_tardia', $reglas['horas_cancelacion_tardia']);
        $sentence->execute();

        self::getByCurso($id_curso_extra);
    }

    /**
     * Reglas del curso para usar desde otros servicios. Devuelve un arreglo
     * con todas las reglas, el id (nulo si no hay fila) y configurado (0/1).
     */
    public static function obtener($db, $id_curso_extra)
    {
        $sentence = $db->prepare("
            SELECT id, id_curso_extra, reserva_automatica, permite_cancelar, permite_reagendar,
                   permite_reserva_suelta, descuenta_inasistencia, horas_cancelacion_tardia
            FROM configuracion_reservas_cursos_extra
            WHERE id_curso_extra = :id_curso_extra AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_curso_extra', $id_curso_extra);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila) {
            $fila['configurado'] = 1;
            return $fila;
        }

        return array_merge(
            array('id' => null, 'id_curso_extra' => $id_curso_extra, 'configurado' => 0),
            self::POR_DEFECTO
        );
    }
}
