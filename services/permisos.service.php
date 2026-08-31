<?php
/**
 * Servicio de permisos para validación en backend.
 *
 * Los permisos ya NO viajan dentro del JWT: con el catalogo completo el arreglo
 * llegaba a ~5.6 KB y el header Authorization se pasaba del limite de Apache
 * (431 Request Header Fields Too Large). Ahora se resuelven contra la BD la
 * primera vez que se necesitan dentro de la peticion y quedan en cache estatica,
 * asi que la consulta se hace una sola vez por peticion HTTP por mas veces que
 * se pregunte por un permiso.
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
     * Cache de permisos por peticion. La llave es "idUsuario|idTenant", asi que
     * un mismo proceso que atienda a dos tenants no se cruza.
     *
     * @var array
     */
    private static $cachePermisos = [];

    /**
     * Devuelve los codigos de permiso del usuario del token.
     *
     * - super_admin devuelve ['*'] sin consultar la BD.
     * - Sin contexto de tenant cargado (rutas publicas o /auth/webauthn antes
     *   de resolver el tenant) devuelve [] en lugar de reventar la peticion.
     * - Cualquier error de BD devuelve [] y queda en el log: el llamador decide
     *   que hacer, igual que cuando el token no traia el claim.
     *
     * @param object $userData Datos del usuario decodificados del JWT
     * @return array Codigos de permiso (o ['*'] para super_admin)
     */
    public static function permisosDe($userData)
    {
        if (isset($userData->super_admin) && (int) $userData->super_admin === 1) {
            return ['*'];
        }

        if (!isset($userData->id) || empty($userData->id)) {
            return [];
        }

        // TenantContext::id() corta la peticion con 500 si no hay contexto, asi
        // que se pregunta antes por la constante para poder degradar a [].
        if (!defined('TENANT_ID')) {
            return [];
        }

        $idTenant = TenantContext::id();
        $llave = $userData->id . '|' . $idTenant;

        if (isset(self::$cachePermisos[$llave])) {
            return self::$cachePermisos[$llave];
        }

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT DISTINCT pxr.codigo_permiso
                FROM permisos_x_rol pxr
                INNER JOIN roles_x_usuario rxu ON pxr.id_rol = rxu.id_rol
                WHERE rxu.id_usuario = :id_usuario
                    AND rxu.id_tenant = :id_tenant
                ORDER BY pxr.codigo_permiso
            ");
            $sentence->bindValue(':id_usuario', $userData->id);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->execute();
            $permisos = $sentence->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log('Error obteniendo permisos del usuario ' . $userData->id . ': ' . $e->getMessage());
            $permisos = [];
        }

        self::$cachePermisos[$llave] = $permisos;

        return $permisos;
    }

    /**
     * Revisa un permiso puntual sin cortar la ejecucion. Devuelve bool, a
     * diferencia de validar(), que responde 403 y termina.
     *
     * @param object $userData Datos del usuario decodificados del JWT
     * @param string $codigoPermiso Codigo a revisar
     * @return bool
     */
    public static function tiene($userData, $codigoPermiso)
    {
        $permisos = self::permisosDe($userData);

        return in_array('*', $permisos, true) || in_array($codigoPermiso, $permisos, true);
    }

    /**
     * Valida que el usuario del JWT tenga un permiso específico.
     * Si es super_admin o tiene ['*'], permite todo.
     * Si no tiene el permiso, responde 403 y detiene la ejecución.
     *
     * @param object $userData Datos del usuario decodificados del JWT (incluye ->super_admin)
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

        // Permisos resueltos contra la BD (cacheados por peticion)
        $permisos = self::permisosDe($userData);

        // Wildcard: acceso total
        if (in_array('*', $permisos)) {
            return true;
        }

        // Verificar permiso específico
        if (in_array($codigoPermiso, $permisos)) {
            return true;
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

        // Permisos resueltos contra la BD (cacheados por peticion)
        $permisos = self::permisosDe($userData);

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

        Flight::halt(403, json_encode([
            'error' => 'No tienes permiso para realizar esta acción',
            'code' => 'FORBIDDEN'
        ]));
        exit;
    }
}