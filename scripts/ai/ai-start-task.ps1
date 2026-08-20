[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][ValidatePattern('^feature/[A-Za-z0-9._/-]+$')][string] $BranchName,
    [string] $BaseRef = '',
    [string] $CodexPath = ''
)

. "$PSScriptRoot/Common.ps1"

$repo = Get-RepoRoot
Assert-CleanGit -Repo $repo

$paths = Get-WorkflowPaths -Repo $repo
if ([string]::IsNullOrWhiteSpace($CodexPath)) {
    $CodexPath = $paths.Codex
}

if ([string]::IsNullOrWhiteSpace($BaseRef)) {
    $defaultBranch = Get-DefaultBranchName -Repo $repo
    $BaseRef = "origin/$defaultBranch"
}

Invoke-Git -Repo $repo -Arguments @('rev-parse', '--verify', "$BaseRef^{commit}") | Out-Null

if (Test-BranchCheckedOutElsewhere -Repo $repo -BranchName $BranchName -AllowedPath $CodexPath) {
    throw "Branch '$BranchName' is already checked out in another worktree."
}

if (Test-Path $CodexPath) {
    Assert-CleanGit -Repo $CodexPath
    $currentBranch = Get-CurrentBranch -Repo $CodexPath
    if ($currentBranch -ne $BranchName) {
        throw "Codex workspace already exists at $CodexPath on branch '$currentBranch', expected '$BranchName'."
    }

    Write-Output "Codex workspace already ready: $CodexPath"
    exit 0
}

$branchExists = $true
try {
    Invoke-Git -Repo $repo -Arguments @('show-ref', '--verify', '--quiet', "refs/heads/$BranchName") | Out-Null
} catch {
    $branchExists = $false
}

if ($branchExists) {
    throw "Branch '$BranchName' already exists. Choose a new feature/<task> branch or use the existing Codex workspace if it is already checked out there."
} else {
    Invoke-Git -Repo $repo -Arguments @('worktree', 'add', '-b', $BranchName, $CodexPath, $BaseRef) | Out-Null
}

Write-Output "Codex workspace: $CodexPath"
Write-Output "Branch: $BranchName"
Write-Output "Base: $BaseRef"
