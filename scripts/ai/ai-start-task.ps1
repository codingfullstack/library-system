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
    $currentBranch = Get-CurrentBranch -Repo $repo

    if ($currentBranch -ne $defaultBranch) {
        throw "Cannot choose default task base from branch '$currentBranch'. Repository default branch is '$defaultBranch'. Switch the coordination worktree to '$defaultBranch' or pass -BaseRef explicitly."
    }

    $baseSha = Get-HeadSha -Repo $repo
    $BaseRef = $baseSha
} else {
    $baseSha = Resolve-CommitSha -Repo $repo -Ref $BaseRef
}

$baseSha = Resolve-CommitSha -Repo $repo -Ref $BaseRef

if (Test-BranchCheckedOutElsewhere -Repo $repo -BranchName $BranchName -AllowedPath $CodexPath) {
    throw "Branch '$BranchName' is already checked out in another worktree."
}

if (Test-Path $CodexPath) {
    Assert-CleanGit -Repo $CodexPath
    $currentBranch = Get-CurrentBranch -Repo $CodexPath
    if ($currentBranch -ne $BranchName) {
        throw "Codex workspace already exists at $CodexPath on branch '$currentBranch', expected '$BranchName'."
    }

    Write-Output "Codex workspace already exists: $CodexPath"
    $environment = Get-WorktreeEnvironmentStatus -Repo $CodexPath
    Write-Output "Environment: $($environment.Status)"
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

$state = @{
    branch = $BranchName
    base_ref = $BaseRef
    base_sha = $baseSha
    bootstrap_status = 'PENDING'
}
$statePath = Write-WorkflowState -Repo $CodexPath -State $state

try {
    & (Join-Path $PSScriptRoot 'ai-bootstrap-worktree.ps1') -WorktreePath $CodexPath
    $state.bootstrap_status = 'READY'
    $statePath = Write-WorkflowState -Repo $CodexPath -State $state
} catch {
    $state.bootstrap_status = 'NOT_READY'
    $state.bootstrap_error = $_.Exception.Message
    $statePath = Write-WorkflowState -Repo $CodexPath -State $state
    Write-Output 'TASK CREATED / BOOTSTRAP FAILED'
    Write-Output "Codex workspace: $CodexPath"
    Write-Output "Branch: $BranchName"
    Write-Output "Base: $BaseRef"
    Write-Output "Base SHA: $baseSha"
    Write-Output "State: $statePath"
    throw "Bootstrap failed. The task branch and worktree were left intact: $($_.Exception.Message)"
}

Write-Output "Codex workspace: $CodexPath"
Write-Output "Branch: $BranchName"
Write-Output "Base: $BaseRef"
Write-Output "Base SHA: $baseSha"
Write-Output "State: $statePath"
Write-Output 'Environment: READY'
