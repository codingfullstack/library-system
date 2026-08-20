[CmdletBinding()]
param(
    [string] $CodexPath = '',
    [string] $ClaudePath = ''
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

$reviewPath = Join-Path $ClaudePath '.ai/runtime/CLAUDE_REVIEW.md'
if (-not (Test-Path $reviewPath)) {
    throw "Claude review not found: $reviewPath"
}

$review = Get-Content -Raw $reviewPath
foreach ($required in @('Reviewed-Commit:', 'Base-Commit:', 'Verdict:')) {
    if ($review -notmatch [regex]::Escape($required)) {
        throw "Claude review is missing required header: $required"
    }
}

if ($review -notmatch 'Verdict:\s*(APPROVE|CHANGES_REQUESTED|BLOCKED)') {
    throw 'Claude review verdict must be APPROVE, CHANGES_REQUESTED, or BLOCKED.'
}

if ($review -notmatch '(?m)^Reviewed-Commit:\s*([0-9a-fA-F]{40})\s*$') {
    throw 'Claude review must contain a valid Reviewed-Commit SHA.'
}
$reviewedCommit = $Matches[1].ToLowerInvariant()
$codexHead = (Get-HeadSha -Repo $CodexPath).ToLowerInvariant()
if ($reviewedCommit -ne $codexHead) {
    throw "Stale Claude review: Reviewed-Commit $reviewedCommit does not match Codex HEAD $codexHead."
}

$runtime = Ensure-RuntimeDirectory -Repo $CodexPath
$destination = Join-Path $runtime 'CLAUDE_REVIEW.md'
Copy-Item -LiteralPath $reviewPath -Destination $destination -Force

Write-Output "Collected review: $destination"
Write-Output $Matches[0]
