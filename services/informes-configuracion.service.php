<?php
class InformesConfiguracion
{
    /**
     * Configuración general del informe del jardín. Es una sola fila por
     * tenant, así que se devuelve el registro existente o un arreglo vacío
     * para que el front pinte el formulario sin casos especiales.
     */
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT c.id, c.id_parametro_evaluacion, c.muestra_ausencias, c.titulo_informe,
                   c.encabezado, c.pie_pagina, c.firma_uno, c.firma_dos, c.firma_acudiente,
                   p.nombre AS nombre_parametro_evaluacion
            FROM informes_configuracion c
            LEFT JOIN parametros_calificaciones p ON c.id_parametro_evaluacion = p.id
            WHERE c.id_tenant = :id_tenant
            LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT c.id, c.id_parametro_evaluacion, c.muestra_ausencias, c.titulo_informe,
                   c.encabezado, c.pie_pagina, c.firma_uno, c.firma_dos, c.firma_acudiente,
                   p.nombre AS nombre_parametro_evaluacion
            FROM informes_configuracion c
            LEFT JOIN parametros_calificaciones p ON c.id_parametro_evaluacion = p.id
            WHERE c.id = :id AND c.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $id_parametro_evaluacion = Flight::request()->data['id_parametro_evaluacion'] ?? null;
        $muestra_ausencias = Flight::request()->data['muestra_ausencias'] ?? 0;
        $titulo_informe = Flight::request()->data['titulo_informe'] ?? null;
        $encabezado = Flight::request()->data['encabezado'] ?? null;
        $pie_pagina = Flight::request()->data['pie_pagina'] ?? null;
        $firma_uno = Flight::request()->data['firma_uno'] ?? null;
        $firma_dos = Flight::request()->data['firma_dos'] ?? null;
        $firma_acudiente = Flight::request()->data['firma_acudiente'] ?? 0;

        $sentence = $db->prepare("INSERT INTO informes_configuracion(
                id, id_tenant, id_parametro_evaluacion, muestra_ausencias, titulo_informe,
                encabezado, pie_pagina, firma_uno, firma_dos, firma_acudiente
            ) VALUES (
                :id, :id_tenant, :id_parametro_evaluacion, :muestra_ausencias, :titulo_informe,
                :encabezado, :pie_pagina, :firma_uno, :firma_dos, :firma_acudiente
            )");

        $idNew = Uuid::generar();
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_parametro_evaluacion', $id_parametro_evaluacion);
        $sentence->bindValue(':muestra_ausencias', $muestra_ausencias, PDO::PARAM_INT);
        $sentence->bindParam(':titulo_informe', $titulo_informe);
        $sentence->bindParam(':encabezado', $encabezado);
        $sentence->bindParam(':pie_pagina', $pie_pagina);
        $sentence->bindParam(':firma_uno', $firma_uno);
        $sentence->bindParam(':firma_dos', $firma_dos);
        $sentence->bindValue(':firma_acudiente', $firma_acudiente, PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $idNew));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_parametro_evaluacion = Flight::request()->data['id_parametro_evaluacion'] ?? null;
        $muestra_ausencias = Flight::request()->data['muestra_ausencias'] ?? 0;
        $titulo_informe = Flight::request()->data['titulo_informe'] ?? null;
        $encabezado = Flight::request()->data['encabezado'] ?? null;
        $pie_pagina = Flight::request()->data['pie_pagina'] ?? null;
        $firma_uno = Flight::request()->data['firma_uno'] ?? null;
        $firma_dos = Flight::request()->data['firma_dos'] ?? null;
        $firma_acudiente = Flight::request()->data['firma_acudiente'] ?? 0;

        $sentence = $db->prepare("UPDATE informes_configuracion SET
                id_parametro_evaluacion = :id_parametro_evaluacion,
                muestra_ausencias = :muestra_ausencias,
                titulo_informe = :titulo_informe,
                encabezado = :encabezado,
                pie_pagina = :pie_pagina,
                firma_uno = :firma_uno,
                firma_dos = :firma_dos,
                firma_acudiente = :firma_acudiente
            WHERE id = :id AND id_tenant = :id_tenant");

        $sentence->bindValue(':id_parametro_evaluacion', $id_parametro_evaluacion);
        $sentence->bindValue(':muestra_ausencias', $muestra_ausencias, PDO::PARAM_INT);
        $sentence->bindParam(':titulo_informe', $titulo_informe);
        $sentence->bindParam(':encabezado', $encabezado);
        $sentence->bindParam(':pie_pagina', $pie_pagina);
        $sentence->bindParam(':firma_uno', $firma_uno);
        $sentence->bindParam(':firma_dos', $firma_dos);
        $sentence->bindValue(':firma_acudiente', $firma_acudiente, PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM informes_configuracion WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
}
