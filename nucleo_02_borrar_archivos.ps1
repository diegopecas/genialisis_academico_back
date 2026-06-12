# =====================================================================
# NÚCLEO ADMIN BACK: eliminar services y routes del dominio educativo
# Ejecutar DENTRO del clon (nucleo-admin-back), nunca en el repo original.
# Uso:  .\nucleo_02_borrar_archivos.ps1            -> simulacro (no borra)
#       .\nucleo_02_borrar_archivos.ps1 -Ejecutar  -> borra de verdad
# =====================================================================
param([switch]$Ejecutar)

$services = @(
  "services\actividades-academicas-x-indicadores-logros.service.php",
  "services\actividades-academicas.service.php",
  "services\acudientes.service.php",
  "services\alimentacion.service.php",
  "services\ambientes.service.php",
  "services\area-academica-x-grupo.service.php",
  "services\areas-academicas.service.php",
  "services\asignacion-onces.service.php",
  "services\asistencia-estudiantes.service.php",
  "services\autorizados_recoger.service.php",
  "services\autorizados_recoger_historial.service.php",
  "services\calificaciones.service.php",
  "services\casas-docentes.service.php",
  "services\categorias-medidas.service.php",
  "services\clasificacion-menus.service.php",
  "services\cobros-automaticos-historial.service.php",
  "services\cocina-disponibilidad.service.php",
  "services\competencias-cognitivas.service.php",
  "services\contratos_matricula.service.php",
  "services\contratos_matricula_valores.service.php",
  "services\convenios-estudiante.service.php",
  "services\cortes-academicos.service.php",
  "services\cuentas-cobrar-x-curso-extra.service.php",
  "services\cursos-extra.service.php",
  "services\dashboard-gerencial.service.php",
  "services\datos-adicionales-x-estudiante.service.php",
  "services\datos-medicos-x-estudiante.service.php",
  "services\dias-x-sprint.service.php",
  "services\docentes-x-cursos-extra.service.php",
  "services\docentes.service.php",
  "services\docentes_x_grupos.service.php",
  "services\ead3-evaluaciones.service.php",
  "services\ead3-items.service.php",
  "services\ead3-rangos-edad.service.php",
  "services\ead3-tablas-conversion.service.php",
  "services\ejes-curriculares.service.php",
  "services\entrega-alimentacion.service.php",
  "services\esferas-desarrollo.service.php",
  "services\estados-tareas.service.php",
  "services\estandares-basicos.service.php",
  "services\estudiantes-diario.service.php",
  "services\estudiantes-x-cursos-extra.service.php",
  "services\estudiantes-x-grupos.service.php",
  "services\estudiantes.service.php",
  "services\galeria_imagenes.service.php",
  "services\galerias.service.php",
  "services\galerias_x_grupos.service.php",
  "services\gini.service.php",
  "services\grados-x-grupo.service.php",
  "services\grados.service.php",
  "services\grupos.service.php",
  "services\historial-informes-estudiantes.service.php",
  "services\historial-recordatorios-asistencia.service.php",
  "services\historial-recordatorios-generales.service.php",
  "services\historial-recordatorios-pago.service.php",
  "services\horarios-alimentacion.service.php",
  "services\horarios-cursos-extra.service.php",
  "services\horarios-estudiante.service.php",
  "services\horarios.service.php",
  "services\ia_cobertura_curricular.service.php",
  "services\ia_maquina_actividades.service.php",
  "services\indicadores-logros.service.php",
  "services\items-menu.service.php",
  "services\logros.service.php",
  "services\materiales_x_actividad.service.php",
  "services\medidas-x-estudiantes.service.php",
  "services\medidas.service.php",
  "services\menu-minutas.service.php",
  "services\menus.service.php",
  "services\motor-cobros-automaticos.service.php",
  "services\objetivos-academicos.service.php",
  "services\observaciones-estudiantes.service.php",
  "services\onces-personas.service.php",
  "services\onces.service.php",
  "services\parametros-calificaciones.service.php",
  "services\porciones.service.php",
  "services\productos-academico.service.php",
  "services\proveedores-x-cursos-extra.service.php",
  "services\puntos-casas-docentes.service.php",
  "services\reglas-cobro-automatico.service.php",
  "services\reportes_pago.service.php",
  "services\servicios-faltantes.service.php",
  "services\servicios-jardin.service.php",
  "services\sprints.service.php",
  "services\subgalerias.service.php",
  "services\tareas-x-sprints-x-estudiante.service.php",
  "services\tareas-x-sprints.service.php",
  "services\tarifas-cursos-extra.service.php",
  "services\tarifas_grupos.service.php",
  "services\test.service.php",
  "services\tipos-actividades-academicas.service.php",
  "services\tipos-acudiente.service.php",
  "services\tipos-evento-cobro.service.php",
  "services\tipos-importancia-servicio-faltante.service.php",
  "services\tipos-observaciones-estudiantes.service.php",
  "services\tipos-producto-academico.service.php",
  "services\tipos_autorizacion_recoger.service.php",
  "services\tipos_necesidades_especiales.service.php",
  "services\unidades-medidas-corporales.service.php",
  "services\valores-medidas.service.php",
  "services\valores-parametros-calificaciones.service.php",
  "services\visitas-ninos.service.php",
  "services\visitas-servicios-gustaron.service.php",
  "services\visitas-servicios-no-tenemos.service.php",
  "services\visitas_ninos_necesidades.service.php"
)

$routes = @(
  "routes\academico.routes.php",
  "routes\alimentacion.routes.php",
  "routes\asistencia.routes.php",
  "routes\calificaciones.routes.php",
  "routes\cobros-automaticos.routes.php",
  "routes\dashboard-gerencial.routes.php",
  "routes\docentes.routes.php",
  "routes\ead3.routes.php",
  "routes\estudiantes.routes.php",
  "routes\galerias.routes.php",
  "routes\malla-curricular.routes.php",
  "routes\medidas.routes.php",
  "routes\menus.routes.php",
  "routes\productos-academico.routes.php",
  "routes\reportes_pago.routes.php",
  "routes\sprints.routes.php",
  "routes\tester.routes.php"
)

$otros = @(
  "services\estructura.txt",
  "routes\estructura.txt",
  "estructura.txt",
  "estructura_back.txt",
  "archivo.zip"
)

$todos = $services + $routes + $otros
$borrados = 0; $faltantes = 0
foreach ($f in $todos) {
  if (Test-Path $f) {
    if ($Ejecutar) { Remove-Item $f -Force; Write-Host "BORRADO  $f" -ForegroundColor Red }
    else           { Write-Host "[simulacro] borraria  $f" -ForegroundColor Yellow }
    $borrados++
  } else {
    Write-Host "NO EXISTE $f" -ForegroundColor DarkGray; $faltantes++
  }
}
Write-Host ""
if ($Ejecutar) { Write-Host "Eliminados: $borrados | No encontrados: $faltantes" -ForegroundColor Cyan }
else { Write-Host "SIMULACRO: se borrarian $borrados archivos ($faltantes no encontrados). Ejecuta con -Ejecutar para aplicar." -ForegroundColor Cyan }