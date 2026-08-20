Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-Git {
    param(
        [Parameter(Mandatory = $true)][string] $Repo,
        [Parameter(Mandatory = $true)][string[]] $Arguments
    )

    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & git -C $Repo @Arguments 2>&1
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($LASTEXITCODE -ne 0) {
        throw "git $($Arguments -join ' ') failed in ${Repo}: $output"
    }

    return @($output)
}

function Get-RepoRoot {
    param([string] $Path = '')

    if ([string]::IsNullOrWhiteSpace($Path)) {
        $Path = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
    }

    $root = @(Invoke-Git -Repo $Path -Arguments @('rev-parse', '--show-toplevel'))
    return [string] $root[0]
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

    $parent = Split-Path -Parent $Repo

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
