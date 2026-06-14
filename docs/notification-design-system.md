# Notification Design System

Backend is the single source of truth for notification UI metadata. Web and Android must not decide category, color, or icon from a notification event `kind`. They render the `ui` object returned by `UserNotificationResource`.

## API schema

Each notification response contains both the legacy event kind and the canonical UI type:

```json
{
  "type": "RESERVATION",
  "kind": "reservation_ready",
  "ui": {
    "type": "RESERVATION",
    "label": "Rezervacija",
    "category_key": "reservation",
    "category": "Rezervacija",
    "icon": "bookmark",
    "color": "Indigo"
  },
  "category": "Rezervacija",
  "icon": "bookmark",
  "color": "Indigo"
}
```

`kind` remains the database/event value for backwards compatibility. `type` and `ui.type` are canonical design-system values.

## Canonical Types

| Type | Use | Color | Icon |
| --- | --- | --- | --- |
| `INFO` | informational and system messages | Blue | info |
| `SUCCESS` | completed actions | Green | check_circle |
| `WARNING` | attention required, warnings | Orange | warning |
| `ERROR` | failed actions and system errors | Red | error |
| `BOOK` | book-related events | Purple | book |
| `RESERVATION` | reservations | Indigo | bookmark |
| `LOAN` | loans, returns, due dates | Teal | schedule |

## Current Event Mapping

| Event kind | Canonical type |
| --- | --- |
| `reservation_created` | `RESERVATION` |
| `reservation_queue_changed` | `RESERVATION` |
| `reservation_ready` | `RESERVATION` |
| `reservation_cancelled` | `RESERVATION` |
| `reservation_fulfilled` | `RESERVATION` |
| `loan_overdue` | `LOAN` |
| `book_due_soon` | `LOAN` |
| `book_returned` | `LOAN` |
| `library_membership_added` | `INFO` |
| `system` | `INFO` |
| `new_user` | `INFO` |
| `qr_scan` | `INFO` |
| `report_ready` | `INFO` |
| `issuance_summary` | `INFO` |
| `system_warning` | `WARNING` |
| `system_error` | `ERROR` |
| `account_security` | `WARNING` |

## Where To Change

Add or change notification UI semantics in:

`app/Support/Notifications/NotificationType.php`

Map database event kinds to canonical types in:

`app/Support/Notifications/NotificationUiConfig.php`

Web and Android may map canonical icon and color tokens to platform-native rendering, but they must not map individual notification kinds to their own categories, colors, or icons.
