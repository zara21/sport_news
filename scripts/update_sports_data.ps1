$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$import = Join-Path $root 'admin\import'
$logDir = Join-Path $root 'storage\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$log = Join-Path $logDir 'sports-sync.log'

# ლოგის როტაცია: 2MB-ს ზემოთ ძველი ლოგი გადადის .1-ში (წინა .1 იშლება), რომ ფაილი უსასრულოდ არ გაიზარდოს
$maxLogBytes = 2MB
if ((Test-Path $log) -and ((Get-Item $log).Length -gt $maxLogBytes)) {
    Move-Item -Force -Path $log -Destination "$log.1"
}

# Python-ის გამოტანა UTF-8-ში; cmd-ის გადამისამართება ბაიტებს ცვლილების გარეშე წერს
# (PowerShell 5.1-ის *>> UTF-16-ში წერდა და ფაილს ორჯერ ადიდებდა)
$env:PYTHONIOENCODING = 'utf-8'

function Invoke-Logged([string]$commandLine) {
    cmd /c "$commandLine >> `"$log`" 2>&1"
    return $LASTEXITCODE
}

try {
    Push-Location $import
    "=== $(Get-Date -Format s) sync start" | Out-File -FilePath $log -Append -Encoding utf8
    if ((Invoke-Logged 'python scrape_flashscore.py') -ne 0) { throw 'Standings scraper failed.' }
    if ((Invoke-Logged 'python match_flashscore_scraper.py') -ne 0) { throw 'Matches scraper failed.' }
    $php = 'C:\xampp2\php\php.exe'
    $importScript = Join-Path $root 'scripts\update_sports_data.php'
    if ((Invoke-Logged "`"$php`" `"$importScript`" all") -ne 0) { throw 'Database import failed.' }
} catch {
    "$(Get-Date -Format s) $($_.Exception.Message)" | Out-File -FilePath $log -Append -Encoding utf8
    exit 1
} finally {
    Pop-Location
}
