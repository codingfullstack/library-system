# Database Tenant Ownership Audit

Status: documentation-only audit. No schema changes are made by this document.

Scope: `libraries`, `branches`, `locations`, `books`, `book_copies`,
`library_memberships`, `loans`, `reservations`, `reservation_queues`,
`scan_logs`, `notifications`, `user_notifications`, and `users`.

Important context: the current codebase already contains a later tenant
invariant migration (`2026_07_28_000000_enforce_tenant_ownership_invariants.php`)
and a read-only auditor (`TenantIntegrityAuditor`). This document records the
real ownership model and marks whether protection exists in the current DB. For
historical/simple-FK risk, see the "Possible violation" column.

## Ownership Model

| Table | Tenant authority | How `library_id` is obtained | Duplicated `library_id`? | Normalization note |
| --- | --- | --- | --- | --- |
| `libraries` | Self | Primary tenant root | No | Normalized tenant root. |
| `branches` | `libraries.id` | `branches.library_id` | No | Normalized child of a library. |
| `locations` | `branches.library_id` | Stored as `locations.library_id`; also derivable from `branch_id` | Yes | Intentional denormalization for library-scoped queries; must match branch tenant. |
| `books` | None/global catalog | No `library_id` | No | Bibliographic record is global; a library owns copies, not the book row. |
| `book_copies` | `branches.library_id` for physical custody; `books` is global | Stored as `book_copies.library_id`; also derivable from `branch_id` and nullable `location_id` | Yes | Intentional denormalization for scope/indexes; copy tenant must match branch/location. |
| `library_memberships` | `libraries.id` | `library_memberships.library_id` | No for user membership; yes if `branch_id` is set | Membership is the normalized tenant membership model for users. Staff branch is a scoped assignment. |
| `loans` | `book_copies.library_id` | Stored as `loans.library_id`; also derivable from `book_copy_id` | Yes | Intentional denormalization for history, scopes, and indexes; borrower must have membership in same library. |
| `reservations` | `library_memberships.library_id` plus library/book queue context | Stored as `reservations.library_id`; related to global `book_id` | Yes for user/branch/copy links | Intentional denormalization for active reservation and queue indexes; branch/copy/user links must match tenant. |
| `reservation_queues` | Queue key `(library_id, book_id)` | Stored from reservation/copy queue context | No duplicate tenant link except global `book_id` | Normalized queue mutex row; `book_id` is global, `library_id` defines tenant queue. |
| `scan_logs` | `book_copies.library_id` when a copy is found; otherwise active library context | Stored as `scan_logs.library_id`; nullable copy link may also derive tenant | Yes when `book_copy_id` exists | Intentional audit denormalization; copy link must not cross tenant. |
| `notifications` | Polymorphic recipient (`notifiable`) | No direct `library_id` | Not applicable | Laravel notification table is user-scoped/global; tenant context may live in payload only. |
| `user_notifications` | Recipient user plus related domain metadata | No direct `library_id` | Not applicable | App notification table is user-scoped; related morph is not DB-enforced. |
| `users` | Global identity | No `library_id` after membership normalization | No | Users can belong to multiple libraries through `library_memberships`; global `role` remains for account-level/superadmin compatibility. |

## Relationship Matrix

