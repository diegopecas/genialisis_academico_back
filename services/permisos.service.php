<?php
/**
 * Servicio de permisos para validación en backend
 * Valida permisos desde el JWT, sin consultar la BD
 */
class PermisosService
{
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