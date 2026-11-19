$taskName = 'SportBlog Sports Data Update'
$script = Join-Path $PSScriptRoot 'update_sports_data.ps1'

# -WindowStyle Hidden: ფანჯარა აღარ ამოხტება ყოველ 30 წუთში (მისი დახურვა სინქრონიზაციას წყვეტდა)
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(2) -RepetitionInterval (New-TimeSpan -Minutes 30)

# ლეპტოპზე ბატარეით მუშაობისასაც გაეშვას; 15 წუთზე მეტი თუ გაიჭედა, შეწყდეს
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 15)

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Description 'Refresh SportBlog matches and league standings every 30 minutes.' -Force
