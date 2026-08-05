# Android mutation refresh measurement

Date: 2026-07-29

Scope:
- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/books/BooksDetailsViewModel.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/member/MemberReservationsScreen.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/loans/ActiveLoansViewModel.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/member/MemberLoansScreen.kt`
- `LibraryApp/app/src/main/java/com/example/libraryapp/ui/common/SessionViewModel.kt`
- Android repositories and the Laravel API controllers used by these flows.

Measurement method:
- Static request-path tracing from ViewModel -> repository -> Retrofit service.
- Backend query review from API controller/action/resource loading paths.
- Payload class review from DTO/resource boundaries.
- Compose state review for list keys, loading flags, and refresh triggers.

## Before

| Operation | Android requests after user action | Authoritative refresh | Payload size class | Backend query risk | UI risk |
| --- | ---: | --- | --- | --- | --- |
| borrow from book details | 2: `POST /book-copies/{id}/borrow`, `GET /books/{id}` | Full book details | Large, because copies/reservations/capabilities are needed | Queue sync + book details relations; no Android-side N+1 | Staff action lacked ViewModel double-submit guard |
| return from book details | 2: `POST /book-copies/{id}/return`, `GET /books/{id}` | Full book details | Large | Queue sync + book details relations | Staff action lacked ViewModel double-submit guard |
| return from active loans | 2: `POST /book-copies/{id}/return`, `GET /loans/active` | List refresh | Medium | Paginator/resource relations | Staff action lacked ViewModel double-submit guard |
| reserve | 2: `POST /reservations`, `GET /books/{id}` | Full book details | Large | Queue sync + book details relations | Staff/member action lacked ViewModel double-submit guard |
| cancel from book details | 2: `PATCH /reservations/{id}/cancel`, `GET /books/{id}` | Full book details | Large | Queue sync + book details relations | Guarded by reservation id |
| cancel from reservations list | 2: `PATCH /reservations/{id}/cancel`, `GET /reservations` | Server reservation applied, then list refresh for summary/capabilities | Medium | Paginator + summary | Guarded by reservation id |
| lifecycle change | 2: `PATCH /book-copies/{id}/lifecycle`, `GET /books/{id}` | Full book details | Large | Book details relations | Staff action lacked ViewModel double-submit guard |
| screen resume/manual refresh | 1 per screen | Existing screen refresh | Medium to large | Endpoint-specific | Existing `isRefreshing`/`isLoading` guards |
| session refresh | 1 `GET /auth/me` | Session resource | Small | Low | Existing running-request guard |

Findings:
- Full book details refetch is intentional for borrow, return, reserve, cancel, and lifecycle change because these mutations can change copy status, reservation status, queue position, pickup branch, capabilities, loan state, and available-copy counts.
- Reservations-list cancel already uses the server-returned `ReservationResource` and then refreshes the list to keep summary and capability fields authoritative.
- `PaginationController.updateFilters` avoids duplicate initial loads for unchanged filters while a page is already loading.
- Real issue found: staff mutation methods used UI button disablement but did not reject a second direct ViewModel call while the first request was running.

## After

| Operation | Android requests after user action | Change |
| --- | ---: | --- |
| borrow from book details | 2 normal requests; duplicate mutation call ignored while pending | Added ViewModel action guard |
| return from book details | 2 normal requests; duplicate mutation call ignored while pending | Added ViewModel action guard |
| return from active loans | 2 normal requests; duplicate mutation call ignored while pending | Added ViewModel action guard |
| reserve | 2 normal requests; duplicate mutation call ignored while pending | Added ViewModel action guard |
| lifecycle change | 2 normal requests; duplicate mutation call ignored while pending | Added ViewModel action guard |
| cancel | Unchanged | Already guarded by reservation id |
| screen/session refresh | Unchanged | Existing guards retained |

Latency and recomposition notes:
- The code has no production latency instrumentation yet, so absolute latency numbers must come from the HTTP client/backend logs in an instrumented run.
- Recomposition is scoped to the relevant `StateFlow` changes. Stable LazyColumn keys are present for reservations and loans; no scroll-state reset was found from list identity alone.
- Loading flicker is minimized by keeping current content during `isRefreshing`; initial full-screen loaders remain only when there is no current data.

Decision:
- Keep authoritative refetch for correctness.
- Optimize only duplicate mutation submission at the ViewModel boundary.
- Do not introduce local optimistic patching.
