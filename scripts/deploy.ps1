<#
.SYNOPSIS
    Publishes the frontend and API to https://wish.trickypig.com/.

.DESCRIPTION
    Builds the frontend, then previews what would change. Nothing is written to
    the server unless you pass -Push.

    What goes up:
      frontend/dist/*               to  <remote>/frontend/dist/
      git-tracked api/ files        to  <remote>/api/   (except api/tests/)

    What is never uploaded or deleted: .env, .htaccess, .git and data/. The only
    remote files ever deleted are stale build files in frontend/dist/assets/.

    Tracked files go up as they are in the working tree, so uncommitted edits do
    go live. A new API file does not until it is at least `git add`ed. The script
    warns about both.

    -Push runs in an order that keeps the live site working: migration files and
    models, then the migration, then the rest of the API, then new build assets,
    then index.html, and finally the removal of stale assets.

    Connection details come from .vscode/sftp.json. rsync runs inside WSL and logs
    in with the WSL ssh key, never the password in that file.

.PARAMETER Push
    Actually publish. Without it nothing is written to the server.

.EXAMPLE
    .\scripts\deploy.ps1
    .\scripts\deploy.ps1 -Push
#>
[CmdletBinding()]
param(
    [switch]$Push
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

trap {
    Write-Host ''
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

$RepoRoot = Split-Path -Parent $PSScriptRoot
$SiteUrl = 'https://wish.trickypig.com'
$RemotePhp = 'php8.4-cli'

# Applied to every rsync run, so these can't be overwritten or removed.
$ProtectedFiles = @('--exclude=.env', '--exclude=.env.*', '--exclude=.htaccess', '--exclude=.git', '--exclude=/data/')

# Checksums rather than timestamps, so the preview lists real changes only.
# New files get normal web permissions; existing files keep the host's.
$RsyncBase = @('rsync', '-rltzc', '--chmod=D755,F644', '--itemize-changes')

function Invoke-Wsl {
    param([string[]]$Arguments)
    # Windows PowerShell turns a native command's stderr into terminating errors under 'Stop'.
    $ErrorActionPreference = 'Continue'
    $output = & wsl.exe -e @Arguments 2>&1
    return [pscustomobject]@{ ExitCode = $LASTEXITCODE; Output = @($output | ForEach-Object { $_.ToString() }) }
}

function ConvertTo-WslPath($path) {
    $full = [IO.Path]::GetFullPath($path)
    $drive = $full.Substring(0, 1).ToLowerInvariant()
    return "/mnt/$drive" + $full.Substring(2).Replace('\', '/')
}

function Read-DeployTarget {
    $configPath = Join-Path $RepoRoot '.vscode\sftp.json'
    if (-not (Test-Path $configPath)) {
        throw 'No .vscode/sftp.json - this script reads the host, user and remote path from it.'
    }
    $config = Get-Content $configPath -Raw | ConvertFrom-Json

    # remotePath is absolute against the SFTP jail, but over ssh a leading slash
    # would mean the real filesystem root.
    $remote = $config.remotePath.Trim('/')
    if ($remote -ne 'trickypig/wish') {
        throw "sftp.json remotePath is '$($config.remotePath)', expected '/trickypig/wish/'."
    }
    return [pscustomobject]@{ Login = "$($config.username)@$($config.host)"; Path = $remote }
}

function Get-SshCommand {
    $rsync = Invoke-Wsl @('sh', '-c', 'command -v rsync')
    if ($rsync.ExitCode -ne 0) {
        throw 'No rsync inside WSL. Install it there: wsl -e sudo apt install rsync'
    }
    $key = Invoke-Wsl @('sh', '-c', 'stat -c%a "$HOME/.ssh/id_ed25519"')
    if ($key.ExitCode -ne 0 -or $key.Output[0].Trim() -ne '600') {
        throw 'WSL needs the ssh key at ~/.ssh/id_ed25519 with mode 600. See the publishing-the-site skill.'
    }
    $wslHome = (Invoke-Wsl @('sh', '-c', 'echo $HOME')).Output[0].Trim()

    # RemoteCommand=none because ~/.ssh/config sets one for this host; BatchMode
    # so a key problem fails instead of waiting on a password.
    return "ssh -i $wslHome/.ssh/id_ed25519 -o RemoteCommand=none -o BatchMode=yes -o ConnectTimeout=20"
}

function Build-Frontend {
    Write-Host 'Building the frontend...' -ForegroundColor Cyan
    $frontend = Join-Path $RepoRoot 'frontend'
    $ErrorActionPreference = 'Continue'
    $output = & npm.cmd --prefix $frontend run build 2>&1
    if ($LASTEXITCODE -ne 0) {
        $output | ForEach-Object { Write-Host "  $_" }
        throw 'The frontend build failed, so nothing was published.'
    }
    $ErrorActionPreference = 'Stop'

    $dist = Join-Path $frontend 'dist'
    if (-not (Test-Path (Join-Path $dist 'index.html')) -or
        -not (Get-ChildItem (Join-Path $dist 'assets') -File -ErrorAction SilentlyContinue)) {
        throw 'The build has no index.html or assets, so nothing was published.'
    }
    return $dist
}

function New-ApiStaging {
    $tracked = @(& git -C $RepoRoot ls-files -- api)
    if ($LASTEXITCODE -ne 0) { throw 'git ls-files failed.' }

    $staging = Join-Path ([IO.Path]::GetTempPath()) 'wish-deploy-api'
    if (Test-Path $staging) { Remove-Item -Recurse -Force $staging }

    $count = 0
    foreach ($file in $tracked) {
        if ($file -match '^api/tests/') { continue }
        $source = Join-Path $RepoRoot $file
        if (-not (Test-Path -LiteralPath $source)) { continue }
        $destination = Join-Path $staging ($file -replace '^api/', '')
        New-Item -ItemType Directory -Force (Split-Path -Parent $destination) | Out-Null
        Copy-Item -LiteralPath $source $destination
        $count++
    }
    if (-not (Test-Path (Join-Path $staging 'index.php')) -or
        -not (Test-Path (Join-Path $staging 'migrations\migrate.php'))) {
        throw 'The API staging folder is incomplete, so nothing was published.'
    }
    return [pscustomobject]@{ Path = $staging; Count = $count }
}

function Write-Warnings {
    $warnings = @()

    $branch = (& git -C $RepoRoot branch --show-current).Trim()
    if ($branch -ne 'main') { $warnings += "You are on branch '$branch', not main." }

    $changed = @(& git -C $RepoRoot status --porcelain --untracked-files=no -- api frontend/src frontend/public)
    if ($changed.Count -gt 0) {
        $warnings += "$($changed.Count) uncommitted change(s) will go live:"
        $warnings += @($changed | ForEach-Object { "    $_" })
    }

    $untracked = @(& git -C $RepoRoot ls-files --others --exclude-standard -- api)
    if ($untracked.Count -gt 0) {
        $warnings += "$($untracked.Count) new API file(s) will NOT go up until you git add them:"
        $warnings += @($untracked | ForEach-Object { "    $_" })
    }

    foreach ($warning in $warnings) { Write-Host "  ! $warning" -ForegroundColor Yellow }
    if ($warnings.Count -gt 0) { Write-Host '' }
}

function Invoke-Sync {
    param(
        [string]$Label,
        [string]$Source,
        [string]$Destination,
        [string[]]$Extra = @(),
        [switch]$DryRun
    )
    $arguments = $RsyncBase + $ProtectedFiles + $Extra
    if ($DryRun) { $arguments += '--dry-run' }
    $arguments += @('-e', $script:SshCommand, ((ConvertTo-WslPath $Source).TrimEnd('/') + '/'), $Destination)

    $result = Invoke-Wsl $arguments
    if ($result.ExitCode -ne 0) {
        $result.Output | ForEach-Object { Write-Host "  $_" }
        throw "$Label failed (rsync exit code $($result.ExitCode)). The publish stopped here."
    }
    return @($result.Output | Where-Object { $_ -match '^[<>ch.]f' -or $_ -match '^\*deleting' })
}

function Show-Changes($label, $changes) {
    $real = @($changes | Where-Object { $_ -match '^[<c]f' -or $_ -match '^\*deleting' })
    Write-Host "  $label - $($real.Count) change(s)" -ForegroundColor DarkGray
    $real | Select-Object -First 60 | ForEach-Object { Write-Host "    $_" }
    if ($real.Count -gt 60) { Write-Host "    ... and $($real.Count - 60) more" -ForegroundColor DarkGray }
    return $real.Count
}

function Invoke-RemoteMigration($target) {
    Write-Host 'Running the database migration...' -ForegroundColor Cyan
    $result = Invoke-Wsl @('sh', '-c', "$script:SshCommand $($target.Login) 'cd $($target.Path) && $RemotePhp api/migrations/migrate.php'")
    $failed = $result.ExitCode -ne 0 -or
        -not ($result.Output -match 'Migration complete') -or
        ($result.Output -match '\[ERROR\]')
    if ($failed) {
        $result.Output | ForEach-Object { Write-Host "  $_" }
        throw 'The migration failed. The API code and frontend were not updated; only migration files and models changed.'
    }
}

function Test-LiveSite {
    Write-Host 'Checking the live site...' -ForegroundColor Cyan
    $ErrorActionPreference = 'Continue'
    $page = (& curl.exe -s -m 20 "$SiteUrl/") -join "`n"
    $asset = [regex]::Match($page, '/assets/[^"]+\.js').Value

    $checks = @(
        @{ Name = 'home page'; Path = '/'; Expected = '200' },
        @{ Name = 'reset page'; Path = '/reset-password'; Expected = '200' },
        @{ Name = 'API rejects anonymous requests'; Path = '/api/auth/me'; Expected = '401' }
    )
    if ($asset) { $checks += @{ Name = 'current script file'; Path = $asset; Expected = '200' } }
    else { Write-Host '  ! No script file found in the home page' -ForegroundColor Yellow; $script:VerifyFailed = $true }

    foreach ($check in $checks) {
        $code = & curl.exe -s -o NUL -m 20 -w '%{http_code}' "$SiteUrl$($check.Path)"
        if ($code -eq $check.Expected) {
            Write-Host "  ok  $($check.Name)" -ForegroundColor DarkGray
        } else {
            Write-Host "  !   $($check.Name): got $code, expected $($check.Expected)" -ForegroundColor Red
            $script:VerifyFailed = $true
        }
    }
}

# ---- Local preparation ----

$target = Read-DeployTarget
$script:SshCommand = Get-SshCommand
$script:VerifyFailed = $false
$dist = Build-Frontend
$api = New-ApiStaging
$assets = Join-Path $dist 'assets'

$apiDestination = "$($target.Login):$($target.Path)/api/"
$distDestination = "$($target.Login):$($target.Path)/frontend/dist/"
$assetsDestination = "$($target.Login):$($target.Path)/frontend/dist/assets/"

$rootOnly = @('--exclude=/assets/')
$migrationFilesOnly = @('--include=/migrations/***', '--include=/models/***', '--exclude=*')

Write-Host ''
Write-Warnings

$check = "cd $($target.Path) && test -f .env && test -d api && test -d frontend/dist && echo ok"
$reachable = Invoke-Wsl @('sh', '-c', "$script:SshCommand $($target.Login) '$check'")
if ($reachable.ExitCode -ne 0 -or -not ($reachable.Output -contains 'ok')) {
    $reachable.Output | ForEach-Object { Write-Host "  $_" }
    throw 'Could not reach the server with the ssh key, or the remote folders are not where expected.'
}

# ---- Preview ----

$apiChanges = Invoke-Sync 'API preview' $api.Path $apiDestination -DryRun
$rootChanges = Invoke-Sync 'Frontend preview' $dist $distDestination -Extra $rootOnly -DryRun
$assetChanges = Invoke-Sync 'Assets preview' $assets $assetsDestination -Extra @('--delete') -DryRun

$total = 0
$total += Show-Changes 'API' $apiChanges
$total += Show-Changes 'Frontend pages' $rootChanges
$total += Show-Changes 'Frontend assets' $assetChanges
Write-Host ''

if (-not $Push) {
    Write-Host "Preview only - nothing was written. $total change(s) would be made." -ForegroundColor Yellow
    Write-Host 'The database migration would also run. Pass -Push to publish.' -ForegroundColor Yellow
    return
}

# ---- Publish ----

Write-Host 'Uploading migration files and models...' -ForegroundColor Cyan
Invoke-Sync 'Migration upload' $api.Path $apiDestination -Extra $migrationFilesOnly | Out-Null

Invoke-RemoteMigration $target

Write-Host 'Uploading the API...' -ForegroundColor Cyan
Invoke-Sync 'API upload' $api.Path $apiDestination | Out-Null

Write-Host 'Uploading the frontend...' -ForegroundColor Cyan
Invoke-Sync 'Asset upload' $assets $assetsDestination | Out-Null
Invoke-Sync 'Frontend upload' $dist $distDestination -Extra $rootOnly | Out-Null
Invoke-Sync 'Stale asset removal' $assets $assetsDestination -Extra @('--delete') | Out-Null

Write-Host ''
Test-LiveSite
Write-Host ''

if ($script:VerifyFailed) {
    Write-Host 'Published, but the live checks above failed. Look at the site now.' -ForegroundColor Red
    exit 1
}
Write-Host "Published - $SiteUrl" -ForegroundColor Green
