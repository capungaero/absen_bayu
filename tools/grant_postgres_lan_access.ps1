#Requires -Version 5.1
<#
.SYNOPSIS
Allow PostgreSQL database access from a LAN subnet and grant database privileges.

.EXAMPLE
.\tools\grant_postgres_lan_access.ps1 -DbName app_db -DbUser app_user -LanCidr 192.168.1.0/24

.EXAMPLE
.\tools\grant_postgres_lan_access.ps1 -DbName app_db -DbUser app_user -LanCidr 192.168.1.0/24 -CreateLogin -GrantMode readwrite -OpenFirewall
#>

[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_][A-Za-z0-9_\-]{0,62}$')]
    [string]$DbName,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9_][A-Za-z0-9_\-]{0,62}$')]
    [string]$DbUser,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\/(?:[1-9]|[12]\d|3[0-2])$')]
    [string]$LanCidr,

    [ValidateSet('readonly', 'readwrite', 'all')]
    [string]$GrantMode = 'readwrite',

    [string]$PgHost = '127.0.0.1',

    [ValidateRange(1, 65535)]
    [int]$PgPort = 5432,

    [string]$AdminUser = 'postgres',

    [SecureString]$AdminPassword,

    [switch]$CreateLogin,

    [SecureString]$DbPassword,

    [switch]$OpenFirewall
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function ConvertTo-PlainText {
    param([SecureString]$SecureValue)

    if (-not $SecureValue) {
        return $null
    }

    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
}

function Invoke-Psql {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments,

        [string]$PasswordPlain
    )

    $oldPassword = $env:PGPASSWORD
    try {
        if ($PasswordPlain) {
            $env:PGPASSWORD = $PasswordPlain
        }

        & psql @Arguments
        if ($LASTEXITCODE -ne 0) {
            throw "psql failed with exit code $LASTEXITCODE"
        }
    }
    finally {
        $env:PGPASSWORD = $oldPassword
    }
}

function Quote-Identifier {
    param([string]$Value)
    return '"' + $Value.Replace('"', '""') + '"'
}

function Quote-Literal {
    param([string]$Value)
    return "'" + $Value.Replace("'", "''") + "'"
}

function Invoke-PsqlScalar {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Sql,

        [string]$PasswordPlain
    )

    $oldPassword = $env:PGPASSWORD
    try {
        if ($PasswordPlain) {
            $env:PGPASSWORD = $PasswordPlain
        }

        $result = & psql -h $PgHost -p $PgPort -U $AdminUser -d postgres -Atqc $Sql
        if ($LASTEXITCODE -ne 0) {
            throw "psql failed with exit code $LASTEXITCODE"
        }

        return ($result | Select-Object -First 1)
    }
    finally {
        $env:PGPASSWORD = $oldPassword
    }
}

if (-not (Get-Command psql -ErrorAction SilentlyContinue)) {
    throw 'psql was not found in PATH. Install PostgreSQL client tools or add the PostgreSQL bin folder to PATH.'
}

if ($LanCidr -in @('0.0.0.0/0', '255.255.255.255/32')) {
    throw 'Refusing unsafe LAN CIDR. Use a specific subnet such as 192.168.1.0/24.'
}

if (-not $AdminPassword) {
    $AdminPassword = Read-Host "Password for PostgreSQL admin user '$AdminUser'" -AsSecureString
}

if ($CreateLogin -and -not $DbPassword) {
    $DbPassword = Read-Host "Password for database user '$DbUser'" -AsSecureString
}

$adminPasswordPlain = ConvertTo-PlainText $AdminPassword
$dbPasswordPlain = ConvertTo-PlainText $DbPassword

$dbNameIdent = Quote-Identifier $DbName
$dbUserIdent = Quote-Identifier $DbUser
$dbUserLiteral = Quote-Literal $DbUser

Write-Host "Checking PostgreSQL configuration paths..."
$hbaFile = Invoke-PsqlScalar -Sql 'SHOW hba_file;' -PasswordPlain $adminPasswordPlain
$configFile = Invoke-PsqlScalar -Sql 'SHOW config_file;' -PasswordPlain $adminPasswordPlain

