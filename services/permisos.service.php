<?php
/**
 * Servicio de permisos para validación en backend
 * Valida permisos desde el JWT, sin consultar la BD
 */
class PermisosService
{
    /**
     * Interruptor de la validación de permisos en el backend.
     *
     * false = el backend NO bloquea por permiso. El control de acceso queda en
     *         el front (PermisosGuard sobre las rutas + filtrado del árbol de
     *         menú y de las tarjetas de cada panel).
     *
     *         Se apagó porque la granularidad del backend no coincide con la
     *         del front: una pantalla permitida consulta en sus pestañas datos
     *         de otro módulo (por ejemplo, la ficha de estudiantes consulta
     *         pagos), y el 403 de esa consulta rompía la pantalla completa
     *         aunque el usuario sí tuviera permiso para verla.
     *
     * true  = se vuelve a exigir el permiso en cada endpoint.
     *
     * Las llamadas a validar() y validarAlguno() siguen intactas en los
     * servicios, así que reactivar la validación es solo cambiar esta
     * constante a true (y revisar antes la granularidad de los códigos que
     * exige cada endpoint).
     */
    const VALIDACION_ACTIVA = false;

    /**
     * Valida que el usuario del JWT tenga un permiso específico.
     * Si es super_admin o tiene ['*'], permite todo.
     * Si no tiene el permiso, responde 403 y detiene la ejecución.
     *
     * @param object $userData Datos del usuario decodificados del JWT (incluye ->permisos y ->super_admin)
     * @param string $codigoPermiso Código del permiso a validar (ej: 'admin.productos.crear')
     */
    public static function validar($userData, $codigoPermiso)
    {
        // Validación desactivada: el control de acceso lo hace el front.
        if (!self::VALIDACION_ACTIVA) {
            return true;
        }

        // Super admin tiene acceso total
        if (isset($userData->super_admin) && $userData->super_admin == 1) {
            return true;
        }

        // Verificar permisos del JWT
        if (isset($userData->permisos)) {
            $permisos = (array) $userData->permisos;

            // Wildcard: acceso total
            if (in_array('*', $permisos)) {
                return true;
            }

            // Verificar permiso específico
            if (in_array($codigoPermiso, $permisos)) {
                return true;
            }
        }

        // No tiene permiso
        Flight::halt(403, json_encode([
            'error' => 'No tienes permiso para realizar esta acción',
            'code' => 'FORBIDDEN',
            'permiso_requerido' => $codigoPermiso
        ]));
        exit;
    }

    /**
     * Valida que el usuario del JWT tenga al menos uno de los permisos indicados.
     *
     * @param object $userData Datos del usuario decodificados del JWT
     * @param array $codigosPermisos Array de códigos de permisos
     */
    public static function validarAlguno($userData, $codigosPermisos)
    {
        // Validación desactivada: el control de acceso lo hace el front.
        if (!self::VALIDACION_ACTIVA) {
            return true;
        }

        // Super admin tiene acceso total
        if (isset($userData->super_admin) && $userData->super_admin == 1) {
            return true;
        }

        // Verificar permisos del JWT
        if (isset($userData->permisos)) {
            $permisos = (array) $userData->permisos;

            // Wildcard: acceso total
            if (in_array('*', $permisos)) {
                return true;
            }

            // Verificar si tiene al menos uno
            foreach ($codigosPermisos as $codigo) {
                if (in_array($codigo, $permisos)) {
                    return true;
                }
            }
        }

        Flight::halt(403, json_encode([
            'error' => 'No tienes permiso para realizar esta acción',
            'code' => 'FORBIDDEN'
        ]));
        exit;
    }
}