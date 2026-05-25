param(
    [string]$TaskName = "AbsenBayu Weekly Attendance Backup",
    [ValidateSet("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")]
    [string]$DayOfWeek = "Monday",
    [string]$At = "01:00",
    [string]$PythonExe = "python",
    [string]$MysqlExe = "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Runner = Join-Path $ScriptDir "run_attendance_backup_weekly.ps1"

$taskArgs = @(
    "-NoProfile",
    "-ExecutionPolicy", "Bypass",
    "-File", "`"$Runner`"",
    "-PythonExe", "`"$PythonExe`"",
    "-MysqlExe", "`"$MysqlExe`"",
    "-PreviousWeek", "1"
) -join " "

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $taskArgs
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek $DayOfWeek -At $At

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Description "Backup dan report CSV absen mingguan absen_bayu" `
    -Force

Get-ScheduledTask -TaskName $TaskName
