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

# Python-ის გამოტანა UTF-8-ში
$env:PYTHONIOENCODING = 'utf-8'

# Ctrl+C არ უნდა წყვეტდეს სინქრონიზაციას (Selenium-ის driver-ები console-ს Ctrl+C-ს უგზავნიან)
try { [Console]::TreatControlCAsInput = $true } catch { }

function Write-Log([string]$line) {
    [IO.File]::AppendAllText($log, $line + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))
}

# პროცესს ვუშვებთ პირდაპირ (cmd /c-ის გარეშე — Task Scheduler-ში ის Ctrl+C-ს იღებდა და სინქრონიზაციას წყვეტდა),
# გამოტანას დროებით ფაილებში ვიღებთ და ბაიტებად ვამატებთ ლოგს (კოდირება არ იცვლება).
function Invoke-Logged([string]$file, [string]$arguments) {
    $out = [IO.Path]::GetTempFileName()
    $err = [IO.Path]::GetTempFileName()
    try {
        # ცალკე (დამალული) console: ბრაუზერის driver-ების Ctrl+C სიგნალი ამ სკრიპტს აღარ წყვეტს
        $process = Start-Process -FilePath $file -ArgumentList $arguments -WorkingDirectory $import `
            -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput $out -RedirectStandardError $err
        foreach ($path in @($out, $err)) {
            $bytes = [IO.File]::ReadAllBytes($path)
            if ($bytes.Length -gt 0) {
                $stream = [IO.File]::Open($log, [IO.FileMode]::Append, [IO.FileAccess]::Write, [IO.FileShare]::Read)
                try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Close() }
            }
        }
        return $process.ExitCode
    } finally {
        Remove-Item -Force -ErrorAction SilentlyContinue -Path $out, $err
    }
}

$python = (Get-Command python -ErrorAction Stop).Source
$php = 'C:\xampp2\php\php.exe'
$importScript = Join-Path $root 'scripts\update_sports_data.php'

try {
    Write-Log "=== $(Get-Date -Format s) sync start"
    if ((Invoke-Logged $python 'scrape_flashscore.py') -ne 0) { throw 'Standings scraper failed.' }
    if ((Invoke-Logged $python 'match_flashscore_scraper.py') -ne 0) { throw 'Matches scraper failed.' }
    if ((Invoke-Logged $php "`"$importScript`" all") -ne 0) { throw 'Database import failed.' }
    Write-Log "=== $(Get-Date -Format s) sync done"
} catch {
    Write-Log "$(Get-Date -Format s) $($_.Exception.Message)"
    exit 1
}
