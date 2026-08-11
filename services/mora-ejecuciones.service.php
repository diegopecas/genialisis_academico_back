<?php
/**
 * Bitacora de corridas del motor de mora. Sirve para vigilar el cron desde la
 * aplicacion: si el ultimo registro exitoso quedo con fecha vieja, el proceso
 * dejo de correr.
 */
class MoraEjecuciones
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $limite = Flight::request()->query['limite'];
            $limite = (!empty($limite) && (int) $limite > 0) ? (int) $limite : 60;

            $sentence = $db->prepare("
                SELECT
                    id,
                    fecha_corte,
                    fecha_inicio,
                    fecha_fin,
                    duracion_segundos,
                    origen,
                    cuentas_evaluadas,
                    cuentas_con_mora,
                    valor_total_causado,
                    estado,
                    mensaje,
                    id_usuario
                FROM mora_ejecuciones
                WHERE id_tenant = :id_tenant
                ORDER BY fecha_inicio DESC
                LIMIT :limite
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':limite', $limite, PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_ejecuciones getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener las ejecuciones de mora'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    id, fecha_corte, fecha_inicio, fecha_fin, duracion_segundos, origen,
                    cuentas_evaluadas, cuentas_con_mora, valor_total_causado, estado, mensaje, id_usuario
                FROM mora_ejecuciones
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en mora_ejecuciones getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener la ejecucion de mora'), 500);
        }
    }

    /**
     * Estado del proceso para el tablero: ultima corrida exitosa, si ya se
     * corrio hoy y cuantos dias lleva sin correr.
     */
    public static function getEstado()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT fecha_corte, fecha_inicio, fecha_fin, origen, cuentas_evaluadas,
                       cuentas_con_mora, valor_total_causado, estado, mensaje
                FROM mora_ejecuciones
                WHERE id_tenant = :id_tenant AND estado = 'OK'
                ORDER BY fecha_inicio DESC
                LIMIT 1
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $ultima = $sentence->fetch(PDO::FETCH_ASSOC);

            $hoy = date('Y-m-d');
            $diasSinCorrer = null;

            if ($ultima && !empty($ultima['fecha_corte'])) {
                $diferencia = strtotime($hoy) - strtotime($ultima['fecha_corte']);
                $diasSinCorrer = (int) floor($diferencia / 86400);
            }

            Flight::json(array(
                'ultima_ejecucion' => $ultima ? $ultima : null,
                'corrio_hoy'       => $ultima ? ($ultima['fecha_corte'] === $hoy) : false,
                'dias_sin_correr'  => $diasSinCorrer,
                'fecha_arranque'   => MotorMora::obtenerFechaArranque($db)
            ));
        } catch (Exception $e) {
            error_log('Error en mora_ejecuciones getEstado: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el estado del proceso de mora'), 500);
        }
    }

    /**
     * Abre el registro de una corrida. Uso interno del motor.
     *
     * @return string id de la ejecucion
     */
    public static function iniciar($db, $fechaCorte, $origen, $idUsuario = null)
    {
        $id = Uuid::generar();

        $sentence = $db->prepare("
            INSERT INTO mora_ejecuciones
                (id, id_tenant, fecha_corte, fecha_inicio, origen, estado, id_usuario)
            VALUES
                (:id, :id_tenant, :fecha_corte, :fecha_inicio, :origen, 'EN_PROCESO', :id_usuario)
        ");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':fecha_corte', $fechaCorte);
        $sentence->bindValue(':fecha_inicio', date('Y-m-d H:i:s'));
        $sentence->bindValue(':origen', $origen);
        $sentence->bindValue(':id_usuario', $idUsuario);
        $sentence->execute();

        return $id;
    }

    /**
     * Cierra el registro de una corrida con su resultado. Uso interno.
     */
    public static function finalizar($db, $idEjecucion, $resumen, $estado, $mensaje, $duracion)
    {
        $sentence = $db->prepare("
            UPDATE mora_ejecuciones SET
                fecha_fin = :fecha_fin,
                duracion_segundos = :duracion_segundos,
                cuentas_evaluadas = :cuentas_evaluadas,
                cuentas_con_mora = :cuentas_con_mora,
                valor_total_causado = :valor_total_causado,
                estado = :estado,
                mensaje = :mensaje
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindValue(':fecha_fin', date('Y-m-d H:i:s'));
        $sentence->bindValue(':duracion_segundos', round($duracion, 2));
        $sentence->bindValue(':cuentas_evaluadas', $resumen['cuentas_evaluadas'], PDO::PARAM_INT);
        $sentence->bindValue(':cuentas_con_mora', $resumen['cuentas_con_mora'], PDO::PARAM_INT);
        $sentence->bindValue(':valor_total_causado', $resumen['valor_total_causado']);
        $sentence->bindValue(':estado', $estado);
        $sentence->bindValue(':mensaje', $mensaje !== null ? substr($mensaje, 0, 1000) : null);
        $sentence->bindValue(':id', $idEjecucion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Indica si ya hay un corte exitoso para esa fecha. Lo usa el disparo
     * perezoso para no repetir trabajo.
     */
    public static function existeCorteExitoso($db, $fechaCorte)
    {
        $sentence = $db->prepare("
            SELECT COUNT(*) AS cantidad
            FROM mora_ejecuciones
            WHERE id_tenant = :id_tenant AND fecha_corte = :fecha_corte AND estado = 'OK'
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha_corte', $fechaCorte);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return $fila && (int) $fila['cantidad'] > 0;
    }
}
