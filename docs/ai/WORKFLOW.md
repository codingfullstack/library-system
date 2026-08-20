# Codex + Claude Workflow

This workflow keeps AI infrastructure separate from feature work and keeps review snapshots reproducible.

## Workspaces

- Human workspace: `C:/xampp/htdocs/library-system`
- Codex workspace: `../library-system-codex`
- Claude review workspace: `../library-system-claude-review`

The human workspace is the coordination point. Codex implements in its own worktree. Claude reviews a detached commit snapshot in its own worktree.

## Branch Rules

- AI workflow infrastructure lives on `chore/ai-agent-workflow`.
- Feature implementation branches should be created in the Codex worktree.
- Claude must review detached HEAD at one reviewed commit SHA.
- Codex and Claude must never check out the same branch at the same time.
- No script pushes, merges, force-pushes, resets, stashes, cleans, or deletes worktrees.

## Commands

Run with Windows PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-status.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-start-task.ps1 -BranchName feature/my-task
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-prepare-review.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-collect-review.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-finish-task.ps1 -ManualTestingComplete
```

## Normal Flow

1. Human starts from a clean human workspace.
2. `ai-start-task` creates or verifies `../library-system-codex` on a task branch based on the default branch.
3. Codex implements, tests, and commits the feature branch.
4. Codex writes test results to `.ai/runtime/TEST_RESULTS.md` in the Codex workspace.
5. `ai-prepare-review` creates `.ai/runtime/HANDOFF.md`, computes the base and reviewed commit, and prepares `../library-system-claude-review` as detached HEAD at the reviewed commit.
6. Claude reviews the detached snapshot and writes `.ai/runtime/CLAUDE_REVIEW.md`.
7. `ai-collect-review` validates the review header, rejects stale `Reviewed-Commit` values that do not match the Codex HEAD, and copies the review into the Codex runtime directory.
8. If Claude requests changes, Codex fixes only accepted findings and prepares another review.
9. If Claude approves, tests pass, and manual testing is explicitly marked complete, the human decides whether to push or merge.

## Handoff Contract

The handoff must include:

- reviewed commit SHA
- base commit SHA
- git diff
- test results
- `.ai/runtime/HANDOFF.md`

Runtime handoff and review files are ignored by Git and must not be committed.

## Git Ownership In Sandboxed Runs

The workflow scripts route all Git commands through a shared wrapper that adds per-command trust for the exact worktree being accessed:

```text
git -c safe.directory=<absolute-worktree-path> -C <absolute-worktree-path> ...
```

This is intentionally scoped to one concrete path at a time. The workflow does not require `safe.directory=*` and does not write global, system, or repository Git trust configuration.
