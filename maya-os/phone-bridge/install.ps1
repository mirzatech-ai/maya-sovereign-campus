# Maya Device Bridge · Windows installer (PowerShell)
# ====================================================
# Run in PowerShell (admin not required):
#   iwr https://iamsuperio.cloud/phone-bridge/install.ps1 -UseB | iex
#
# What this does:
#   1. Verifies Python 3.10+ is installed (offers winget install if not)
#   2. pip-installs fastapi + uvicorn (user scope)
#   3. Downloads server.py to %USERPROFILE%\maya-bridge\
#   4. Creates a maya-bridge.bat launcher in %USERPROFILE%\maya-bridge\
#   5. Creates a Start-Menu shortcut "Maya Bridge"
#   6. Optional: adds a Task Scheduler entry to auto-start on logon
#   7. Prints the bridge token + tells Mo to paste it into Maya OS

$ErrorActionPreference = "Stop"
$Esc = [char]27
function Banner($t) { Write-Host "$Esc[36m=== $t$Esc[0m" }
function OK($t)     { Write-Host "$Esc[32m  ok  $t$Esc[0m" }
function Warn($t)   { Write-Host "$Esc[33m  ??  $t$Esc[0m" }
function Fail($t)   { Write-Host "$Esc[31m  !!  $t$Esc[0m"; exit 1 }

Banner "Maya Device Bridge - Windows installer"

# 1. Python check
try {
    $pyVer = & python --version 2>&1
    if ($pyVer -match "Python (3\.(1[0-9]|[2-9][0-9]))") {
        OK "found $pyVer"
    } else {
        Warn "Python version too old: $pyVer (need 3.10+)"
        $install = Read-Host "Install Python 3.12 via winget now? (y/n)"
        if ($install -eq "y") { winget install -e --id Python.Python.3.12 } else { Fail "Python 3.10+ required" }
    }
} catch {
    Warn "Python not found"
    $install = Read-Host "Install Python 3.12 via winget now? (y/n)"
    if ($install -eq "y") {
        winget install -e --id Python.Python.3.12
        Warn "Re-run this installer after Python finishes installing"
        exit 0
    } else {
        Fail "Python 3.10+ required - install manually from python.org"
    }
}

# 2. pip packages
Banner "Installing FastAPI + uvicorn (user scope)"
& python -m pip install --user --upgrade pip *> $null
& python -m pip install --user fastapi uvicorn
if ($LASTEXITCODE -ne 0) { Fail "pip install failed" }
OK "deps installed"

# 3. Download server.py
$bridgeDir = Join-Path $env:USERPROFILE "maya-bridge"
New-Item -ItemType Directory -Force -Path $bridgeDir | Out-Null
$serverPath = Join-Path $bridgeDir "server.py"
Banner "Downloading server.py"
try {
    Invoke-WebRequest -Uri "https://iamsuperio.cloud/phone-bridge/server.py" -OutFile $serverPath -UseBasicParsing
    OK "saved to $serverPath"
} catch {
    Fail "download failed: $($_.Exception.Message)"
}

# 4. Launcher .bat
$launcher = Join-Path $bridgeDir "maya-bridge.bat"
@"
@echo off
title Maya Device Bridge
python "%USERPROFILE%\maya-bridge\server.py" %*
"@ | Set-Content -Path $launcher -Encoding ASCII
OK "launcher created: $launcher"

# 5. Start-Menu shortcut
$startMenu = [Environment]::GetFolderPath('Programs')
$shortcutPath = Join-Path $startMenu "Maya Bridge.lnk"
try {
    $ws = New-Object -ComObject WScript.Shell
    $sc = $ws.CreateShortcut($shortcutPath)
    $sc.TargetPath = $launcher
    $sc.WorkingDirectory = $bridgeDir
    $sc.Description = "Maya Device Bridge - lets Maya see this Windows machine"
    $sc.WindowStyle = 1
    $sc.Save()
    OK "Start Menu shortcut: 'Maya Bridge'"
} catch {
    Warn "could not create Start Menu shortcut: $($_.Exception.Message)"
}

