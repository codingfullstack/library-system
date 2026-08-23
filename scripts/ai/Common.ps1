Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-AbsolutePath {
    param([Parameter(Mandatory = $true)][string] $Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        throw 'Path cannot be empty.'
    }

    return [IO.Path]::GetFullPath($Path)
}

function Resolve-RepoRelativePath {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string] $Path
    )

    if ([IO.Path]::IsPathRooted($Path)) {
        return Resolve-AbsolutePath -Path $Path
    }

    return Resolve-AbsolutePath -Path (Join-Path (Resolve-AbsolutePath -Path $Repo) $Path)
}
function Invoke-Git {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string[]] $Arguments
    )

    $safeDirectory = Resolve-AbsolutePath -Path $Repo
    $safeDirectoryConfig = $safeDirectory -replace '\\', '/'
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & git -c "safe.directory=$safeDirectoryConfig" -C $safeDirectory @Arguments 2>&1
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($LASTEXITCODE -ne 0) {
        throw "git $($Arguments -join ' ') failed in ${safeDirectory}: $output"
    }

    return @($output)
}

function Invoke-ExternalCommand {
    param(
        [Parameter(Mandatory = $true)][string] $WorkingDirectory,
        [Parameter(Mandatory = $true)][string] $FilePath,
        [Parameter(Mandatory = $true)][string[]] $Arguments,
        [string] $DisplayName = ''
    )

    $directory = Resolve-AbsolutePath -Path $WorkingDirectory
    if ([string]::IsNullOrWhiteSpace($DisplayName)) {
        $DisplayName = $FilePath
    }

    $previousLocation = Get-Location
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        Set-Location -LiteralPath $directory
        $output = & $FilePath @Arguments 2>&1
    } finally {
        $exitCode = $LASTEXITCODE
        $ErrorActionPreference = $previousErrorActionPreference
        Set-Location -LiteralPath $previousLocation
    }

    if ($exitCode -ne 0) {
        throw "$DisplayName $($Arguments -join ' ') failed in ${directory}: $output"
    }

    return @($output)
}

function Get-RepoRoot {
    param([string] $Path = '')

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return Resolve-AbsolutePath -Path (Join-Path $PSScriptRoot '../..')
    }

    $root = @(Invoke-Git -Repo (Resolve-AbsolutePath -Path $Path) -Arguments @('rev-parse', '--show-toplevel'))
    return [string] $root[0]
}

function Assert-RepositoryWorktree {
    param(
        [Parameter(Mandatory = $true)][string] $CoordinationRepo,
        [Parameter(Mandatory = $true)][string] $WorktreePath
    )

    $target = Resolve-AbsolutePath -Path $WorktreePath
    if (-not (Test-Path -LiteralPath $target -PathType Container)) {
        throw "Worktree path does not exist: $target"
    }

    $targetCommonDir = @(Invoke-Git -Repo $target -Arguments @('rev-parse', '--git-common-dir'))[0]
    $repoCommonDir = @(Invoke-Git -Repo $CoordinationRepo -Arguments @('rev-parse', '--git-common-dir'))[0]

    $targetCommonFull = Resolve-RepoRelativePath -Repo $target -Path $targetCommonDir
    $repoCommonFull = Resolve-RepoRelativePath -Repo $CoordinationRepo -Path $repoCommonDir

    if ($targetCommonFull -ne $repoCommonFull) {
        throw "Path is not a worktree of this repository: $target"
    }

    return $target
}

function Get-CurrentBranch {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $branch = @(Invoke-Git -Repo $Repo -Arguments @('branch', '--show-current'))
    if ([string]::IsNullOrWhiteSpace($branch)) {
        return 'DETACHED'
    }

    return [string] $branch[0]
}

function Get-HeadSha {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $sha = @(Invoke-Git -Repo $Repo -Arguments @('rev-parse', 'HEAD'))
    return [string] $sha[0]
}

function Resolve-CommitSha {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string] $Ref
    )

    $sha = @(Invoke-Git -Repo $Repo -Arguments @('rev-parse', '--verify', "$Ref^{commit}"))
    return [string] $sha[0]
}

function Assert-CleanGit {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $status = @(Invoke-Git -Repo $Repo -Arguments @('status', '--porcelain=v1'))
    if ($status.Count -gt 0) {
        throw "Worktree is not clean: $Repo`n$($status -join [Environment]::NewLine)"
    }
}

function Get-DefaultBranchName {
    param([Parameter(Mandatory = $true)][string] $Repo)

    try {
        $ref = @(Invoke-Git -Repo $Repo -Arguments @('symbolic-ref', '--quiet', '--short', 'refs/remotes/origin/HEAD'))
        if ($ref.Count -gt 0 -and $ref[0] -match '^origin/(.+)$') {
            return $Matches[1]
        }
    } catch {
        # Fall through to common remote branch names.
    }

    foreach ($candidate in @('main', 'master')) {
        try {
            Invoke-Git -Repo $Repo -Arguments @('show-ref', '--verify', '--quiet', "refs/remotes/origin/$candidate") | Out-Null
            return $candidate
        } catch {
            try {
                Invoke-Git -Repo $Repo -Arguments @('show-ref', '--verify', '--quiet', "refs/heads/$candidate") | Out-Null
                return $candidate
            } catch {
            }
        }
    }

    throw 'Could not determine the default branch. Configure origin/HEAD or pass an explicit base ref.'
}

