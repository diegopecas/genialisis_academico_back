<?php
/**
 * Carga de un solo uso: Contrato laboral DOCENTE - Play School (tenant 5)
 *
 * Que hace (dentro de una transaccion):
 *   1) Borra del tenant 5 las clausulas heredadas de Lumen (contratos_clausulas).
 *   2) Crea el shell de plantilla laboral (clave 'docente_ano_escolar').
 *   3) Inserta las 11 clausulas de la minuta, atadas al cargo Docente.
 *   4) Mapea (Docente + NOMINA_FIJO) -> plantilla.
 *
 * Usa sentencias PREPARADAS: los {{placeholders}} entran como valor literal.
 *
 * COMO EJECUTAR (terminal de VS Code):
 *   php cargar-contrato-docente-tenant5.php        -> modo revision, NO toca nada
 *   php cargar-contrato-docente-tenant5.php SI     -> aplica los cambios
 *   (con XAMPP: C:/xampp/php/php cargar-contrato-docente-tenant5.php SI)
 *
 * Idempotente: puedes correrlo de nuevo; limpia lo suyo antes de reinsertar.
 * Borralo cuando termines.
 */

$esCli = (php_sapi_name() === 'cli');
if (!$esCli) { header('Content-Type: text/plain; charset=utf-8'); }

// ====== 1) CONEXION - COMPLETA USUARIO Y PASSWORD ANTES DE EJECUTAR ======
$DB_HOST = '132.148.181.209';        // servidor MySQL (mismo de Lumen)
$DB_USER = 'usr_g_fundadores_prod';          // <-- usuario de g_fundadores_prod (NO el de Lumen)
$DB_PASS = 'G8mdj!$2UqmF=XIh';         // <-- password
$DB_NAME = 'g_fundadores_prod';   // tenant 5 (Play School)
// ========================================================================

$ID_TENANT        = 5;
$ID_CARGO         = 'bcd163c4-7a25-11f1-81ec-fa163e0aa7da';   // Docente
$ID_TIPO_CONTRATO = 'ef2575da-7a25-11f1-81ec-fa163e0aa7da';   // NOMINA_FIJO
$CLAVE_PLANTILLA  = 'docente_ano_escolar';

// 'SI' para ejecutar: por terminal como argumento, o por web con ?ejecutar=SI
$ejecutar = $esCli
    ? in_array('SI', array_slice($argv, 1), true)
    : (isset($_GET['ejecutar']) && $_GET['ejecutar'] === 'SI');

