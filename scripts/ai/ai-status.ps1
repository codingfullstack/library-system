[CmdletBinding()]
param()

. "$PSScriptRoot/Common.ps1"

$repo = Get-RepoRoot
$paths = Get-WorkflowPaths -Repo $repo
$defaultBranch = Get-DefaultBranchName -Repo $repo

Write-Output "Repository: $repo"
Write-Output "Current branch: $(Get-CurrentBranch -Repo $repo)"
Write-Output "HEAD: $(Get-HeadSha -Repo $repo)"
Write-Output "Default branch: $defaultBranch"
Write-Output ''
Write-Output 'Current status:'
Invoke-Git -Repo $repo -Arguments @('status', '--short') | ForEach-Object { Write-Output $_ }
Write-Output ''
Write-Output 'Worktrees:'
Invoke-Git -Repo $repo -Arguments @('worktree', 'list') | ForEach-Object { Write-Output $_ }
Write-Output ''
Write-Output "Human workspace: $($paths.Human)"
Write-Output "Codex workspace: $($paths.Codex)"
Write-Output "Claude workspace: $($paths.Claude)"

foreach ($path in @($paths.Human, $paths.Codex, $paths.Claude)) {
    if (Test-Path $path) {
        Write-Output ''
        Write-Output "Status for ${path}:"
        Invoke-Git -Repo $path -Arguments @('status', '--short', '--branch') | ForEach-Object { Write-Output $_ }
    }
}