# 6. Optional autostart via Task Scheduler
$auto = Read-Host "Auto-start the bridge on login? (y/n)"
if ($auto -eq "y") {
    try {
        $taskName = "MayaDeviceBridge"
        $action = New-ScheduledTaskAction -Execute $launcher -WorkingDirectory $bridgeDir
        $trigger = New-ScheduledTaskTrigger -AtLogon -User $env:USERNAME
        $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero)
        $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
        Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
        OK "auto-start enabled (Task Scheduler: $taskName)"
    } catch {
        Warn "could not register Task Scheduler entry: $($_.Exception.Message)"
    }
}

# 7. Prime token + print
Banner "Generating bridge token"
$tokenPath = Join-Path $env:USERPROFILE ".maya_bridge_token"
if (-not (Test-Path $tokenPath)) {
    $bytes = New-Object byte[] 48
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $token = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+','-').Replace('/','_')
    $token | Out-File -FilePath $tokenPath -Encoding ASCII -NoNewline
}
$token = (Get-Content $tokenPath -Raw).Trim()

# Auto-register with Maya OS · zero copy-paste
Banner "Auto-registering this device with Maya OS"
$pin = $env:MAYA_COMMANDER_PIN
if (-not $pin) {
    Write-Host "  Commander PIN required ONCE to register this device with Maya OS."
    Write-Host "  Set `$env:MAYA_COMMANDER_PIN before re-running to skip this prompt next time."
    $pinSecure = Read-Host -AsSecureString "  Commander PIN"
    $pin = [System.Net.NetworkCredential]::new('', $pinSecure).Password
}
$registered = $false
$regId = $null
try {
    $payload = @{
        action        = 'register'
        pin           = $pin
        device_name   = $env:COMPUTERNAME
        platform      = 'windows'
        url           = 'http://127.0.0.1:8765'
        token         = $token
        capabilities  = @{ files = $true; shell = $true; ssh = $true; termux_api = $false; clipboard = $true; open = $true; notify = $true }
    } | ConvertTo-Json -Depth 4 -Compress
    $resp = Invoke-RestMethod -Uri 'https://iamsuperio.cloud/api/maya_devices' -Method Post -Body $payload -ContentType 'application/json' -TimeoutSec 15
    if ($resp.ok) {
        $registered = $true
        $regId = $resp.id
        OK ("registered as '$($env:COMPUTERNAME)' (id: $regId)")
    } else {
        Warn ("auto-register failed: " + ($resp | ConvertTo-Json -Compress))
    }
} catch {
    Warn ("auto-register error: $($_.Exception.Message)")
}

Write-Host ""
Write-Host "$Esc[32m============================================================$Esc[0m"
Write-Host "$Esc[32m  INSTALLED$Esc[0m"
Write-Host ""
Write-Host "  Bridge directory:  $bridgeDir"
Write-Host "  Launcher:          $launcher"
Write-Host ""
if ($registered) {
    Write-Host "  $Esc[32m✓ AUTO-REGISTERED$Esc[0m with Maya OS as '$($env:COMPUTERNAME)' (windows)"
    Write-Host "    Device id: $regId"
    Write-Host "    NO MANUAL SETUP NEEDED IN MAYA OS · just open + go"
} else {
    Write-Host "  ✗ Auto-registration failed. Manual fallback:"
    Write-Host "    Bridge token (paste into Maya OS - Device Bridge):"
    Write-Host "$Esc[33m      $token$Esc[0m"
    Write-Host "    Bridge URL: http://127.0.0.1:8765"
}
Write-Host ""
Write-Host "  Start the bridge now: $Esc[36mmaya-bridge.bat$Esc[0m  (from $bridgeDir)"
Write-Host "  Or from Start Menu:   'Maya Bridge'"
Write-Host "$Esc[32m============================================================$Esc[0m"
