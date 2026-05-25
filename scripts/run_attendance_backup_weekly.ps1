param(
    [string]$PythonExe = "python",
    [string]$MysqlExe = "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe",
    [bool]$PreviousWeek = $true
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir "..")
$Extractor = Join-Path $ScriptDir "attendance_backup_report.py"
$OutDir = Join-Path $ProjectRoot "exports\attendance_backups"

$argsList = @(
    $Extractor,
    "--out-dir", $OutDir,
    "--mysql", $MysqlExe
)

if ($PreviousWeek) {
    $argsList += "--previous-week"
}

& $PythonExe @argsList
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