| Relationship | Tenant authority | Existing DB protection | Application protection | Possible violation | Recommended invariant | Migration risk |
| --- | --- | --- | --- | --- | --- | --- |
| `branches.library_id -> libraries.id` | `libraries` | Simple FK with cascade delete | `BelongsToLibrary` scope on `Branch` model | Low; branch cannot point to missing library | Keep simple FK | Low |
| `locations.library_id -> branches.library_id` through `locations.branch_id` | Branch library | Current composite FK `locations(branch_id, library_id) -> branches(id, library_id)` | `TenantIntegrityAuditor` checks `branch_tenant_mismatch`; scoped queries use `library_id` | Historical simple FK allowed a location in library A to point at branch in library B | Keep composite FK; keep duplicated `library_id` for query scope | Medium because existing bad rows block migration |
| `book_copies.library_id -> branches.library_id` | Branch library | Current composite FK `book_copies(branch_id, library_id) -> branches(id, library_id)` | `TenantIntegrityAuditor`; `User::canManageBookCopy` checks copy library/branch | Historical simple FK allowed copy tenant to diverge from branch tenant | Keep composite FK and `UNIQUE (branches.id, branches.library_id)` support | Medium; bad copy rows block migration |
| `book_copies.branch_id -> locations.branch_id` | Branch/location placement | Not fully enforced by DB; current FK only ensures location tenant matches copy tenant | Create/update forms/services should choose locations under selected branch | Copy and location can be same library but different branches, causing impossible shelf placement | Add composite FK or validation for `(location_id, branch_id, library_id)` if locations are branch-specific; current model implies they are | Medium; nullable location and existing rows require preflight |
| `book_copies.library_id -> locations.library_id` | Location library | Current composite FK `book_copies(location_id, library_id) -> locations(id, library_id)` | `TenantIntegrityAuditor` checks `location_tenant_mismatch` | Historical simple FK allowed cross-library location assignment | Keep composite FK; do not remove `book_copies.library_id` | Medium |
| `book_copies.book_id -> books.id` | Global book catalog | Simple FK only | Create/update actions bind copy to selected global book | No tenant crossing because `books` is global; risk is catalog misuse, not tenant escape | Keep simple FK. Do not add `books.library_id` solely for theory | Low |
| `library_memberships.library_id -> branches.library_id` through `branch_id` | Membership library | Current composite FK `library_memberships(branch_id, library_id) -> branches(id, library_id)` | `User::assignedBranchId`, effective role checks, membership token revocation | Staff/admin/member membership can be assigned to branch in another library if only simple FK exists | Keep composite FK; additionally validate branch assignment by role in application | Medium |
| Staff membership -> assigned branch | Membership library | Same composite FK protects tenant only, not role semantics | `User::assignedBranchId` returns branch only for effective staff; staff without branch has no branch-scoped permissions | Staff may have null branch and lose branch permissions; non-staff may technically have branch_id unless app prevents it | Keep tenant FK; enforce role/branch semantic in FormRequest/service, not trigger first | Low to medium |
| `loans.library_id -> book_copies.library_id` | Physical copy | Current composite FK `loans(book_copy_id, library_id) -> book_copies(id, library_id)` | `BorrowBookCopyAction` creates loan from locked copy and `canManageBookCopy` | Historical simple FK allowed loan in library A for copy in library B | Keep composite FK; keep active loan generated-column unique index | Medium; active history rows must be clean |
| `loans.user_id -> library_memberships(library_id, user_id)` | Borrower membership in loan library | Current composite FK `loans(library_id, user_id) -> library_memberships(library_id, user_id)` | `BorrowBookCopyAction` requires active member in copy library | Loan borrower can be a user with no membership in the loan library | Keep composite FK; application should keep active-membership rule because DB cannot express `is_active` FK | Medium; historical borrowers without membership block migration |
| `loans.issued_by` / `received_by -> users.id` | Global actor identity | Simple nullable FK to users | Actions set actor from authenticated user and `canManageBookCopy` | Actor may later lose membership; historical audit should remain valid | Keep simple user FK; do not require actor membership retroactively | Low |
| `reservations.library_id -> library_memberships(library_id, user_id)` | Reservation member membership | Current composite FK `reservations(library_id, user_id) -> library_memberships(library_id, user_id)` | `CreateReservationAction` resolves member in active library | Reservation for a user who is not a member of that library | Keep composite FK; application keeps active-user/active-membership validation | Medium |
| `reservations.library_id -> branches.library_id` through `branch_id` | Branch-scoped reservation | Current composite FK `reservations(branch_id, library_id) -> branches(id, library_id)` | `CreateReservationAction::resolveScope` validates branch belongs to library and staff branch limits | Branch-scoped reservation can point to another library branch | Keep composite FK | Medium |
| `reservations.library_id -> pickup_branch.library_id` | Ready pickup branch | Current composite FK `reservations(pickup_branch_id, library_id) -> branches(id, library_id)` | Ready assignment logic uses copy branch/pickup branch in same queue context | Ready reservation can send member to another library's branch | Keep composite FK; keep READY completeness check | Medium |
| `reservations.library_id -> assigned_book_copy.library_id` | Assigned physical copy | Current composite FK `reservations(assigned_book_copy_id, library_id) -> book_copies(id, library_id)` | `SyncReservationQueueAction` and queue service select copies by same library/book | Ready reservation can assign a copy from another library | Keep composite FK and active READY copy unique index | Medium |
| `reservations.book_id -> assigned_book_copy.book_id` | Global book/catalog consistency | Not enforced by current tenant FK | Queue service assigns using same `(library_id, book_id)` and copy eligibility | Reservation for book A can be assigned to copy of book B if bypassing app logic | Add composite consistency invariant using `book_copies(id, library_id, book_id)` and FK from `reservations(assigned_book_copy_id, library_id, book_id)`, or equivalent validation plus preflight | Medium; requires index and legacy data audit |
| `reservation_queues.library_id -> reservation tenant` | Queue library/book context | Unique `(library_id, book_id)` and FKs to `libraries`, `books`; no direct FK to reservations | Queue service locks/creates queue context before reservation mutations | Queue row can exist without reservations or copies; this is acceptable mutex state | Keep unique queue key; no trigger needed. Orphan queue rows are harmless | Low |
| `reservation_queues.book_id -> books.id` | Global book catalog | Simple FK | Queue service derives from reservation/copy book | No tenant crossing; queues are per library/global book | Keep simple FK and unique `(library_id, book_id)` | Low |
| `scan_logs.library_id -> book_copies.library_id` | Scanned copy when present | Current composite FK `scan_logs(book_copy_id, library_id) -> book_copies(id, library_id)` | `TenantIntegrityAuditor`; scan actions should log active context | Scan log in library A can reference copy in library B under simple FK | Keep composite FK. Nullable copy keeps failed scans possible | Low to medium |
| `scan_logs.user_id -> users.id` | Global scanner/member identity | Simple nullable FK only | Actions/controller set authenticated user | User may not belong to scan library; for anonymous/error scans this may be intentional | Leave simple FK unless product requires membership-bound scans; do not force composite without policy decision | Low |
| `notifications.notifiable_id/type -> users` | Recipient identity | Polymorphic, no FK | Laravel notification routing | Notification can point to nonexistent or cross-tenant recipient by payload | Keep as framework table; use app-level routing and cleanup. Composite FK not practical for polymorph | Low |
| `user_notifications.user_id/sent_by -> users` | Recipient/sender identity | Simple FKs to users | `CreateUserNotificationAction` receives domain object and metadata | Notification can reference related domain row from another tenant through `related_type/id` | Keep user FKs; add application assertion that related model is visible to recipient. DB FK not practical for polymorph | Low to medium |
| `users -> library_memberships` | Membership table | `library_memberships` FK to users plus unique `(library_id, user_id)` | `User::belongsToLibrary`, `effectiveRole`, `activeLibraryId` | Reintroducing `users.library_id` would create uncontrolled duplicate tenant state | Keep users global; library access must flow through membership | Low |

