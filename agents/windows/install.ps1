# Installeert de Laptop Monitoring agent als geplande taak (elke minuut).
# Uitvoeren in PowerShell ALS ADMINISTRATOR:
#   powershell -ExecutionPolicy Bypass -File .\install.ps1 -ServerUrl http://localhost:8000/api/report -Token JOUW_TOKEN
#
# Verwijderen:  powershell -ExecutionPolicy Bypass -File .\install.ps1 -Uninstall

param(
    [string]$ServerUrl,
    [string]$Token,
    [switch]$Uninstall
)

$ErrorActionPreference = 'Stop'
$taskName  = 'LaptopMonitoringAgent'
$targetDir = Join-Path $env:ProgramData 'LaptopMonitoring'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'Start PowerShell als administrator en probeer het opnieuw.' -ForegroundColor Red
    exit 1
}

if ($Uninstall) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Remove-Item $targetDir -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host 'Agent verwijderd.' -ForegroundColor Green
    exit 0
}

if (-not $ServerUrl -or -not $Token) {
    Write-Host 'Gebruik: .\install.ps1 -ServerUrl http://localhost:8000/api/report -Token JOUW_TOKEN' -ForegroundColor Yellow
    exit 1
}

New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
Copy-Item (Join-Path $PSScriptRoot 'laptop-agent.ps1') $targetDir -Force
@{ serverUrl = $ServerUrl; token = $Token } | ConvertTo-Json | Set-Content (Join-Path $targetDir 'config.json')

# Alleen administrators en SYSTEM mogen het token lezen
icacls $targetDir /inheritance:r /grant:r '*S-1-5-32-544:(OI)(CI)F' '*S-1-5-18:(OI)(CI)F' | Out-Null

$script = Join-Path $targetDir 'laptop-agent.ps1'
# conhost --headless voorkomt dat er elke minuut een venster in beeld flitst
$action = New-ScheduledTaskAction -Execute 'conhost.exe' `
    -Argument "--headless powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$script`""

$repeat = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
$logon  = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"

$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Highest
$settings  = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 2) -StartWhenAvailable

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $repeat, $logon `
    -Principal $principal -Settings $settings -Force | Out-Null

Start-ScheduledTask -TaskName $taskName
Write-Host "Agent geinstalleerd in $targetDir en gestart." -ForegroundColor Green
Write-Host "Problemen? Bekijk $targetDir\agent.log"
