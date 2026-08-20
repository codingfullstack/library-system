# AI Agent Instructions

This repository uses a human + Codex + Claude workflow with separate Git worktrees.

## Roles

Codex is the implementing agent. Codex may change source, tests, documentation, and migrations when the task requires it. Codex must run relevant tests before proposing a commit, must keep changes scoped, and must not push, merge, force-push, reset, stash, clean, or rewrite history unless the human explicitly asks for that operation.

Claude is the reviewer and auditor. Claude reviews one concrete commit snapshot from a detached HEAD worktree. Claude must not edit production source files. Claude writes findings to `.ai/runtime/CLAUDE_REVIEW.md`.

## Worktrees

- Human workspace: `C:/xampp/htdocs/library-system`
- Codex workspace: `../library-system-codex`
- Claude review workspace: `../library-system-claude-review`

Codex and Claude must not check out the same branch at the same time. Claude review worktrees must stay detached at the reviewed commit SHA.

## Safety Rules

- Do not use `git add .`.
- Do not commit runtime files from `.ai/runtime/`.
- Do not commit local screenshots or manual testing artifacts unless the human explicitly approves them.
- Do not ignore failing tests.
- Do not weaken authorization, tenant isolation, database invariants, or API contracts to make tests pass.
- Treat multi-library, membership, reservation, loan, and branch logic as high-risk.

## Commands

PowerShell workflow scripts live in `scripts/ai/`:

- `ai-status.ps1`
- `ai-start-task.ps1`
- `ai-prepare-review.ps1`
- `ai-collect-review.ps1`
- `ai-finish-task.ps1`

Run scripts from a clean worktree unless the script explicitly states otherwise.
Task branches created by `ai-start-task.ps1` must use the `feature/<task>` prefix.
