<?php
/**
 * Carga de un solo uso: Contrato de Cooperacion Educativa (estudiantes) - Play School (tenant 5)
 *
 * REEMPLAZA el contenido de la plantilla 'contrato_completo' (tipo 'contrato_matricula')
 * en el tenant 5 por la minuta de Cooperacion Educativa de Play School.
 * (La actual es la de Lumen.)
 *
 * El JSON va embebido aqui mismo (nowdoc), asi los {{placeholders}} quedan intactos.
 *
 * COMO EJECUTAR (terminal de VS Code):
 *   php cargar-contrato-estudiantes-tenant5.php        -> modo revision, NO toca nada
 *   php cargar-contrato-estudiantes-tenant5.php SI      -> aplica el cambio
 *   (con XAMPP: C:/xampp/php/php cargar-contrato-estudiantes-tenant5.php SI)
 *
 * Borralo cuando termines.
 */

$esCli = (php_sapi_name() === 'cli');
if (!$esCli) { header('Content-Type: text/plain; charset=utf-8'); }

// ====== CONEXION - COMPLETA USUARIO Y PASSWORD ANTES DE EJECUTAR ======
$DB_HOST = '132.148.181.209';        // servidor MySQL (mismo de Lumen)
$DB_USER = 'usr_g_fundadores_prod';          // <-- usuario de g_fundadores_prod (NO el de Lumen)
$DB_PASS = 'G8mdj!$2UqmF=XIh';         // <-- password
$DB_NAME = 'g_fundadores_prod';   // tenant 5 (Play School)
// ======================================================================

$ID_TENANT      = 5;
$CLAVE          = 'contrato_completo';
$CODIGO_TIPO    = 'contrato_matricula';

$ejecutar = $esCli
    ? in_array('SI', array_slice($argv, 1), true)
    : (isset($_GET['ejecutar']) && $_GET['ejecutar'] === 'SI');

