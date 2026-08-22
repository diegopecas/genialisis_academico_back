<?php
/*=============================================
SERVICIO - JORNADA LABORAL
Archivo: services/jornada-laboral.service.php

Horario de atencion del jardin por dia de la semana. Es lo que acota las
horas que se pueden pedir en una solicitud, y lo que usa el motor de
cobros como horario por defecto cuando el estudiante no tiene uno propio.

dias_semana sigue siendo el catalogo global de los nombres de los dias y
sus horas quedan como respaldo: si un tenant no tiene su jornada
configurada, se cae a las de alli.
=============================================*/

class JornadaLaboral
{
    /**
     * Los siete dias con la jornada del tenant. Siempre devuelve los siete,
     * aunque al tenant le falte alguno: en ese caso trae las horas de
     * dias_semana, para que la pantalla no muestre huecos.
     */
    public static function getAll()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $sentence = $db->prepare("SELECT ds.id AS id_dia_semana,
                                         ds.nombre AS dia_nombre,
                                         jl.id,
                                         COALESCE(jl.hora_entrada, ds.hora_entrada) AS hora_entrada,
                                         COALESCE(jl.hora_salida, ds.hora_salida)   AS hora_salida,
                                         COALESCE(jl.atiende, 1) AS atiende,
                                         CASE WHEN jl.id IS NULL THEN 0 ELSE 1 END AS configurado
                                  FROM dias_semana ds
                                  LEFT JOIN jornada_laboral jl
                                         ON jl.id_dia_semana = ds.id
                                        AND jl.id_tenant = :id_tenant
                                  ORDER BY ds.id");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT jl.id, jl.id_dia_semana, jl.hora_entrada, jl.hora_salida, jl.atiende,
                                         ds.nombre AS dia_nombre
                                  FROM jornada_laboral jl
                                  INNER JOIN dias_semana ds ON ds.id = jl.id_dia_semana
                                  WHERE jl.id = :id AND jl.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function new()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $datos = self::leerDatos();

        if ($datos['error']) {
            Flight::json(array('error' => $datos['error']), 400);
            return;
        }

        $sentence = $db->prepare("SELECT COUNT(*) AS repetidos FROM jornada_laboral
                                  WHERE id_tenant = :id_tenant AND id_dia_semana = :id_dia_semana");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_dia_semana', $datos['id_dia_semana'], PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        if ($fila && $fila['repetidos'] > 0) {
            Flight::json(array('error' => 'Ese dia ya tiene jornada configurada'), 400);
            return;
        }

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO jornada_laboral
            (id, id_tenant, id_dia_semana, hora_entrada, hora_salida, atiende)
            VALUES (:id, :id_tenant, :id_dia_semana, :hora_entrada, :hora_salida, :atiende)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_dia_semana', $datos['id_dia_semana'], PDO::PARAM_INT);
        $sentence->bindValue(':hora_entrada', $datos['hora_entrada']);
        $sentence->bindValue(':hora_salida', $datos['hora_salida']);
        $sentence->bindValue(':atiende', $datos['atiende'], PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
        $datos = self::leerDatos();

        if ($datos['error']) {
            Flight::json(array('error' => $datos['error']), 400);
            return;
        }

        // La pantalla edita los siete dias de corrido, y algunos pueden no
        // existir todavia en el tenant. Si no viene id, se crea.
        if (empty($id)) {
            self::new();
            return;
        }

        $sentence = $db->prepare("UPDATE jornada_laboral SET
                hora_entrada = :hora_entrada,
                hora_salida = :hora_salida,
                atiende = :atiende
            WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':hora_entrada', $datos['hora_entrada']);
        $sentence->bindValue(':hora_salida', $datos['hora_salida']);
        $sentence->bindValue(':atiende', $datos['atiende'], PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    /**
     * Borra la jornada de un dia. El dia no desaparece: vuelve a tomar las
     * horas de dias_semana, que son el respaldo.
     */
    public static function delete()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM jornada_laboral WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    /**
     * Jornada del tenant indexada por dia (1 lunes a 7 domingo). Lectura
     * interna, sin responder al cliente.
     *
     * Cae a dias_semana cuando el tenant no tiene el dia configurado, para
     * no dejar sin horario a un jardin que aun no la ha parametrizado.
     *
     * @param  PDO   $db
     * @return array Filas con nombre, hora_entrada, hora_salida y atiende
     */
    public static function obtenerPorDia(PDO $db)
    {
        $sentence = $db->prepare("SELECT ds.id,
                                         ds.nombre,
                                         COALESCE(jl.hora_entrada, ds.hora_entrada) AS hora_entrada,
                                         COALESCE(jl.hora_salida, ds.hora_salida)   AS hora_salida,
                                         COALESCE(jl.atiende, 1) AS atiende
                                  FROM dias_semana ds
                                  LEFT JOIN jornada_laboral jl
                                         ON jl.id_dia_semana = ds.id
                                        AND jl.id_tenant = :id_tenant
                                  ORDER BY ds.id");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $jornadas = array();
        foreach ($sentence->fetchAll() as $fila) {
            $jornadas[(int)$fila['id']] = $fila;
        }

        return $jornadas;
    }

    /**
     * Primer dia en el que todavia se puede pedir algo, con la fecha y la
     * hora del servidor.
     *
     * Normalmente es HOY, con las horas que faltan de la jornada. Pero si ya
     * se acabo el dia, si el jardin no atiende hoy o si hoy no es habil, rueda
     * al siguiente dia habil y devuelve su jornada completa: a las nueve de la
     * noche lo natural es programar para manana, no quedarse sin opciones.
     *
     * Existe aparte de getAll para no cambiar lo que ese metodo devuelve.
     *
     * La fecha y la hora salen del servidor a proposito: si se tomaran del
     * navegador, un celular con la zona horaria corrida pediria un
     * medicamento para el dia equivocado. Aca PHP corre en America/Bogota.
     */
    public static function getVigente()
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $hoy  = date('Y-m-d');
        $hora = date('H:i');

        $jornadas = self::obtenerPorDia($db);

        $fecha = $hoy;
        $jornada = null;
        $horas = array();

        // Se miran hasta 14 dias hacia adelante. Si en dos semanas no hay un
        // dia habil con jornada, algo esta mal configurado y es mejor
        // devolver el dia de hoy sin horas que seguir buscando.
        for ($i = 0; $i < 14; $i++) {
            $fecha = date('Y-m-d', strtotime($hoy . ' +' . $i . ' day'));
            $idDia = (int)date('N', strtotime($fecha));
            $jornada = isset($jornadas[$idDia]) ? $jornadas[$idDia] : null;
            $horas = array();

            if (!$jornada || (int)$jornada['atiende'] !== 1) {
                continue;
            }

            if (!self::esDiaHabil($db, $fecha)) {
                continue;
            }

            $entrada = self::aMinutos($jornada['hora_entrada']);
            $salida  = self::aMinutos($jornada['hora_salida']);

            // El corte por hora solo aplica hoy: manana la jornada esta
            // completa.
            $corte = ($i === 0) ? self::aMinutos($hora) : -1;

            for ($m = $entrada; $m <= $salida; $m += 15) {
                if ($m <= $corte) {
                    continue;
                }

                $horas[] = sprintf('%02d:%02d', floor($m / 60), $m % 60);
            }

            if (count($horas) > 0) {
                break;
            }
        }

        Flight::json(array(
            'fecha_hoy'     => $hoy,
            'hora_actual'   => $hora,
            'fecha_actual'  => $fecha,
            'es_hoy'        => $fecha === $hoy ? 1 : 0,
            'id_dia_semana' => (int)date('N', strtotime($fecha)),
            'dia_nombre'    => $jornada ? $jornada['nombre'] : null,
            'atiende'       => $jornada ? (int)$jornada['atiende'] : 0,
            'hora_entrada'  => $jornada ? substr($jornada['hora_entrada'], 0, 5) : null,
            'hora_salida'   => $jornada ? substr($jornada['hora_salida'], 0, 5) : null,
            'horas'         => $horas
        ));
    }

    /**
     * Jornada y horas disponibles de una fecha puntual.
     *
     * La usa el formulario cuando el acudiente mueve la fecha. Solo se
     * permite de hoy en adelante: pedir algo para ayer no tiene sentido y
     * ademas ya no se puede cumplir.
     *
     * El corte por hora aplica unicamente cuando la fecha es hoy; en un dia
     * futuro la jornada esta completa.
     *
     * @param string $fecha AAAA-MM-DD
     */
    public static function getHorasFecha($fecha)
    {
        JWTService::requerirAutenticacion();
        $db = Flight::db();

        $hoy  = date('Y-m-d');
        $hora = date('H:i');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha)) {
            Flight::json(array('error' => 'La fecha no es valida'), 400);
            return;
        }

        if ($fecha < $hoy) {
            Flight::json(array('error' => 'No se pueden pedir solicitudes para una fecha pasada'), 400);
            return;
        }

        $idDia = (int)date('N', strtotime($fecha));
        $jornadas = self::obtenerPorDia($db);
        $jornada = isset($jornadas[$idDia]) ? $jornadas[$idDia] : null;

        $habil = self::esDiaHabil($db, $fecha);
        $horas = array();

        if ($jornada && (int)$jornada['atiende'] === 1 && $habil) {
            $entrada = self::aMinutos($jornada['hora_entrada']);
            $salida  = self::aMinutos($jornada['hora_salida']);
            $corte   = ($fecha === $hoy) ? self::aMinutos($hora) : -1;

            for ($m = $entrada; $m <= $salida; $m += 15) {
                if ($m <= $corte) {
                    continue;
                }

                $horas[] = sprintf('%02d:%02d', floor($m / 60), $m % 60);
            }
        }

        Flight::json(array(
            'fecha_hoy'     => $hoy,
            'hora_actual'   => $hora,
            'fecha_actual'  => $fecha,
            'es_hoy'        => $fecha === $hoy ? 1 : 0,
            'id_dia_semana' => $idDia,
            'dia_nombre'    => $jornada ? $jornada['nombre'] : null,
            'atiende'       => $jornada ? (int)$jornada['atiende'] : 0,
            'dia_habil'     => $habil ? 1 : 0,
            'hora_entrada'  => $jornada ? substr($jornada['hora_entrada'], 0, 5) : null,
            'hora_salida'   => $jornada ? substr($jornada['hora_salida'], 0, 5) : null,
            'horas'         => $horas
        ));
    }

    /**
     * Si la fecha es dia habil segun el calendario del jardin.
     *
     * Cuando el calendario no tiene cargada esa fecha se asume habil: es
     * preferible ofrecer el dia a dejar al acudiente sin poder pedir nada
     * por un calendario incompleto.
     */
    private static function esDiaHabil(PDO $db, $fecha)
    {
        $sentence = $db->prepare("SELECT dia_habil FROM calendarios WHERE fecha = :fecha LIMIT 1");
        $sentence->bindParam(':fecha', $fecha);
        $sentence->execute();
        $fila = $sentence->fetch();

        if (!$fila) {
            return true;
        }

        return (int)$fila['dia_habil'] === 1;
    }

    /**
     * Minutos desde medianoche. Se usa para comparar horas sin pelear con
     * formatos.
     */
    private static function aMinutos($hora)
    {
        $partes = explode(':', substr((string)$hora, 0, 5));
        return (int)$partes[0] * 60 + (int)$partes[1];
    }

    /**
     * Lee y valida el cuerpo de la peticion.
     */
    private static function leerDatos()
    {
        $data = Flight::request()->data;

        $valores = array(
            'error'         => null,
            'id_dia_semana' => isset($data['id_dia_semana']) ? (int)$data['id_dia_semana'] : null,
            'hora_entrada'  => isset($data['hora_entrada']) ? self::normalizarHora($data['hora_entrada']) : null,
            'hora_salida'   => isset($data['hora_salida']) ? self::normalizarHora($data['hora_salida']) : null,
            'atiende'       => isset($data['atiende']) ? (int)$data['atiende'] : 1
        );

        if (empty($valores['id_dia_semana']) || $valores['id_dia_semana'] < 1 || $valores['id_dia_semana'] > 7) {
            $valores['error'] = 'El dia de la semana no es valido';
            return $valores;
        }

        if ($valores['hora_entrada'] === null || $valores['hora_salida'] === null) {
            $valores['error'] = 'La hora de entrada y la de salida son obligatorias';
            return $valores;
        }

        if ($valores['hora_salida'] <= $valores['hora_entrada']) {
            $valores['error'] = 'La hora de salida debe ser posterior a la de entrada';
            return $valores;
        }

        return $valores;
    }

    /**
     * Deja la hora en HH:MM:SS. Devuelve null si el formato no sirve.
     */
    private static function normalizarHora($hora)
    {
        $hora = trim((string)$hora);

        if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        if (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $hora)) {
            return null;
        }

        $partes = explode(':', $hora);

        if ((int)$partes[0] > 23 || (int)$partes[1] > 59 || (int)$partes[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', (int)$partes[0], (int)$partes[1], (int)$partes[2]);
    }
}
