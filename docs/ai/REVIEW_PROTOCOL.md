# Claude Review Protocol

Claude reviews one immutable commit snapshot.

## Inputs

Claude must receive:

- `.ai/runtime/HANDOFF.md`
- reviewed commit SHA
- base commit SHA
- git diff from base to reviewed commit
- test results produced by Codex

## Output Location

Claude writes:

```text
.ai/runtime/CLAUDE_REVIEW.md
```

Runtime files are local and ignored by Git.

## Required Header

```text
Reviewed-Commit: <sha>
Base-Commit: <sha>
Verdict: APPROVE | CHANGES_REQUESTED | BLOCKED
```

## Severity Levels

- `P0`: release blocker, data loss, security break, severe tenant leak
- `P1`: serious correctness, authorization, data integrity, or API regression
- `P2`: moderate bug, missing important edge case, or meaningful maintainability risk
- `P3`: minor issue, clarity, small test gap, or polish

## Finding Format

Each finding should include:

```text
Severity: P1
File: path/to/file.php
Line: 123
Issue: concise description
Evidence: why this is a real problem
Recommendation: the smallest correct fix
```

## Verdict Rules

- `APPROVE`: no blocking findings; remaining notes are optional.
- `CHANGES_REQUESTED`: at least one actionable P0, P1, or P2 finding.
- `BLOCKED`: review cannot be completed because inputs are missing, the workspace is not detached at the reviewed commit, tests are absent for a high-risk change, or the diff is not reviewable.

Claude must not change production source files.
