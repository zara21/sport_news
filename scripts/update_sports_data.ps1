$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$import = Join-Path $root 'admin\import'
$logDir = Join-Path $root 'storage\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$log = Join-Path $logDir 'sports-sync.log'

try {
    Push-Location $import
    python scrape_flashscore.py *>> $log
    if ($LASTEXITCODE -ne 0) { throw 'Standings scraper failed.' }
    python match_flashscore_scraper.py *>> $log
    if ($LASTEXITCODE -ne 0) { throw 'Matches scraper failed.' }
    & 'C:\xampp2\php\php.exe' (Join-Path $root 'scripts\update_sports_data.php') all *>> $log
    if ($LASTEXITCODE -ne 0) { throw 'Database import failed.' }
} catch {
    "$(Get-Date -Format s) $($_.Exception.Message)" | Add-Content -Path $log
    exit 1
} finally {
    Pop-Location
}
