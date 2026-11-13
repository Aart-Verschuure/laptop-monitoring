# Laptop Monitoring - Windows agent
# Verzamelt open programma's, systeemgegevens, specificaties en inlogmomenten
# en stuurt die naar het Laravel-dashboard. Wordt elke minuut gestart door
# de geplande taak die install.ps1 aanmaakt.
#
# Let op: dit bestand bevat bewust alleen ASCII-tekens (Windows PowerShell 5.1).

$ErrorActionPreference = 'Stop'
$baseDir    = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $baseDir 'config.json'
$statePath  = Join-Path $baseDir 'state.json'
$logPath    = Join-Path $baseDir 'agent.log'

function Write-Log($message) {
    $line = '{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message
    Add-Content -Path $logPath -Value $line
    # Houd het logbestand klein
    if ((Get-Item $logPath).Length -gt 512KB) {
        Get-Content $logPath -Tail 500 | Set-Content $logPath
    }
}

function Iso($date) {
    if ($null -eq $date) { return $null }
    return ([datetime]$date).ToString('o')
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$state  = if (Test-Path $statePath) { Get-Content $statePath -Raw | ConvertFrom-Json } else { [pscustomobject]@{} }
foreach ($prop in 'lastLoginRecordId', 'specsSentAt') {
    if (-not ($state.PSObject.Properties.Name -contains $prop)) {
        $state | Add-Member -NotePropertyName $prop -NotePropertyValue $null
    }
}

$payload = @{}

# ---------------------------------------------------------------- open programma's
try {
    $apps = @(
        Get-Process | Where-Object { $_.MainWindowHandle -ne 0 -and $_.MainWindowTitle } | ForEach-Object {
            $started = $null
            try { $started = $_.StartTime } catch { }
            $name = $_.ProcessName
            try { if ($_.Description) { $name = $_.Description } } catch { }
            @{
                name       = $name
                title      = $_.MainWindowTitle
                pid        = $_.Id
                started_at = (Iso $started)
            }
        }
    )
    $payload.apps = $apps
} catch {
    Write-Log "Programma's ophalen mislukt: $($_.Exception.Message)"
}

# ---------------------------------------------------------------- systeemgegevens
try {
    $os   = Get-CimInstance Win32_OperatingSystem
    $cpu  = (Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'").PercentProcessorTime
    $disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='$($env:SystemDrive)'"
    $bat  = Get-CimInstance Win32_Battery | Select-Object -First 1

    $metrics = @{
        cpu_percent     = [double]$cpu
        memory_total_mb = [int]($os.TotalVisibleMemorySize / 1024)
        memory_used_mb  = [int](($os.TotalVisibleMemorySize - $os.FreePhysicalMemory) / 1024)
        disk_total_gb   = [math]::Round($disk.Size / 1GB, 1)
        disk_used_gb    = [math]::Round(($disk.Size - $disk.FreeSpace) / 1GB, 1)
        booted_at       = (Iso $os.LastBootUpTime)
        recorded_at     = (Iso (Get-Date))
    }
    if ($bat) {
        $metrics.battery_percent = [int]$bat.EstimatedChargeRemaining
        $metrics.charging        = ($bat.BatteryStatus -ne 1)   # 1 = ontladen
    }
    $payload.metrics = $metrics
} catch {
    Write-Log "Systeemgegevens ophalen mislukt: $($_.Exception.Message)"
}

# ---------------------------------------------------------------- specificaties (1x per uur)
$specsDue = (-not $state.specsSentAt) -or (((Get-Date) - [datetime]$state.specsSentAt).TotalMinutes -ge 60)
if ($specsDue) {
    try {
        $cs    = Get-CimInstance Win32_ComputerSystem
        $proc  = Get-CimInstance Win32_Processor | Select-Object -First 1
        $bios  = Get-CimInstance Win32_BIOS
        $gpus  = @(Get-CimInstance Win32_VideoController | ForEach-Object { $_.Name })
        $osI   = Get-CimInstance Win32_OperatingSystem
        $drive = Get-CimInstance Win32_DiskDrive | Select-Object -First 1
        $batI  = Get-CimInstance Win32_Battery | Select-Object -First 1

        $payload.specs = [ordered]@{
            computernaam      = $env:COMPUTERNAME
            fabrikant         = $cs.Manufacturer
            model             = $cs.Model
            serienummer       = $bios.SerialNumber
            besturingssysteem = $osI.Caption
            windows_versie    = "$($osI.Version) (build $($osI.BuildNumber))"
            processor         = $proc.Name.Trim()
            kernen            = $proc.NumberOfCores
            threads           = $proc.NumberOfLogicalProcessors
            max_kloksnelheid  = "$($proc.MaxClockSpeed) MHz"
            werkgeheugen      = "{0:N1} GB" -f ($cs.TotalPhysicalMemory / 1GB)
            videokaart        = $gpus
            schijf            = if ($drive) { "$($drive.Model) ({0:N0} GB)" -f ($drive.Size / 1GB) } else { $null }
            batterij          = if ($batI) { $batI.Name } else { 'geen' }
            bios_versie       = $bios.SMBIOSBIOSVersion
            ingelogde_gebruiker = "$env:USERDOMAIN\$env:USERNAME"
        }
    } catch {
        Write-Log "Specificaties ophalen mislukt: $($_.Exception.Message)"
    }
}

# ---------------------------------------------------------------- inlogmomenten
# 4624 = geslaagde aanmelding, 4625 = mislukte aanmelding (Security-log, vereist admin-rechten)
$newLastRecordId = $state.lastLoginRecordId
try {
    if ($state.lastLoginRecordId) {
        $xpath = "*[System[(EventID=4624 or EventID=4625) and EventRecordID > $($state.lastLoginRecordId)]]"
    } else {
        # Eerste keer: laatste 30 dagen
        $xpath = "*[System[(EventID=4624 or EventID=4625) and TimeCreated[timediff(@SystemTime) <= 2592000000]]]"
    }

    $events = @(Get-WinEvent -LogName Security -FilterXPath $xpath -MaxEvents 5000 -ErrorAction SilentlyContinue)
    $logins = @()

    foreach ($ev in ($events | Sort-Object RecordId)) {
        if ($null -eq $newLastRecordId -or $ev.RecordId -gt $newLastRecordId) { $newLastRecordId = $ev.RecordId }

        $x = [xml]$ev.ToXml()
        $d = @{}
        foreach ($item in $x.Event.EventData.Data) { $d[$item.Name] = $item.'#text' }

        $user = $d['TargetUserName']
        if (-not $user -or $user.EndsWith('$') -or $user -match '^(DWM-|UMFD-)' -or $user -in 'SYSTEM', 'LOCAL SERVICE', 'NETWORK SERVICE', 'ANONYMOUS LOGON') { continue }

        $logonType = [int]$d['LogonType']
        if ($logonType -notin 2, 7, 10, 11) { continue }   # alleen echte (interactieve) aanmeldingen

        if ($ev.Id -eq 4624) {
            # Een beheerder krijgt 2 gekoppelde aanmeldingen tegelijk; tel er maar 1.
            if ($d['TargetLinkedLogonId'] -and $d['TargetLinkedLogonId'] -ne '0x0' -and $d['ElevatedToken'] -ne '%%1842') { continue }
            $type = switch ($logonType) { 7 { 'unlock' } 10 { 'remote' } default { 'logon' } }
        } else {
            $type = 'failed'
        }

        $logins += @{
            type        = $type
            username    = if ($d['TargetDomainName']) { "$($d['TargetDomainName'])\$user" } else { $user }
            source_id   = "win-$($ev.RecordId)"
            occurred_at = (Iso $ev.TimeCreated)
        }
    }

    if ($logins.Count -gt 0) { $payload.logins = $logins }
} catch {
    Write-Log "Inlogmomenten ophalen mislukt (draait de taak met hoogste rechten?): $($_.Exception.Message)"
}

# ---------------------------------------------------------------- versturen
try {
    $json  = $payload | ConvertTo-Json -Depth 6 -Compress
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)

    Invoke-RestMethod -Method Post -Uri $config.serverUrl -Body $bytes -TimeoutSec 30 `
        -ContentType 'application/json; charset=utf-8' `
        -Headers @{ Authorization = "Bearer $($config.token)"; Accept = 'application/json' } | Out-Null

    $state.lastLoginRecordId = $newLastRecordId
    if ($payload.ContainsKey('specs')) { $state.specsSentAt = (Get-Date).ToString('o') }
    $state | ConvertTo-Json | Set-Content $statePath
} catch {
    Write-Log "Versturen mislukt: $($_.Exception.Message)"
    exit 1
}