if (-not (Test-Path -LiteralPath $hbaFile)) {
    throw "pg_hba.conf not found at: $hbaFile"
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$hbaBackup = "$hbaFile.$timestamp.bak"
Copy-Item -LiteralPath $hbaFile -Destination $hbaBackup -Force
Write-Host "Backup created: $hbaBackup"

$hbaLine = "host    $DbName    $DbUser    $LanCidr    scram-sha-256"
$existingHba = Get-Content -LiteralPath $hbaFile -Raw
if ($existingHba -notmatch [regex]::Escape($hbaLine)) {
    if ($PSCmdlet.ShouldProcess($hbaFile, "Append LAN rule: $hbaLine")) {
        Add-Content -LiteralPath $hbaFile -Value @('', '# Added by grant_postgres_lan_access.ps1', $hbaLine)
    }
}
else {
    Write-Host 'pg_hba.conf already contains the requested LAN rule.'
}

Write-Host "Setting PostgreSQL listen_addresses to '*' via ALTER SYSTEM..."
Invoke-Psql -PasswordPlain $adminPasswordPlain -Arguments @(
    '-h', $PgHost,
    '-p', $PgPort,
    '-U', $AdminUser,
    '-d', 'postgres',
    '-v', 'ON_ERROR_STOP=1',
    '-c', "ALTER SYSTEM SET listen_addresses = '*';"
)

if ($CreateLogin) {
    $passwordLiteral = Quote-Literal $dbPasswordPlain
    $createLoginSql = @"
DO `$`$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = $dbUserLiteral) THEN
        CREATE ROLE $dbUserIdent LOGIN PASSWORD $passwordLiteral;
    ELSE
        ALTER ROLE $dbUserIdent LOGIN PASSWORD $passwordLiteral;
    END IF;
END
`$`$;
"@

    Write-Host "Creating or updating login role '$DbUser'..."
    Invoke-Psql -PasswordPlain $adminPasswordPlain -Arguments @(
        '-h', $PgHost,
        '-p', $PgPort,
        '-U', $AdminUser,
        '-d', 'postgres',
        '-v', 'ON_ERROR_STOP=1',
        '-c', $createLoginSql
    )
}

$grantSql = switch ($GrantMode) {
    'readonly' {
        @"
GRANT CONNECT ON DATABASE $dbNameIdent TO $dbUserIdent;
GRANT USAGE ON SCHEMA public TO $dbUserIdent;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO $dbUserIdent;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO $dbUserIdent;
"@
    }
    'readwrite' {
        @"
GRANT CONNECT ON DATABASE $dbNameIdent TO $dbUserIdent;
GRANT USAGE, CREATE ON SCHEMA public TO $dbUserIdent;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO $dbUserIdent;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO $dbUserIdent;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO $dbUserIdent;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO $dbUserIdent;
"@
    }
    'all' {
        @"
GRANT ALL PRIVILEGES ON DATABASE $dbNameIdent TO $dbUserIdent;
GRANT ALL PRIVILEGES ON SCHEMA public TO $dbUserIdent;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $dbUserIdent;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $dbUserIdent;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON TABLES TO $dbUserIdent;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL PRIVILEGES ON SEQUENCES TO $dbUserIdent;
"@
    }
}

Write-Host "Granting '$GrantMode' privileges on database '$DbName' to '$DbUser'..."
Invoke-Psql -PasswordPlain $adminPasswordPlain -Arguments @(
    '-h', $PgHost,
    '-p', $PgPort,
    '-U', $AdminUser,
    '-d', $DbName,
    '-v', 'ON_ERROR_STOP=1',
    '-c', $grantSql
)

if ($OpenFirewall) {
    $ruleName = "PostgreSQL LAN $PgPort"
    if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
        Write-Host "Creating Windows Firewall rule '$ruleName'..."
        New-NetFirewallRule `
            -DisplayName $ruleName `
            -Direction Inbound `
            -Action Allow `
            -Protocol TCP `
            -LocalPort $PgPort `
            -RemoteAddress $LanCidr | Out-Null
    }
    else {
        Write-Host "Windows Firewall rule '$ruleName' already exists."
    }
}

Write-Host 'Reloading PostgreSQL configuration...'
Invoke-Psql -PasswordPlain $adminPasswordPlain -Arguments @(
    '-h', $PgHost,
    '-p', $PgPort,
    '-U', $AdminUser,
    '-d', 'postgres',
    '-v', 'ON_ERROR_STOP=1',
    '-c', 'SELECT pg_reload_conf();'
)

Write-Host ''
Write-Host 'Done.'
Write-Host "Config file : $configFile"
Write-Host "HBA file    : $hbaFile"
Write-Host "HBA backup  : $hbaBackup"
Write-Host ''
Write-Host 'Important: listen_addresses changes may require a PostgreSQL service restart before LAN clients can connect.'
