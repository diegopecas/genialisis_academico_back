# =====================================================================
# GENIALISIS ACADÉMICO BACK: restaurar el dominio educativo desde el
# backend original de lumen hacia este repo producto.
# Ejecutar en la RAÍZ de genialisis_academico_back.
#
# Estrategia: copiar services\ y routes\ COMPLETOS desde el original.
# - Restaura los 105 services del dominio.
# - Restaura las versiones originales de los routes (válidas de nuevo
#   porque todas las clases vuelven a existir).
# - Restaura las versiones originales de usuarios/personas/wa-conversaciones
#   (con docentes/estudiantes/acudientes, correctas en este producto).
# NO toca: config\ (tenants y credenciales propias de cada instalación),
# index.php, flight\, webhook\, vendor\.
#
# Uso:  .\genialisis_02_restaurar_dominio_back.ps1            -> simulacro
#       .\genialisis_02_restaurar_dominio_back.ps1 -Ejecutar  -> aplica
#       Ajusta el origen si difiere:  -Origen "C:\ruta\al\back\original"
# =====================================================================
param(
  [switch]$Ejecutar,
  [string]$Origen = "C:\xampp\htdocs\lumen_academico_back"
)

if (-not (Test-Path (Join-Path $Origen "services")) -or -not (Test-Path (Join-Path $Origen "routes"))) {
  Write-Host "ERROR: no se encontraron services\ y routes\ en $Origen. Ajusta -Origen." -ForegroundColor Red
  exit 1
}
if (-not (Test-Path "index.php")) {
  Write-Host "ERROR: ejecuta este script desde la raíz de genialisis_academico_back (donde está index.php)." -ForegroundColor Red
  exit 1
}

$total = 0
foreach ($carpeta in @("services", "routes")) {
  $archivos = Get-ChildItem (Join-Path $Origen $carpeta) -Filter *.php -Recurse
  Write-Host "== $carpeta ($($archivos.Count) archivos) ==" -ForegroundColor Cyan
  foreach ($a in $archivos) {
    # ruta relativa dentro de la carpeta (conserva subcarpetas como services\portal)
    $rel = $a.FullName.Substring((Join-Path $Origen $carpeta).Length).TrimStart('\')
    $destino = Join-Path $carpeta $rel
    if ($Ejecutar) {
      $padre = Split-Path $destino -Parent
      if ($padre -and -not (Test-Path $padre)) { New-Item -ItemType Directory -Path $padre -Force | Out-Null }
      Copy-Item $a.FullName $destino -Force
      Write-Host "RESTAURADO  $carpeta\$rel" -ForegroundColor Green
    } else {
      $estado = if (Test-Path $destino) { "(sobrescribe)" } else { "(nuevo)" }
      Write-Host "[simulacro] restauraria $estado  $carpeta\$rel" -ForegroundColor Yellow
    }
    $total++
  }
}

# Limpiar respaldos .bak heredados del núcleo, ya sin sentido en el producto
Get-ChildItem routes -Filter *.bak -ErrorAction SilentlyContinue | ForEach-Object {
  if ($Ejecutar) { Remove-Item $_.FullName -Force; Write-Host "ELIMINADO  routes\$($_.Name)" -ForegroundColor DarkGray }
  else           { Write-Host "[simulacro] eliminaria  routes\$($_.Name)" -ForegroundColor DarkGray }
}

Write-Host ""
if ($Ejecutar) { Write-Host "LISTO: $total archivos restaurados desde $Origen" -ForegroundColor Cyan }
else { Write-Host "SIMULACRO: $total archivos a restaurar. Ejecuta con -Ejecutar para aplicar." -ForegroundColor Cyan }
