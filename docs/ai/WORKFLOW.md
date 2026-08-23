# Codex + Claude Workflow

This workflow keeps AI infrastructure separate from feature work and keeps review snapshots reproducible.

## Workspaces

- Human workspace: `C:/xampp/htdocs/library-system`
- Codex workspace: `../library-system-codex`
- Claude review workspace: `../library-system-claude-review`

The human workspace is the coordination point. Codex implements in its own worktree. Claude reviews a detached commit snapshot in its own worktree.

## Branch Rules

- AI workflow infrastructure lives on `chore/ai-agent-workflow` until it is integrated into `main`.
- Feature implementation branches should be created in the Codex worktree.
- Claude must review detached HEAD at one reviewed commit SHA.
- Codex and Claude must never check out the same branch at the same time.
- No script pushes, merges, force-pushes, resets, stashes, cleans, deletes branches, or deletes worktrees.

## Commands

Run with Windows PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-status.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-start-task.ps1 -BranchName feature/my-task
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-bootstrap-worktree.ps1 -WorktreePath ../library-system-codex
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-prepare-review.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-collect-review.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-finish-task.ps1 -ManualTestingComplete
```

## Normal Flow

1. Human starts from a clean human workspace.
2. `ai-start-task` creates or verifies `../library-system-codex` on a task branch. When `-BaseRef` is omitted, the coordination worktree must be clean and checked out on the repository default branch; the task branch is created from that local default-branch `HEAD`, not from `origin/main`.
3. After creating a new Codex worktree, `ai-start-task` runs `ai-bootstrap-worktree` for that worktree. The task is READY only after bootstrap succeeds.
4. If bootstrap fails, the task branch and worktree are left intact. The state file records `bootstrap_status: NOT_READY`; no destructive cleanup is performed.
5. Codex implements, tests, and commits the feature branch.
6. Codex writes test results to `.ai/runtime/TEST_RESULTS.md` in the Codex workspace.
7. `ai-prepare-review` creates `.ai/runtime/HANDOFF.md`, computes the base and reviewed commit, and prepares `../library-system-claude-review` as detached HEAD at the reviewed commit.
8. Claude reviews the detached snapshot and writes `.ai/runtime/CLAUDE_REVIEW.md`.
9. `ai-collect-review` validates the review header, rejects stale `Reviewed-Commit` values that do not match the Codex HEAD, and copies the review into the Codex runtime directory.
10. If Claude requests changes, Codex fixes only accepted findings and prepares another review.
11. If Claude approves, tests pass, and manual testing is explicitly marked complete, the human decides whether to push or merge.

## Codex Worktree Bootstrap

`ai-bootstrap-worktree` prepares local ignored runtime artifacts for a Codex worktree:

- Composer dependencies: if `composer.lock` exists and `vendor/autoload.php` is missing, it runs `composer install --no-interaction --prefer-dist`. It never runs `composer update`.
- Environment file: if `.env` is missing, it copies `.env.example` into a local ignored `.env` and generates a new worktree-specific `APP_KEY`. It never copies `.env` or secrets from another worktree, never prints secret values, and never overwrites an existing `.env`.
- Test database: if `phpunit.xml` already sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, bootstrap leaves DB configuration unchanged. It does not copy local or production database credentials.
- Node dependencies: it chooses a deterministic install from the lockfile. `package-lock.json` uses `npm ci`; `pnpm-lock.yaml` uses `pnpm install --frozen-lockfile`; `yarn.lock` uses `yarn install --frozen-lockfile`.
- Frontend build: if `package.json` has a `build` script and `public/build/manifest.json` is missing, it runs the repository build command. It requires `public/build` to be ignored before creating build artifacts.
- Runtime writes: it probes `storage/`, `storage/logs/`, `storage/framework/`, and `bootstrap/cache/` without changing global ACLs.
- Boot check: it runs `php artisan --version` and `php artisan about`.

Bootstrap is idempotent. A second run does not regenerate `APP_KEY` when `.env` already exists, does not overwrite local environment files, and fails if tracked files change.

Claude review worktrees are not automatically bootstrapped with Composer or Node dependencies because Claude's default role is source and diff review. Test execution in a Claude worktree is a separate explicit decision.

## Handoff Contract

The handoff must include:

- reviewed commit SHA
- base commit SHA
- git diff
- test results
- `.ai/runtime/HANDOFF.md`

Runtime handoff and review files are ignored by Git and must not be committed.

## Task Base Selection

Default task base is the clean local default-branch `HEAD`. This allows local, reviewed workflow commits to be used before they are pushed.

`origin/main` is not used automatically as the task base. To start from any other commit or ref, pass `-BaseRef` explicitly:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/ai/ai-start-task.ps1 -BranchName feature/my-task -BaseRef origin/main
```

`ai-start-task` writes `.ai/runtime/STATE.json` in the Codex worktree with `base_ref`, immutable `base_sha`, and bootstrap status.

## Environment Status

`ai-status` reports the Codex worktree environment as `READY`, `NOT_READY`, or `UNKNOWN` using non-secret signals such as local dependency artifacts, `.env` presence, Vite manifest presence, and Laravel boot capability. It must not print `.env` values.

## Git Ownership In Sandboxed Runs

The workflow scripts route all Git commands through a shared wrapper that adds per-command trust for the exact worktree being accessed:

```text
git -c safe.directory=<absolute-worktree-path> -C <absolute-worktree-path> ...
```

This is intentionally scoped to one concrete path at a time. The workflow does not require `safe.directory=*` and does not write global, system, or repository Git trust configuration.
