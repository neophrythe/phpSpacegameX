# PowerShell Script to Setup Local MySQL Database for SpacegameX

# --- Configuration ---
$mysqlUser = "root"
$mysqlHost = "localhost"
$databaseName = "spacegamex_local"
$projectBasePath = "f:\\sdi\\wog\\SpacegameX" # Adjusted to the correct base path
$databasePath = Join-Path $projectBasePath "database"

# SQL files in order of execution
$sqlFiles = @(
    "schema.sql",
    "combat_schema.sql",
    "ships_schema.sql",
    "galaxy_expansion.sql",
    "initial_data.sql",
    "ships_initial_data.sql",
    "seeds\\static_buildings.sql"
)

# --- Script ---

Write-Host "SpacegameX Local Database Setup Script"
Write-Host "------------------------------------"

# Prompt for MySQL root password
$mysqlPassword = Read-Host -Prompt "Enter MySQL '$($mysqlUser)' password (leave blank if none)"

# Construct base mysql command
$mysqlCommandBase = "mysql -h $($mysqlHost) -u $($mysqlUser)"
if (-not [string]::IsNullOrEmpty($mysqlPassword)) {
    $mysqlCommandBase += " -p'$($mysqlPassword)'"
}

# 1. Drop existing database (optional, for a clean setup)
Write-Host "Attempting to drop database '$($databaseName)' if it exists..."
$dropDbCommand = "$($mysqlCommandBase) -e 'DROP DATABASE IF EXISTS $($databaseName);'" # Removed backticks around database name
try {
    Invoke-Expression $dropDbCommand
    Write-Host "Database '$($databaseName)' dropped successfully or did not exist." -ForegroundColor Green
} catch {
    Write-Warning "Could not drop database '$($databaseName)'. Error: $($_.Exception.Message)"
    # It might fail if the DB doesn't exist, which is fine. Or due to permissions/password.
}

# 2. Create new database
Write-Host "Creating database '$($databaseName)'..."
$createDbCommand = "$($mysqlCommandBase) -e 'CREATE DATABASE $($databaseName) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'" # Removed backticks
try {
    Invoke-Expression $createDbCommand
    Write-Host "Database '$($databaseName)' created successfully." -ForegroundColor Green
} catch {
    Write-Error "Failed to create database '$($databaseName)'. Error: $($_.Exception.Message)"
    exit 1
}

# 3. Import SQL files
Write-Host "Importing SQL files into '$($databaseName)'..."
$mysqlImportCommandBase = "$($mysqlCommandBase) $($databaseName)"

foreach ($sqlFile in $sqlFiles) {
    $fullSqlFilePath = Join-Path $databasePath $sqlFile
    if (Test-Path $fullSqlFilePath) {
        Write-Host "  Importing '$($sqlFile)'..."
        # Use Get-Content to pipe the SQL file content to mysql
        try {
            # Use cmd /c to handle input redirection reliably
            # Removed single quotes around $($fullSqlFilePath) for cmd.exe
            $importProcessCommand = "cmd /c ""$($mysqlImportCommandBase) < $($fullSqlFilePath)"""
            Invoke-Expression $importProcessCommand
            Write-Host "  Successfully imported '$($sqlFile)'." -ForegroundColor Green
        } catch {
            Write-Error "  Failed to import '$($sqlFile)'. Error: $($_.Exception.Message)"
            Write-Warning "  Aborting further imports."
            exit 1
        }
    } else {
        Write-Warning "  SQL file not found: '$($fullSqlFilePath)'. Skipping."
    }
}

Write-Host "------------------------------------"
Write-Host "Local database setup complete for '$($databaseName)'." -ForegroundColor Cyan
Write-Host "Make sure your Apache/PHP is configured and running."
Write-Host "You should be able to access the game at the BASE_URL defined in config.php."