## Findings

1. `library_id` on `locations`, `book_copies`, `loans`, `reservations`, and
   `scan_logs` is intentional denormalization. It supports tenant scoping,
   indexes, historical records, and queue/concurrency queries. It should not be
   removed just because it can be derived.

2. The highest-risk uncontrolled duplicates are child rows that carry
   `library_id` and also reference another tenant-bearing row: locations to
   branches, copies to branches/locations, memberships to branches, loans to
   copies/users, reservations to members/branches/copies, and scan logs to
   copies.

3. `books` is not tenant-owned. A relationship involving `book_id` is a catalog
   consistency invariant, not a tenant invariant. The important unresolved
   consistency rule is `reservations.book_id == assigned_book_copy.book_id`.

4. Polymorphic notification tables cannot be made fully tenant-safe with normal
   FKs. Their tenant safety must come from notification creation services and
   recipient visibility rules.

5. Application-layer protection exists in `BelongsToLibrary`, user capability
   helpers, reservation/loan actions, and `ReservationQueueService`. These are
   necessary but should not be the only protection for tenant-bearing FK pairs
   that can be expressed as composite FKs.

## Recommended Fix Plan

1. Keep `library_id` denormalization where it is used for scoping, indexes,
   history, or queue locking.

2. Before adding or tightening constraints, run a read-only preflight auditor
   that reports row IDs and violation types for each tenant-bearing FK pair.

3. Prefer composite unique indexes and composite FKs:
   - `branches(id, library_id)`
   - `locations(id, library_id)`
   - `book_copies(id, library_id)`
   - child FKs that include both local FK and local `library_id`

4. Add or verify a book-consistency invariant for ready reservations:
   `reservations(assigned_book_copy_id, library_id, book_id)` should reference a
   matching copy identity if the DB engine/index model permits it cleanly. If
   not, keep explicit application validation and add a preflight check.

5. Keep role and active-state rules in application services/policies/FormRequests
   because normal FKs cannot express "active membership" or "staff must have a
   branch" cleanly.

6. Do not use triggers as the first option. Use triggers only if a future
   invariant cannot be expressed through normalization, composite unique indexes,
   composite FKs, or explicit application validation.

