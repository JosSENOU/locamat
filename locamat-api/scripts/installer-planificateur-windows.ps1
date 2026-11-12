[CmdletBinding()]
param(
    [string] $TaskName = 'LocaMat Laravel Scheduler'
)

$ErrorActionPreference = 'Stop'

$artisanPath = (Resolve-Path (Join-Path $PSScriptRoot '..\artisan')).Path
$php = Get-Command php -ErrorAction Stop
$phpPath = $php.Source

if (-not $phpPath -or -not (Test-Path -LiteralPath $phpPath -PathType Leaf)) {
    throw 'PHP est introuvable dans le PATH. Installez PHP ou ajoutez php.exe au PATH, puis reessayez.'
}

$taskArguments = '"{0}" schedule:run' -f $artisanPath
$existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue

if ($existingTask) {
    $existingAction = $existingTask.Actions | Select-Object -First 1
    $existingArguments = ([string] $existingAction.Arguments).Replace('"', '')
    $expectedArguments = $taskArguments.Replace('"', '')

    if ($existingAction.Execute -eq $phpPath -and $existingArguments -eq $expectedArguments) {
        Write-Host "La tache '$TaskName' est deja configuree pour ce projet."
        exit 0
    }

    throw "La tache '$TaskName' existe deja avec une autre commande. Verifiez-la avant de la remplacer."
}

$taskRun = '"{0}" "{1}" schedule:run' -f $phpPath, $artisanPath
& "$env:SystemRoot\System32\schtasks.exe" /Create /SC MINUTE /MO 1 /TN $TaskName /TR $taskRun /F

if ($LASTEXITCODE -ne 0) {
    throw "La tâche '$TaskName' n'a pas pu être créée (code $LASTEXITCODE)."
}

Write-Host "Tache '$TaskName' creee. Verifiez-la avec Get-ScheduledTask -TaskName '$TaskName'."
