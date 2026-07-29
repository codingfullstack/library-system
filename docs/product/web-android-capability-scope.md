# Web and Android capability scope

This matrix is the product reference for future parity audits. Web route existence does not by itself define an Android feature gap.

| Funkcija | Member Android | Staff Android | Admin Android | Web | Statusas | Pastaba |
| --- | --- | --- | --- | --- | --- | --- |
| Knygu perziura | Yes | Yes | Yes | Yes | In Android scope | Android supports catalog/detail browsing for operational and member flows. |
| Rezervavimas | Yes | Yes, for members | Yes, for members | Yes | In Android scope | Backend remains the source of reservation capabilities. |
| Rezervacijos atsaukimas | Yes, when `can_cancel` | Yes, when `can_cancel` | Yes, when `can_cancel` | Yes | In Android scope | Visibility must follow API capability and endpoint authorization. |
| Paskolu perziura | Yes, own loans | Yes, active library loans | Yes, active library loans | Yes | In Android scope | Android is optimized for member self-service and staff circulation. |
| Skolinimas | No | Yes | Yes | Yes | Role-specific Android scope | Staff/admin circulation exists on Android; members cannot borrow directly. |
| Grazinimas | No | Yes | Yes | Yes | Role-specific Android scope | Staff/admin circulation exists on Android. |
| Kopiju lifecycle | No | Yes, allowed copies | Yes | Yes | Role-specific Android scope | Android supports operational copy lifecycle changes, not catalog administration. |
| Autoriu valdymas | No | No | No | Yes | Web-only | Administrative master data stays in Web. |
| Kategoriju valdymas | No | No | No | Yes | Web-only | Administrative taxonomy stays in Web. |
| Leidyklu valdymas | No | No | No | Yes | Web-only | Administrative master data stays in Web. |
| Biblioteku valdymas | No | No | No | Yes | Web-only | Multi-tenant management remains Web-only. |
| Filialu valdymas | No | No | No | Yes | Web-only | Branch administration remains Web-only. |
| Importai | No | No | No | Yes | Web-only | Bulk import workflows are not Android scope. |
| Audit logs | No | No | No | Yes | Web-only | Audit review remains Web-only and role-limited. |
| Eksportai | No | No | No | Yes | Web-only | Data export remains Web-only. |
| Pranesimai | Yes | Yes | Yes | Yes | In Android scope | Android consumes notifications and FCM; Web remains a management/visibility surface. |

Android product scope:
- Member Android: discover books, reserve/cancel when allowed, view own loans/reservations, receive notifications.
- Staff Android: circulation work near the desk or shelves: search/open books, borrow, return, manage eligible copy lifecycle, scan flows, receive notifications.
- Admin Android: same mobile operational scope as staff, with broader backend-authorized capabilities where API permits.

Web-only scope:
- Administrative catalog/master-data management, tenant/library/branch management, imports, exports, and audit log review.
- These are not defects unless a product requirement explicitly declares them as Android features.