function Get-WorkflowPaths {
    param([string] $Repo = (Get-RepoRoot))

    $repoRoot = Resolve-AbsolutePath -Path $Repo
    $parent = Split-Path -Parent $repoRoot

    return [pscustomobject] @{
        Human = Join-Path $parent 'library-system'
        Codex = Join-Path $parent 'library-system-codex'
        Claude = Join-Path $parent 'library-system-claude-review'
    }
}

function Test-BranchCheckedOutElsewhere {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string] $BranchName,
        [string] $AllowedPath = ''
    )

    $lines = @(Invoke-Git -Repo $Repo -Arguments @('worktree', 'list', '--porcelain'))
    $currentPath = ''
    foreach ($line in $lines) {
        if ($line -match '^worktree (.+)$') {
            $currentPath = [IO.Path]::GetFullPath($Matches[1])
            continue
        }

        if ($line -eq "branch refs/heads/$BranchName") {
            if ([string]::IsNullOrWhiteSpace($AllowedPath)) {
                return $true
            }

            $allowed = [IO.Path]::GetFullPath($AllowedPath)
            if ($currentPath -ne $allowed) {
                return $true
            }
        }
    }

    return $false
}

function Ensure-RuntimeDirectory {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $runtime = Join-Path $Repo '.ai/runtime'
    New-Item -ItemType Directory -Force -Path $runtime | Out-Null
    return $runtime
}

function Write-WorkflowState {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][hashtable] $State
    )

    $runtime = Ensure-RuntimeDirectory -Repo $Repo
    $statePath = Join-Path $runtime 'STATE.json'
    $State | ConvertTo-Json -Depth 5 | Set-Content -Path $statePath -Encoding UTF8

    return $statePath
}

function Test-GitIgnored {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string] $RelativePath
    )

    try {
        Invoke-Git -Repo $Repo -Arguments @('check-ignore', '--quiet', $RelativePath) | Out-Null
        return $true
    } catch {
        return $false
    }
}

function Test-LaravelBootReady {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $artisan = Join-Path $Repo 'artisan'
    if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) {
        return $false
    }

    $previousLocation = Get-Location
    try {
        Set-Location -LiteralPath $Repo
        Invoke-ExternalCommand -WorkingDirectory $Repo -FilePath 'php' -Arguments @('artisan', '--version') -DisplayName 'php artisan' | Out-Null
        return $true
    } catch {
        return $false
    } finally {
        Set-Location -LiteralPath $previousLocation
    }
}

function Get-WorktreeEnvironmentStatus {
    param([Parameter(Mandatory = $true)][string] $Repo)

    $repoPath = Resolve-AbsolutePath -Path $Repo
    $signals = @()
    $missing = @()

    if (Test-Path -LiteralPath (Join-Path $repoPath 'composer.lock')) {
        if (Test-Path -LiteralPath (Join-Path $repoPath 'vendor/autoload.php') -PathType Leaf) {
            $signals += 'vendor/autoload.php'
        } else {
            $missing += 'vendor/autoload.php'
        }
    }

    if (Test-Path -LiteralPath (Join-Path $repoPath '.env') -PathType Leaf) {
        $signals += '.env'
    } elseif (Test-Path -LiteralPath (Join-Path $repoPath '.env.example') -PathType Leaf) {
        $missing += '.env'
    }

    if (Test-Path -LiteralPath (Join-Path $repoPath 'package.json') -PathType Leaf) {
        if (Test-Path -LiteralPath (Join-Path $repoPath 'node_modules') -PathType Container) {
            $signals += 'node_modules'
        } else {
            $missing += 'node_modules'
        }

        if (Test-Path -LiteralPath (Join-Path $repoPath 'public/build/manifest.json') -PathType Leaf) {
            $signals += 'public/build/manifest.json'
        } else {
            $missing += 'public/build/manifest.json'
        }
    }

    $bootReady = Test-LaravelBootReady -Repo $repoPath
    if ($bootReady) {
        $signals += 'laravel-boot'
    } else {
        $missing += 'laravel-boot'
    }

    if ($missing.Count -eq 0 -and $signals.Count -gt 0) {
        $status = 'READY'
    } elseif ($signals.Count -gt 0 -or $missing.Count -gt 0) {
        $status = 'NOT_READY'
    } else {
        $status = 'UNKNOWN'
    }

    return [pscustomobject] @{
        Status = $status
        Present = $signals
        Missing = $missing
    }
}



