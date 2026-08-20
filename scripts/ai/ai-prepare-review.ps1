[CmdletBinding()]
param(
    [string] $CodexPath = '',
    [string] $ClaudePath = '',
    [string] $ReviewCommit = 'HEAD',
    [string] $BaseCommit = ''
)

. "$PSScriptRoot/Common.ps1"

$repo = Get-RepoRoot
$paths = Get-WorkflowPaths -Repo $repo
if ([string]::IsNullOrWhiteSpace($CodexPath)) {
    $CodexPath = $paths.Codex
}
if ([string]::IsNullOrWhiteSpace($ClaudePath)) {
    $ClaudePath = $paths.Claude
}

if (-not (Test-Path $CodexPath)) {
    throw "Codex workspace does not exist: $CodexPath"
}

Assert-CleanGit -Repo $CodexPath

$reviewSha = @(Invoke-Git -Repo $CodexPath -Arguments @('rev-parse', "$ReviewCommit^{commit}"))[0]

if ([string]::IsNullOrWhiteSpace($BaseCommit)) {
    $defaultBranch = Get-DefaultBranchName -Repo $CodexPath
    $BaseCommit = @(Invoke-Git -Repo $CodexPath -Arguments @('merge-base', $reviewSha, "origin/$defaultBranch"))[0]
} else {
    $BaseCommit = @(Invoke-Git -Repo $CodexPath -Arguments @('rev-parse', "$BaseCommit^{commit}"))[0]
}

$runtime = Ensure-RuntimeDirectory -Repo $CodexPath
$handoff = Join-Path $runtime 'HANDOFF.md'
$testResults = Join-Path $runtime 'TEST_RESULTS.md'
$collectedReview = Join-Path $runtime 'CLAUDE_REVIEW.md'

if (Test-Path $collectedReview) {
    Remove-Item -LiteralPath $collectedReview -Force
}

if (Test-Path $testResults) {
    $tests = Get-Content -Raw $testResults
} else {
    $tests = 'NOT PROVIDED'
}

$stat = @(Invoke-Git -Repo $CodexPath -Arguments @('diff', '--stat', $BaseCommit, $reviewSha))
$diff = @(Invoke-Git -Repo $CodexPath -Arguments @('diff', $BaseCommit, $reviewSha))

$handoffContent = @(
    '# Codex To Claude Handoff',
    '',
    "Reviewed-Commit: $reviewSha",
    "Base-Commit: $BaseCommit",
    '',
    '## Test Results',
    '',
    '~~~text',
    $tests,
    '~~~',
    '',
    '## Diff Stat',
    '',
    '~~~text',
    ($stat -join [Environment]::NewLine),
    '~~~',
    '',
    '## Git Diff',
    '',
    '~~~diff',
    ($diff -join [Environment]::NewLine),
    '~~~'
) -join [Environment]::NewLine

Set-Content -Path $handoff -Value $handoffContent -Encoding UTF8

if (Test-Path $ClaudePath) {
    Assert-CleanGit -Repo $ClaudePath
    $claudeBranch = Get-CurrentBranch -Repo $ClaudePath
    if ($claudeBranch -ne 'DETACHED') {
        throw "Claude workspace exists but is not detached: $ClaudePath ($claudeBranch)"
    }

    Invoke-Git -Repo $ClaudePath -Arguments @('switch', '--detach', $reviewSha) | Out-Null
} else {
    Invoke-Git -Repo $CodexPath -Arguments @('worktree', 'add', '--detach', $ClaudePath, $reviewSha) | Out-Null
}

$claudeRuntime = Ensure-RuntimeDirectory -Repo $ClaudePath
$staleClaudeReview = Join-Path $claudeRuntime 'CLAUDE_REVIEW.md'
if (Test-Path $staleClaudeReview) {
    Remove-Item -LiteralPath $staleClaudeReview -Force
}

Copy-Item -LiteralPath $handoff -Destination (Join-Path $claudeRuntime 'HANDOFF.md') -Force

Write-Output "Reviewed-Commit: $reviewSha"
Write-Output "Base-Commit: $BaseCommit"
Write-Output "HANDOFF: $handoff"
Write-Output "Claude workspace: $ClaudePath"
