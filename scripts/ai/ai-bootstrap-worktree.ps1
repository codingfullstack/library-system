[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $WorktreePath
)

. "$PSScriptRoot/Common.ps1"

$coordinationRepo = Get-RepoRoot
$worktree = Assert-RepositoryWorktree -CoordinationRepo $coordinationRepo -WorktreePath $WorktreePath

Assert-CleanGit -Repo $worktree

function Assert-TrackedFilesUnchanged {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $trackedChanges = @(Invoke-Git -Repo $Repo -Arguments @('status', '--porcelain=v1', '--untracked-files=no'))
    if ($trackedChanges.Count -gt 0) {
        throw "Bootstrap modified tracked files in ${Repo}:`n$($trackedChanges -join [Environment]::NewLine)"
    }
}

function Invoke-InWorktree {
    param(
        [Parameter(Mandatory = $true)][string] $FilePath,
        [Parameter(Mandatory = $true)][string[]] $Arguments,
        [string] $DisplayName = ''
    )

    $previousLocation = Get-Location
    try {
        Set-Location -LiteralPath $worktree
        Invoke-ExternalCommand -WorkingDirectory $worktree -FilePath $FilePath -Arguments $Arguments -DisplayName $DisplayName
    } finally {
        Set-Location -LiteralPath $previousLocation
    }
}

function Get-PackageManagerCommand {
    param([Parameter(Mandatory = $true)][string] $Repo)

    if (Test-Path -LiteralPath (Join-Path $Repo 'package-lock.json') -PathType Leaf) {
        return [pscustomobject] @{ FilePath = 'npm.cmd'; Arguments = @('ci'); Name = 'npm ci' }
    }

    if (Test-Path -LiteralPath (Join-Path $Repo 'pnpm-lock.yaml') -PathType Leaf) {
        return [pscustomobject] @{ FilePath = 'pnpm.cmd'; Arguments = @('install', '--frozen-lockfile'); Name = 'pnpm install --frozen-lockfile' }
    }

    if (Test-Path -LiteralPath (Join-Path $Repo 'yarn.lock') -PathType Leaf) {
        return [pscustomobject] @{ FilePath = 'yarn.cmd'; Arguments = @('install', '--frozen-lockfile'); Name = 'yarn install --frozen-lockfile' }
    }

    return $null
}

function Get-PackageBuildScript {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $packageJsonPath = Join-Path $Repo 'package.json'
    if (-not (Test-Path -LiteralPath $packageJsonPath -PathType Leaf)) {
        return $null
    }

    $package = Get-Content -LiteralPath $packageJsonPath -Raw | ConvertFrom-Json
    if ($null -ne $package.scripts -and $null -ne $package.scripts.build) {
        return 'build'
    }

    return $null
}

function Assert-WriteProbe {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string] $RelativePath
    )

    $directory = Join-Path $Repo $RelativePath
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }

    $probe = Join-Path $directory ('.ai-write-probe-' + [guid]::NewGuid().ToString() + '.tmp')
    try {
        Set-Content -LiteralPath $probe -Value 'probe' -Encoding UTF8
        Remove-Item -LiteralPath $probe -Force
    } catch {
        if (Test-Path -LiteralPath $probe) {
            Remove-Item -LiteralPath $probe -Force -ErrorAction SilentlyContinue
        }
        throw "Runtime directory is not writable: $directory. $($_.Exception.Message)"
    }
}

Write-Output "Bootstrapping worktree: $worktree"

if (Test-Path -LiteralPath (Join-Path $worktree 'composer.lock') -PathType Leaf) {
    if (-not (Test-Path -LiteralPath (Join-Path $worktree 'vendor/autoload.php') -PathType Leaf)) {
        Write-Output 'Installing Composer dependencies with composer install --no-interaction --prefer-dist'
        Invoke-InWorktree -FilePath 'composer' -Arguments @('install', '--no-interaction', '--prefer-dist') -DisplayName 'composer'
        Assert-TrackedFilesUnchanged -Repo $worktree
    } else {
        Write-Output 'Composer dependencies already present.'
    }
}

