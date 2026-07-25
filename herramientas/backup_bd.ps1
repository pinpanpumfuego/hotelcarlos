# Copia de seguridad de la base de datos hotelcarlos hacia OneDrive.
# Uso:  powershell -ExecutionPolicy Bypass -File backup_bd.ps1
# Conserva los últimos 30 días de copias.

$mysqldump = "C:\xamppum\mysql\bin\mysqldump.exe"
$baseDatos = "hotelcarlos"
$carpetaDestino = Join-Path $env:USERPROFILE "OneDrive\BackupsHotelCarlos"
$diasConservar = 30

if (-not (Test-Path $carpetaDestino)) {
    New-Item -ItemType Directory -Force $carpetaDestino | Out-Null
}

$fecha = Get-Date -Format "yyyy-MM-dd_HHmm"
$archivo = Join-Path $carpetaDestino "hotelcarlos_$fecha.sql"

& $mysqldump -u root --single-transaction --routines --triggers $baseDatos | Out-File -FilePath $archivo -Encoding utf8

if ($LASTEXITCODE -eq 0 -and (Get-Item $archivo).Length -gt 0) {
    Write-Output "Copia creada: $archivo"
    # Comprimir para ahorrar espacio en OneDrive
    Compress-Archive -Path $archivo -DestinationPath "$archivo.zip" -Force
    Remove-Item $archivo -Force
    Write-Output "Comprimida: $archivo.zip"
} else {
    Write-Error "La copia de seguridad fallo. Revisa que MySQL este arrancado."
    exit 1
}

# Eliminar copias con más de $diasConservar días
Get-ChildItem $carpetaDestino -Filter "hotelcarlos_*.sql.zip" |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$diasConservar) } |
    Remove-Item -Force

Write-Output "Copias actuales en ${carpetaDestino}:"
Get-ChildItem $carpetaDestino -Filter "hotelcarlos_*.zip" | Select-Object Name, Length, LastWriteTime