function uuid4() {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

// Shell de la plantilla (titulo / introduccion / pie_firma)
$SHELL_JSON = '{"titulo":"CONTRATO INDIVIDUAL DE TRABAJO – DURACIÓN DEL AÑO ESCOLAR {{anio}}","introduccion":"Entre los suscritos {{institucion_nombre}} representado legalmente por {{representante_legal_nombre}} identificada con cédula de ciudadanía No {{representante_legal_cedula}} de {{representante_legal_cedula_lugar}} quien para los efectos legales se denomina EL EMPLEADOR, por una parte y por la otra {{colaborador_nombre}} mayor de edad identificada con cédula de ciudadanía No {{colaborador_documento}}, actuando en nombre propio quien en adelante se denomina EMPLEADO, se compromete a celebrar el presente CONTRATO INDIVIDUAL DE TRABAJO – DURACIÓN DEL AÑO ESCOLAR, como DOCENTE del {{institucion_nombre}}, contrato que se regirá por las siguientes cláusulas:","pie_firma":"El presente contrato se entiende perfeccionado para su ejecución con la firma de las partes. Las partes, una vez leído el presente contrato, firman como aparece en la ciudad de {{lugar_firma}} en dos (2) ejemplares, uno con destino a cada parte, cada uno de ellos del mismo tenor. En constancia, se firma en {{lugar_firma}} el {{fecha_firma_larga}}."}';

// Las 11 clausulas: [tipo, numero, titulo, contenido, orden]
$CLAUSULAS = [
    ['objeto', 1, 'OBJETO CONTRACTUAL', 'EL EMPLEADO en calidad de DOCENTE, en las áreas de estudio y para el nivel o grado que le sean asignados por la Institución, y en pleno conocimiento de las actividades a desarrollar en cumplimiento de sus funciones, se obliga para con el EMPLEADOR a realizar los trabajos y todas las actividades inherentes con el servicio contratado, liderando el proceso enseñanza aprendizaje de los niños y niñas del Centro Educativo, buscando su desarrollo integral, propiciando la autonomía, el liderazgo, el cuidado propio y del otro, despertando su capacidad de asombro que los lleve a ser libres y felices, el que deberá ejecutar de acuerdo a las condiciones y cláusulas del presente contrato.', 10],
    ['duracion', 2, 'DURACIÓN', 'El término de ejecución del presente contrato inicia el {{fecha_inicio_larga}} y finaliza el {{fecha_fin_larga}}.

PARÁGRAFO: Si con antelación no inferior a treinta (30) días de la fecha de vencimiento de este término ninguna de las partes avisare por escrito a la otra su determinación de no prorrogar el contrato, este se entenderá prorrogado por un período igual al inicialmente pactado.', 20],
    ['horario_lugar', 3, 'HORARIO Y LUGAR DE TRABAJO DEL EMPLEADO', 'EL EMPLEADO queda obligado a realizar la jornada de trabajo de lunes a viernes desde las 7:00 AM hasta las 3:00 PM, y eventualmente los SÁBADOS que se requieran (Fechas que serán comunicadas previamente por parte del EMPLEADOR), en el {{institucion_nombre}} ubicado en la Carrera 4 No 6ª – 01 Interior 1 en el Municipio de Chía.

PARÁGRAFO: Aunque el lugar de trabajo es el indicado en este Contrato, las partes pueden acordar que el mismo se preste en un lugar diferente, siempre y cuando las condiciones laborales del trabajador no se vean desmejoradas o se disminuya su remuneración o le cause perjuicio. Correrán por cuenta del empleador los gastos que ocasione dicho traslado.', 30],
    ['remuneracion', 4, 'REMUNERACIÓN', 'EL EMPLEADO recibirá como contraprestación a sus servicios un salario de {{salario_mensual_letras}} (${{salario_mensual_numero}}) y un auxilio de transporte por un valor de DOSCIENTOS CUARENTA Y NUEVE MIL NOVENTA Y CINCO PESOS M/C (249.095. oo) que serán pagaderos los cinco (5) primeros días hábiles de cada mes, iniciando a partir del mes Marzo. Dicha suma será consignada de manera mensual dentro del plazo estipulado, en la cuenta bancaria suministrada por el EMPLEADO y/o entregada personalmente al EMPLEADOR.

PARÁGRAFO: Queda establecido que en dicho pago se halla la remuneración correspondiente a los descansos dominicales y festivos de que tratan los artículos 172 y 178 del Código Sustantivo del Trabajo y la Ley 789 del 2002.', 40],
    ['obligacion', 5, 'OBLIGACIONES DEL EMPLEADO', 'Son obligaciones del EMPLEADO las siguientes:

1. Realizar personalmente la labor, en los términos estipulados; observar los preceptos del reglamento, acatar y cumplir las órdenes e instrucciones que de modo particular la impartan el empleador o sus representantes, según el orden jerárquico establecido.

2. No comunicar con terceros, salvo la autorización expresa, las informaciones que tenga sobre su trabajo, especialmente sobre las cosas que sean de naturaleza reservada o cuya divulgación pueda ocasionar perjuicios al empleador, lo que no obsta para denunciar delitos comunes o violaciones del contrato o de las normas legales del trabajo ante las autoridades competentes.

3. Conservar y restituir en buen estado, salvo el deterioro natural, los instrumentos y útiles que le hayan sido facilitados y las materias primas sobrantes.

4. Guardar rigurosamente la moral en las relaciones con sus superiores y compañeros.

5. Comunicar oportunamente al empleador las observaciones que estime conducentes a evitarle daños y perjuicios.

6. Prestar la colaboración posible en casos de siniestro o de riesgo inminente que afecten o amenacen las personas o cosas de la empresa o establecimiento.

7. Observar con suma diligencia y cuidado las instrucciones y órdenes preventivas de accidentes o de enfermedades profesionales.

8. Para obtener permiso para ausentarse de su trabajo, el EMPLEADO deberá solicitarlo por escrito con antelación de tres (3) días adjuntando el respectivo soporte o justificación. De no existir justificación escrita el EMPLEADOR descontará del salario el día o días de ausencia.

9. Efectuar las modificaciones o correcciones que el EMPLEADOR le requiera.

10. Atender las necesidades del niño(a) buscando su tranquilidad en el proceso de adaptación a la Institución, apoyándose en los demás miembros del equipo.

11. Guiar el proceso de construcción de conocimientos dentro del Aula, desarrollando las competencias que se deriven de él en la aplicación del mismo.

12. Desarrollar diferentes actividades lúdico-pedagógicas que sean llamativas y que tengan como finalidad que el niño(a) aprenda algún contenido o destreza particular.

13. Asistir y orientar al estudiante en sus dudas personales y académicas, dentro y fuera del Aula.

14. Desarrollar en los educandos hábitos fundamentales de orden, disciplina y trabajo, inculcándoles sentido de responsabilidad.

15. Evaluar integralmente los avances y desafíos del proceso formativo del educando.

16. Vigilar y controlar en el desarrollo de las actividades diarias, la integridad física del estudiante, tomando las medidas necesarias en caso de accidente o percance.

17. Organizar, asistir, atender y poner en desarrollo los eventos específicos programados por la Institución.

18. Afianzar La Educación Integral del educando en valores tales como: Respeto, Honestidad, Responsabilidad y buen uso y cuidado de los elementos que se le sean encargados para el cumplimiento de su función.

19. Lograr un aprendizaje eficaz por medio de actividades que con lleven a un mejor aprovechamiento del tiempo y trabajo integrado.

20. Apoyar a la Institución en el fomento de la convivencia armónica a través de las diferentes actividades escolares.

21. Adelantar y desarrollar sincrónicamente las actividades principales, secundarias y complementarias que se derivan del proceso de aprendizaje y educación integral descritos en el Proyecto Educativo Institucional PEI.

22. Llenar los controles y registros exigidos por la Institución Educativa.

23. Participar activamente en las reuniones, talleres de padres de familia, consejos, comités y comisiones programadas por la Institución.

24. Llevar registro diario de asistencia por la asignatura correspondiente.

25. Llevar el registro de evaluación por temas, unidades y períodos.

26. Utilizar en forma adecuada y con los cuidados del caso, el aula de clase, el material didáctico, audiovisual, informático y pedagógico que proporciona la Institución para llevar a cabo el proceso enseñanza aprendizaje.

27. Suministrar los documentos que acrediten su formación académica y experiencia profesional.

28. Mantener una amplia comunicación con los coordinadores y las diferentes autoridades jerárquicas de la Institución, informando oportunamente sobre situaciones anómalas o por corregir, procurando una continua retroalimentación de la información necesaria para llevar a cabo, eficaz y eficientemente la misión, objetivos y directrices establecidos por la Institución.

29. Atender y participar activamente en las jornadas de capacitación a las cuales la Institución le invite con el fin de actualizar y mejorar los conocimientos pedagógicos y de desarrollo humano.

30. Diligenciar semanalmente y completamente el observador del alumno registrando conductas positivas y por mejorar.

31. Establecer el adecuado canal de comunicación con los demás estamentos de la Institución dentro de un marco de respeto, tolerancia, cordialidad y solidaridad.

32. Fomentar el espíritu de grupo en el espacio de convivencia cotidiana.

33. Desempeñar las demás funciones y actividades inherentes al ejercicio de su cargo y las que reciba por delegación expresa a través de las diferentes autoridades jerárquicas de la Institución.

34. Administrar la progresión de los aprendizajes.

35. Trabajar en equipo.

36. Enfrentar los deberes y los dilemas éticos de la profesión de educador.

37. Administrar su propia formación continua.', 50],
    ['obligacion_empleador', 6, 'OBLIGACIONES DEL EMPLEADOR', 'Son obligaciones del EMPLEADOR las siguientes:

1. Pagar como contraprestación a los servicios prestados por el EMPLEADO el salario estipulado en la fecha y lugar acordado.

2. Facilitar al EMPLEADO en forma oportuna el acceso a la información necesario para el cumplimiento de sus obligaciones y del objeto contratado.

3. Suministrar al EMPLEADO los elementos que requiera para el desarrollo de sus funciones.

4. Realizar de forma oportuna los pagos correspondientes a la Seguridad Social del Empleado.

5. Supervisar la ejecución del servicio profesional encomendado.

6. Formular las observaciones que considere pertinentes y necesarias con el fin de ser analizadas conjuntamente con el EMPLEADO.

7. Cumplir con lo preceptuado en las demás clausulas y condiciones previstas en este documento.

8. Poner a disposición de los trabajadores, salvo estipulación en contrario, los instrumentos adecuados y las materias primas necesarias para la realización de las labores.

9. Procurar a los trabajadores apropiarlos de elementos adecuados de protección contra los accidentes y enfermedades profesionales en forma que se garanticen razonablemente la seguridad y la salud.

10. Prestar inmediatamente los primeros auxilios en caso de accidente o de enfermedad. A este efecto en todo establecimiento, taller o fábrica que ocupe habitualmente más de diez (10) trabajadores, deberá mantenerse lo necesario, según reglamentación de las autoridades sanitarias.

11. Guardar absoluto respeto a la dignidad personal del trabajador, a sus creencias y sentimientos.

12. Cumplir el reglamento y mantener el orden, la moralidad y el respeto a las leyes.

13. Conceder al trabajador las licencias necesarias para el ejercicio del sufragio; para el desempeño de cargos oficiales transitorios de forzosa aceptación; en caso de grave calamidad doméstica debidamente comprobada; para desempeñar comisiones sindicales inherentes a la organización o para asistir al entierro de sus compañeros, siempre que avise con la debida oportunidad al empleador o a su representante y que, en los dos (2) últimos casos, el número de los que se ausenten, no sea tal que perjudique el funcionamiento de la empresa o Institución. En el reglamento de trabajo se señalarán las condiciones para las licencias antes dichas.

14. Los demás que establezca la ley.', 60],
    ['descuentos', 7, 'DESCUENTOS', 'Del salario devengado por el EMPLEADO se descontará mensualmente el cuatro (4%) por ciento de aportes al fondo de pensión; el porcentaje restante será asumido por el EMPLEADOR de acuerdo con lo establecido por la ley.', 70],
    ['periodo_prueba', 8, 'PERÍODO DE PRUEBA', 'Acuerdan las partes fijar como período de prueba SESENTA (60) DÍAS. En caso de existir prórroga o un nuevo contrato entre las partes se entiende que no existirá un nuevo período de prueba. Durante el período aquí establecido el EMPLEADOR y el EMPLEADO pueden dar por terminado unilateral el presente contrato.', 80],
    ['terminacion', 9, 'CAUSALES DE TERMINACIÓN', 'El contrato de trabajo termina:

- Por muerte del trabajador.

- Por mutuo consentimiento.

- Por expiración del plazo fijo pactado.

- Por terminación de la obra o labor contratada.

- Por liquidación o clausura definitiva de la empresa o establecimiento.

- Por suspensión de actividades por parte del empleador durante más de ciento veinte (120) días).

- Por sentencia ejecutoriada.

- Por decisión unilateral.

- Por no regresar el trabajador a su empleo, al desaparecer las causas de la suspensión del contrato.', 90],
    ['penal', 10, 'CLAUSULA PENAL', 'En caso de incumplimiento por parte del EMPLEADO cualquiera de las obligaciones estipuladas en este contrato, sin justa causa dará derecho al EMPLEADOR al pago o reconocimiento de un valor equivalente a 1/3 parte del cumplimiento que se haya efectuado del contrato inicialmente pactado cuya base de liquidación será la sumatoria de los valores netos recibidos como ingresos por parte del EMPLEADO, a la fecha de la diferencia surgida.', 100],
    ['compromisoria', 11, 'CLAUSULA COMPROMISORIA', 'Las partes convienen que en el evento en que surja alguna diferencia entre las mismas, con ocasión al presente contrato, será resuelta por el abogado que desempeñe el cargo en el {{institucion_nombre}}, quien actuará conforme a la ley.', 110],
];

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $n = $pdo->query('SELECT COUNT(*) FROM contratos_clausulas WHERE id_tenant = ' . intval($ID_TENANT))->fetchColumn();
    echo "BD: $DB_NAME (tenant $ID_TENANT)\n";
    echo "Clausulas actuales en el tenant: $n\n";

    if (!$ejecutar) {
        echo "\n[MODO REVISION] No se ejecuto nada.\n";
        echo "Para aplicar:  php " . basename(__FILE__) . " SI\n";
        exit(0);
    }

    $pdo->beginTransaction();

    // Idempotencia: limpiar lo propio antes de reinsertar
    $st = $pdo->prepare('DELETE FROM cargos_plantillas_contratos WHERE id_tenant = ? AND id_cargo = ? AND id_tipo_contrato = ?');
    $st->execute([$ID_TENANT, $ID_CARGO, $ID_TIPO_CONTRATO]);
    $st = $pdo->prepare('DELETE FROM plantillas WHERE id_tenant = ? AND clave = ?');
    $st->execute([$ID_TENANT, $CLAVE_PLANTILLA]);

    // 1) LIMPIEZA: clausulas heredadas de Lumen
    $st = $pdo->prepare('DELETE FROM contratos_clausulas WHERE id_tenant = ?');
    $st->execute([$ID_TENANT]);
    $borradas = $st->rowCount();

    // 2a) Resolver id_tipo_plantilla por codigo (sin hardcodear)
    $st = $pdo->prepare("SELECT id FROM tipos_plantillas WHERE codigo = 'contrato_laboral'");
    $st->execute();
    $idTipoPlantilla = $st->fetchColumn();
    if (!$idTipoPlantilla) { throw new Exception("No existe tipo de plantilla con codigo 'contrato_laboral'"); }

    // 2b) SHELL de la plantilla laboral
    $idPlantilla = uuid4();
    $st = $pdo->prepare(
        'INSERT INTO plantillas (id, id_tenant, id_tipo_plantilla, clave, titulo, contenido, fecha_creacion, fecha_actualizacion)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $st->execute([$idPlantilla, $ID_TENANT, $idTipoPlantilla, $CLAVE_PLANTILLA, 'Contrato de trabajo - Docente (año escolar)', $SHELL_JSON]);

    // 3) CLAUSULAS atadas al cargo Docente
    $st = $pdo->prepare(
        'INSERT INTO contratos_clausulas (id, id_tenant, tipo, id_cargo, numero, subnumero, titulo, contenido, orden, activo)
         VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, 1)'
    );
    foreach ($CLAUSULAS as $c) {
        list($tipo, $numero, $titulo, $contenido, $orden) = $c;
        $st->execute([uuid4(), $ID_TENANT, $tipo, $ID_CARGO, $numero, $titulo, $contenido, $orden]);
    }

    // 4) MAPEO (cargo + tipo) -> plantilla
    $st = $pdo->prepare(
        'INSERT INTO cargos_plantillas_contratos (id, id_tenant, activo, id_cargo, id_plantilla, id_tipo_contrato)
         VALUES (?, ?, 1, ?, ?, ?)'
    );
    $st->execute([uuid4(), $ID_TENANT, $ID_CARGO, $idPlantilla, $ID_TIPO_CONTRATO]);

    $pdo->commit();

    echo "\n[OK] Cambios aplicados.\n";
    echo "  - Clausulas borradas (Lumen): $borradas\n";
    echo "  - Plantilla shell creada:     $CLAVE_PLANTILLA ($idPlantilla)\n";
    echo "  - Clausulas insertadas:       " . count($CLAUSULAS) . " (cargo Docente)\n";
    echo "  - Mapeo Docente + NOMINA_FIJO -> plantilla: creado\n";
    echo "\nRecuerda borrar este archivo cuando termines.\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    if (!$esCli) { http_response_code(500); }
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    echo "No se aplico ningun cambio (rollback).\n";
    exit(1);
}