// ---- Contenido de la plantilla (JSON literal) ----
$JSON = <<<'JSON'
{
  "titulo": "CONTRATO DE COOPERACIÓN EDUCATIVA AÑO {{anio}}",
  "introduccion": "Teniendo en cuenta que la educación es un derecho de las personas, garantizado por la Constitución Política de Colombia Artículos 26, 27, 67 y 70, la Ley 115 de 1.994, sus decretos reglamentarios. El Código de la Infancia y la Adolescencia, la Doctrina, la Jurisprudencia y las demás normas concordantes. Entre los suscritos a saber {{institucion_nombre}} y en calidad de representante legal {{representante_legal_nombre}} mayor de edad, identificada con cédula de ciudadanía N° {{representante_legal_cedula}} expedida en {{representante_legal_cedula_lugar}}, quien en adelante se denominará el {{institucion_nombre}} Y {{acudientes_nombres}} mayores de edad, identificados como aparece al pie de sus firmas, quienes actuarán en nombre propio y en calidad de representantes legales, padres/tutores y/o acudientes de la menor beneficiaria {{estudiante_nombre}} del grado {{nombre_grupo}}; se ha celebrado el presente CONTRATO DE COOPERACION EDUCATIVA, que se regirá por las siguientes cláusulas:",
  "pie_firma": "Leído el anterior documento y estando de común acuerdo con todas las cláusulas, en señal de aceptación lo firman en el Municipio de {{lugar_firma}} el {{fecha_firma_larga}}.",
  "clausulas": [
    {
      "numero": 1,
      "titulo": "DEFINICIÓN DEL CONTRATO",
      "contenido": "El presente es un Contrato de Cooperación Educativa, que obedece a las disposiciones, principalmente aquellas que establecen que el servicio educativo, independientemente del carácter privado o público de quien lo preste, es ante todo un servicio público que cumple una función social, pero que igualmente implica una responsabilidad compartida de la educación, en donde incurren obligaciones de los educadores, educandos y padres, por tanto son correlativas y esenciales, de manera que el incumplimiento de cualquiera de las obligaciones adquiridas por los contratantes, hace imposible la prestación del servicio y/o consecución del fin común."
    },
    {
      "numero": 2,
      "titulo": "NATURALEZA",
      "contenido": "De conformidad con lo anterior, el presente es un Contrato de Cooperación Educativa, de derecho privado, que se regirá por las disposiciones constitucionales y legales arriba señaladas, por las del derecho civil y comercial correspondientes y presta mérito ejecutivo."
    },
    {
      "numero": 3,
      "titulo": "OBJETO",
      "contenido": "Por consiguiente, el objeto del presente contrato es el de conseguir la recíproca complementación de esfuerzos entre los padres del estudiante beneficiario, el estudiante y la Institución: Consistente la primera es decir, la colaboración de los padres en la participación activa dentro del proceso educativo y en el pago oportuno del costo del servicio; la segunda, es decir, la colaboración del estudiante, la cual se entiende es imputable a los padres, asistiendo y cumpliendo las pautas de la promoción académica; y la tercera, es decir, la colaboración de la Institución impartiendo la educación contratada. Todo con el fin de obtener un desarrollo integral satisfactorio en el programa curricular correspondiente al nivel que se curse durante el año {{anio}}."
    },
    {
      "numero": 4,
      "titulo": "OBLIGACIONES ESENCIALES DEL CONTRATO, ININTERRUMPIBLES Y EJECUTIVAS",
      "contenido": "Por ser este un Contrato de Cooperación Educativa que tiene como propósito el cumplimiento de un fin común consistente en el desarrollo integral del estudiante, son obligaciones esenciales del mismo, que no se pueden interrumpir y que su cumplimiento presta en consecuencia mérito ejecutivo, las siguientes:\n\na. Por parte del estudiante beneficiario: Asistir y cumplir las pautas del Manual de Convivencia y Manual de Evaluación y Promoción. El incumplimiento de esta obligación es imputable a los Padres, quienes deberán vigilar y garantizar en todo tiempo el cumplimiento de esta obligación.\n\nb. Por parte de la Institución: Impartir la educación contratada, durante todo el año académico.\n\nc. Por parte de los Padres: Participar activamente en el proceso educativo del estudiante, asistir a las reuniones y citaciones que se formulen y CANCELAR OPORTUNAMENTE el costo del servicio educativo.\n\nPARÁGRAFO PRIMERO: La ausencia temporal o total dentro del mes por enfermedad u otra causa atribuible al estudiante beneficiario y/o a sus padres, así sea por fuerza mayor o caso fortuito, no dará derecho a los Padres a descontar suma alguna de lo obligado a pagar o que la Institución le haga devoluciones o abonos a meses posteriores.\n\nPARÁGRAFO SEGUNDO: Por ser el pago de la matrícula y pensión, una obligación esencial para la prestación del servicio por parte de la Institución, el incumplimiento de la misma o su retardo en más de (30) treinta días, autoriza a la Institución su recuperación por la vía ejecutiva, sin mediar comunicación o requerimiento extrajudicial alguno, así como cobro de honorarios e intereses moratorios correspondientes a la tasa máxima legal permitida por la ley a la fecha del incumplimiento o retardo."
    },
    {
      "numero": 5,
      "titulo": "LUGAR PARA EFECTUAR LOS PAGOS",
      "contenido": "El pago de las mensualidades se realizará directamente en el Banco Caja Social en la cuenta de ahorros # 24083132124 a nombre de {{institucion_nombre}}."
    },
    {
      "numero": 6,
      "titulo": "DURACIÓN",
      "contenido": "Este contrato tiene una vigencia contada a partir del {{fecha_inicio_larga}} hasta el {{fecha_fin}}."
    },
    {
      "numero": 7,
      "titulo": "VALOR Y FORMA DE PAGO",
      "contenido": "Los Padres del estudiante beneficiario deben pagar la suma de {{valor_total_formateado}}, correspondiente 50% del valor de la matrícula y el de las pensión que se cancelará en ({{numero_cuotas}}) mensualidades iguales dentro de los (5) cinco primeros días hábiles de cada mes, a partir de {{mes_inicio}} de {{anio}}. De no hacerse la cancelación de la mensualidad en los 5 primeros días de cada mes la Institución hará un incremento de $50.000 (cincuenta mil pesos) sobre cada mensualidad que se cancele después del tiempo estimado. De no hacer las cancelaciones de dicho incremento estas serán acumulativas durante el año y son causal de la no entrega del Paz y Salvo."
    },
    {
      "numero": 8,
      "titulo": "OBLIGACIONES DE LA INSTITUCIÓN",
      "contenido": "La Institución se obliga:\n\na) Impartir la educación contratada de manera regular teniendo en cuenta las disposiciones del ICBF.\n\nb) Poner a disposición del beneficiario del servicio contratado, todas las instalaciones educativas y dotaciones docentes necesarias para el logro del Objeto del contrato.\n\nc) Mantener el cuerpo docente debidamente actualizado con el fin de permitir al beneficiario obtener mayor desarrollo de sus habilidades.\n\nd) Expedir a la finalización del período lectivo, el correspondiente informe. En caso de incumplimiento en el pago estipulado en la cláusula séptima del presente contrato, la institución de conformidad con las normas vigentes en materia de costos educativos, se reserva la facultad de no expedir un Paz y Salvo, requisito este indispensable para que el beneficiario pueda renovar la matrícula para el año promocionado."
    },
    {
      "numero": 9,
      "titulo": "OBLIGACIONES DE LOS PADRES",
      "contenido": "En cumplimiento en lo dispuesto en el Artículo 67 de la Constitución Política de Colombia, los padres están obligados a:\n\na) Pagar oportunamente y en la forma prevista, el valor del presente contrato.\n\nb) Velar porque el estudiante beneficiario del servicio, asista y cumpla con las pautas establecidas por la institución.\n\nc) Responder personalmente por cualquier situación académica o disciplinaria que se oponga a los reglamentos y políticas de la institución.\n\nd) Acatar cualquier recomendación o decisión que sobre el beneficiario del servicio tomen las directivas de la Institución.\n\ne) Suministrar oportunamente al estudiante beneficiario todos los implementos de trabajo para el buen logro del Objetivo del presente contrato.\n\nf) En caso de ser citados por las directivas de la Institución asistir el día y en la hora en que se les notifique.\n\ng) Cumplir y respetar el horario escolar contratado con la Institución donde se les otorgará un tiempo de espera de 15 minutos para el inicio y la finalización de la jornada escolar. En caso de sobrepasar dicho tiempo se hará un incremento en el valor de la siguiente pensión ya que este tiempo se tomará como extracurricular.\n\nh) Cumplir con el Reglamento o Manual de Convivencia de la Institución."
    },
    {
      "numero": 10,
      "titulo": "MÉRITO EJECUTIVO",
      "contenido": "El incumplimiento de las obligaciones pecuniarias por parte de los Padres, de acuerdo con la forma prevista de pago, dará lugar al cobro de los intereses corrientes, moratorios y sanciones legales.\n\nDe igual manera el presente contrato prestará mérito ejecutivo por cuanto se trata de una obligación clara, expresa y actualmente exigible, tal como lo disponen los Artículos 488 y 491 del Código de Procedimiento Civil. La institución podrá adelantar las acciones necesarias para hacer efectivo los valores adeudados. Los gastos y honorarios que requieran la acción judicial y/o extrajudicial, correrán a cargo de los Padres."
    },
    {
      "numero": 11,
      "titulo": "CAUSALES DE TERMINACIÓN DEL CONTRATO",
      "contenido": "El presente contrato se dará por terminado por las siguientes causales:\n\na) Por expiración del término fijado.\n\nb) Por mutuo acuerdo.\n\nc) Por suspensión de las actividades de la Institución por más de (60) sesenta días o clausura del establecimiento.\n\nd) Por muerte del educando.\n\ne) Por las demás consagradas en el manual de convivencia."
    },
    {
      "numero": 12,
      "titulo": "RENOVACIÓN",
      "contenido": "La Institución condicionará la renovación del presente contrato a:\n\na) El incumplimiento de cualquiera de las obligaciones contractuales; la Institución no está obligada a renovar el contrato a los Padres que no hayan cancelado oportunamente el valor del mismo y/o que al terminar el año escolar se encuentre en mora.\n\nb) El incumplimiento de cualquiera de las normas internas de la Institución, especialmente las contempladas en el Manual de Convivencia."
    },
    {
      "numero": 13,
      "titulo": "ANEXOS",
      "contenido": "Se consideran parte constitutiva e integral del presente contrato el Proyecto Educativo Institucional; el reglamento o Manual de Convivencia y la Matrícula."
    }
  ]
}
JSON;

