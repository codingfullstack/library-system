[CmdletBinding()]
param(
    [string] $CodexPath = '',
    [switch] $ManualTestingComplete
)

. "$PSScriptRoot/Common.ps1"

$repo = Get-RepoRoot
$paths = Get-WorkflowPaths -Repo $repo
if ([string]::IsNullOrWhiteSpace($CodexPath)) {
    $CodexPath = $paths.Codex
}

Assert-CleanGit -Repo $CodexPath

$reviewPath = Join-Path $CodexPath '.ai/runtime/CLAUDE_REVIEW.md'
if (-not (Test-Path $reviewPath)) {
    throw "No collected Claude review found: $reviewPath"
}

$review = Get-Content -Raw $reviewPath
if ($review -notmatch '(?m)^Reviewed-Commit:\s*([0-9a-fA-F]{40})\s*$') {
    throw 'Collected Claude review must contain a valid Reviewed-Commit SHA.'
}

$reviewedCommit = $Matches[1].ToLowerInvariant()
$codexHead = (Get-HeadSha -Repo $CodexPath).ToLowerInvariant()
if ($reviewedCommit -ne $codexHead) {
    throw "Task cannot be finished: Claude review is stale for $reviewedCommit, current Codex HEAD is $codexHead."
}

if ($review -notmatch 'Verdict:\s*APPROVE') {
    throw 'Task cannot be finished: Claude review is not APPROVE.'
}

if ($review -match '(?m)^\s*(Severity:\s*)?P[01]\b') {
    throw 'Task cannot be finished: collected Claude review still contains P0/P1 findings.'
}

if (-not $ManualTestingComplete) {
    throw 'Task cannot be finished until -ManualTestingComplete is provided.'
}

Write-Output "Task is ready for human final review."
Write-Output "Codex workspace: $CodexPath"
Write-Output "Branch: $(Get-CurrentBranch -Repo $CodexPath)"
Write-Output "HEAD: $(Get-HeadSha -Repo $CodexPath)"
Write-Output 'No push or merge was performed.'