$envPath = Join-Path $worktree '.env'
if (Test-Path -LiteralPath $envPath -PathType Leaf) {
    Write-Output 'Local .env already exists; leaving it unchanged.'
} else {
    if (-not (Test-Path -LiteralPath (Join-Path $worktree '.env.example') -PathType Leaf)) {
        throw 'Cannot create .env because .env.example is missing.'
    }

    if (-not (Test-GitIgnored -Repo $worktree -RelativePath '.env')) {
        throw '.env is not ignored by Git. Refusing to create a local environment file.'
    }

    Copy-Item -LiteralPath (Join-Path $worktree '.env.example') -Destination $envPath
    Write-Output 'Created local ignored .env from .env.example.'
    Invoke-InWorktree -FilePath 'php' -Arguments @('artisan', 'key:generate', '--force', '--no-interaction') -DisplayName 'php artisan' | Out-Null
    Write-Output 'Generated a new worktree-local APP_KEY.'
}

if (-not (Test-GitIgnored -Repo $worktree -RelativePath '.env')) {
    throw '.env is not ignored by Git. Refusing to continue.'
}

if (Test-Path -LiteralPath (Join-Path $worktree 'phpunit.xml') -PathType Leaf) {
    $phpunit = Get-Content -LiteralPath (Join-Path $worktree 'phpunit.xml') -Raw
    if ($phpunit -match 'name="DB_CONNECTION"\s+value="sqlite"' -and $phpunit -match 'name="DB_DATABASE"\s+value=":memory:"') {
        Write-Output 'Test database is configured for SQLite :memory: in phpunit.xml; leaving DB config unchanged.'
    } else {
        Write-Output 'phpunit.xml does not declare SQLite :memory: test DB; leaving DB config unchanged and not copying credentials.'
    }
}

foreach ($relativePath in @('storage', 'storage/logs', 'storage/framework', 'bootstrap/cache')) {
    Assert-WriteProbe -Repo $worktree -RelativePath $relativePath
}
Write-Output 'Runtime directories are writable.'

if (Test-Path -LiteralPath (Join-Path $worktree 'package.json') -PathType Leaf) {
    $packageManager = Get-PackageManagerCommand -Repo $worktree
    if ($null -eq $packageManager) {
        throw 'package.json exists but no supported lockfile was found for deterministic install.'
    }

    if (-not (Test-Path -LiteralPath (Join-Path $worktree 'node_modules') -PathType Container)) {
        Write-Output "Installing Node dependencies with $($packageManager.Name)."
        Invoke-InWorktree -FilePath $packageManager.FilePath -Arguments $packageManager.Arguments -DisplayName $packageManager.Name
        Assert-TrackedFilesUnchanged -Repo $worktree
    } else {
        Write-Output 'Node dependencies already present.'
    }

    $buildScript = Get-PackageBuildScript -Repo $worktree
    if ($null -ne $buildScript -and -not (Test-Path -LiteralPath (Join-Path $worktree 'public/build/manifest.json') -PathType Leaf)) {
        if (-not (Test-GitIgnored -Repo $worktree -RelativePath 'public/build')) {
            throw 'public/build is not ignored by Git. Refusing to create frontend build artifacts.'
        }

        Write-Output "Generating frontend build with npm run $buildScript."
        Invoke-InWorktree -FilePath 'npm.cmd' -Arguments @('run', $buildScript) -DisplayName 'npm'
        Assert-TrackedFilesUnchanged -Repo $worktree
    } elseif ($null -ne $buildScript) {
        Write-Output 'Frontend build manifest already present.'
    }
}

Invoke-InWorktree -FilePath 'php' -Arguments @('artisan', '--version') -DisplayName 'php artisan' | ForEach-Object { Write-Output $_ }
Invoke-InWorktree -FilePath 'php' -Arguments @('artisan', 'about') -DisplayName 'php artisan' | ForEach-Object { Write-Output $_ }

Assert-TrackedFilesUnchanged -Repo $worktree
$environment = Get-WorktreeEnvironmentStatus -Repo $worktree

Write-Output "Environment: $($environment.Status)"
if ($environment.Missing.Count -gt 0) {
    Write-Output "Missing: $($environment.Missing -join ', ')"
}
Write-Output 'Bootstrap complete.'