// Validar JSON antes de tocar la base
if (json_decode($JSON) === null) {
    echo "[ERROR] JSON invalido: " . json_last_error_msg() . "\n";
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Resolver id_tipo_plantilla por codigo (sin hardcodear)
    $st = $pdo->prepare("SELECT id FROM tipos_plantillas WHERE codigo = ?");
    $st->execute([$CODIGO_TIPO]);
    $idTipo = $st->fetchColumn();
    if (!$idTipo) { throw new Exception("No existe tipo de plantilla con codigo '$CODIGO_TIPO'"); }

    // Ver la plantilla actual que se va a reemplazar
    $st = $pdo->prepare("SELECT id, LENGTH(contenido) AS len FROM plantillas
                         WHERE id_tenant = ? AND id_tipo_plantilla = ? AND clave = ?");
    $st->execute([$ID_TENANT, $idTipo, $CLAVE]);
    $actual = $st->fetch(PDO::FETCH_ASSOC);

    echo "BD: $DB_NAME (tenant $ID_TENANT)\n";
    echo "Tipo '$CODIGO_TIPO' -> id $idTipo\n";
    if ($actual) {
        echo "Plantilla '$CLAVE' encontrada (id {$actual['id']}), contenido actual: {$actual['len']} bytes\n";
    } else {
        echo "[AVISO] No existe la plantilla '$CLAVE' para este tipo/tenant. El UPDATE no afectaria filas.\n";
    }

    if (!$ejecutar) {
        echo "\n[MODO REVISION] No se ejecuto nada.\n";
        echo "Para aplicar:  php " . basename(__FILE__) . " SI\n";
        exit(0);
    }

    $up = $pdo->prepare("UPDATE plantillas
                         SET contenido = :contenido, fecha_actualizacion = NOW()
                         WHERE id_tenant = :tenant AND id_tipo_plantilla = :tipo AND clave = :clave");
    $up->execute([
        ':contenido' => $JSON,
        ':tenant'    => $ID_TENANT,
        ':tipo'      => $idTipo,
        ':clave'     => $CLAVE,
    ]);

    echo "\n[OK] Plantilla actualizada (" . $up->rowCount() . " registro).\n";
    echo "Recuerda borrar este archivo cuando termines.\n";

} catch (Throwable $e) {
    if (!$esCli) { http_response_code(500); }
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
