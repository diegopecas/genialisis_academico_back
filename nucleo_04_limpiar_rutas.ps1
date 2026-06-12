# =====================================================================
# NÚCLEO ADMIN BACK: eliminar rutas que referencian clases inexistentes
# Compara las clases usadas en routes\*.routes.php contra las clases
# realmente definidas en services\, controllers\ y webhook\, y elimina
# las líneas de ruta cuya clase ya no existe (services borrados).
# Crea respaldo .bak de cada archivo modificado.
# Uso:  .\nucleo_04_limpiar_rutas.ps1            -> simulacro (no modifica)
#       .\nucleo_04_limpiar_rutas.ps1 -Ejecutar  -> aplica los cambios
# =====================================================================
param([switch]$Ejecutar)

# 1. Recolectar todas las clases definidas en el código que quedó
$definidas = New-Object System.Collections.Generic.HashSet[string]
$carpetas = @('services', 'controllers', 'webhook', 'utils')
foreach ($dir in $carpetas) {
  if (-not (Test-Path $dir)) { continue }
  Get-ChildItem $dir -Filter *.php -Recurse | ForEach-Object {
    $contenido = Get-Content $_.FullName -Raw
    foreach ($m in [regex]::Matches($contenido, '(?m)^\s*(?:final\s+|abstract\s+)?class\s+(\w+)')) {
      [void]$definidas.Add($m.Groups[1].Value)
    }
  }
}
Write-Host "Clases definidas encontradas: $($definidas.Count)" -ForegroundColor Cyan

# 2. Revisar cada archivo de rutas
$totalEliminadas = 0
$archivosModificados = 0
Get-ChildItem routes -Filter *.routes.php -Recurse | ForEach-Object {
  $archivo = $_
  $lineas = Get-Content $archivo.FullName
  $nuevas = New-Object System.Collections.Generic.List[string]
  $eliminadasAqui = 0

  foreach ($ln in $lineas) {
    $borrar = $false
    # Patrón A: [Clase::class, 'metodo']
    $m = [regex]::Match($ln, '\[\s*(\w+)::class\s*,')
    if ($m.Success -and -not $definidas.Contains($m.Groups[1].Value)) { $borrar = $true }
    # Patrón B: ['Clase', 'metodo']
    if (-not $borrar) {
      $m2 = [regex]::Match($ln, "\[\s*'(\w+)'\s*,\s*'")
      if ($m2.Success -and -not $definidas.Contains($m2.Groups[1].Value)) { $borrar = $true }
    }
    if ($borrar) {
      $clase = if ($m.Success) { $m.Groups[1].Value } else { $m2.Groups[1].Value }
      $eliminadasAqui++
      if ($Ejecutar) { Write-Host "  ELIMINADA [$clase] $($ln.Trim())" -ForegroundColor Red }
      else           { Write-Host "  [simulacro] eliminaria [$clase] $($ln.Trim())" -ForegroundColor Yellow }
      continue
    }
    $nuevas.Add($ln)
  }

  if ($eliminadasAqui -gt 0) {
    Write-Host "$($archivo.Name): $eliminadasAqui rutas con clases inexistentes" -ForegroundColor Magenta
    if ($Ejecutar) {
      Copy-Item $archivo.FullName "$($archivo.FullName).bak" -Force
      Set-Content -Path $archivo.FullName -Value $nuevas -Encoding UTF8
    }
    $archivosModificados++
    $totalEliminadas += $eliminadasAqui
  }
}

Write-Host ""
if ($Ejecutar) {
  Write-Host "LISTO: $totalEliminadas rutas eliminadas en $archivosModificados archivos (respaldos .bak creados)." -ForegroundColor Cyan
  Write-Host "Si todo funciona, borra los .bak con: Remove-Item routes\*.bak" -ForegroundColor DarkGray
} else {
  Write-Host "SIMULACRO: $totalEliminadas rutas a eliminar en $archivosModificados archivos. Ejecuta con -Ejecutar para aplicar." -ForegroundColor Cyan
}
