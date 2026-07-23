<?php

namespace App\Support\Notifications;

enum NotificationType: string
{
    case RESERVATION_CREATED = 'reservation_created';
    case RESERVATION_QUEUE_CHANGED = 'reservation_queue_changed';
    case RESERVATION_READY = 'reservation_ready';
    case RESERVATION_CANCELLED = 'reservation_cancelled';
    case RESERVATION_EXPIRED = 'reservation_expired';
    case RESERVATION_FULFILLED = 'reservation_fulfilled';
    case LOAN_OVERDUE = 'loan_overdue';
    case BOOK_DUE_SOON = 'book_due_soon';
    case BOOK_RETURNED = 'book_returned';
    case LIBRARY_MEMBERSHIP_ADDED = 'library_membership_added';
    case SYSTEM = 'system';
    case NEW_USER = 'new_user';
    case QR_SCAN = 'qr_scan';
    case REPORT_READY = 'report_ready';
    case ISSUANCE_SUMMARY = 'issuance_summary';
    case SYSTEM_WARNING = 'system_warning';
    case SYSTEM_ERROR = 'system_error';
    case ACCOUNT_SECURITY = 'account_security';

    public static function normalize(self|string $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        return self::tryFrom(strtolower($type)) ?? self::SYSTEM;
    }

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::RESERVATION_CREATED,
            self::RESERVATION_QUEUE_CHANGED,
            self::RESERVATION_READY,
            self::RESERVATION_CANCELLED,
            self::RESERVATION_EXPIRED,
            self::RESERVATION_FULFILLED => NotificationCategory::RESERVATION,

            self::LOAN_OVERDUE,
            self::BOOK_DUE_SOON,
            self::BOOK_RETURNED => NotificationCategory::LOAN,

            self::SYSTEM_WARNING,
            self::ACCOUNT_SECURITY => NotificationCategory::WARNING,

            self::SYSTEM_ERROR => NotificationCategory::ERROR,

            default => NotificationCategory::INFO,
        };
    }

    public function defaultTitle(): string
    {
        return match ($this) {
            self::RESERVATION_CREATED => 'Rezervacija sukurta',
            self::RESERVATION_QUEUE_CHANGED => 'Rezervacijos eilė pasikeitė',
            self::RESERVATION_READY => 'Rezervacija paruošta',
            self::RESERVATION_CANCELLED => 'Rezervacija atšaukta',
            self::RESERVATION_EXPIRED => 'Rezervacijos galiojimas baigėsi',
            self::RESERVATION_FULFILLED => 'Rezervacija įvykdyta',
            self::LOAN_OVERDUE => 'Vėluojate grąžinti knygą',
            self::BOOK_DUE_SOON => 'Artėja grąžinimo terminas',
            self::BOOK_RETURNED => 'Knyga grąžinta',
            self::LIBRARY_MEMBERSHIP_ADDED => 'Biblioteka pridėta',
            self::SYSTEM => 'Sistemos pranešimas',
            self::NEW_USER => 'Naujas vartotojas',
            self::QR_SCAN => 'QR nuskaitymas',
            self::REPORT_READY => 'Ataskaita paruošta',
            self::ISSUANCE_SUMMARY => 'Išdavimo suvestinė',
            self::SYSTEM_WARNING => 'Sistemos perspėjimas',
            self::SYSTEM_ERROR => 'Sistemos klaida',
            self::ACCOUNT_SECURITY => 'Paskyros saugumas',
        };
    }

    public function priority(): string
    {
        return match ($this) {
            self::SYSTEM_ERROR,
            self::ACCOUNT_SECURITY,
            self::LOAN_OVERDUE => 'high',
            self::RESERVATION_READY,
            self::RESERVATION_EXPIRED,
            self::BOOK_DUE_SOON,
            self::SYSTEM_WARNING => 'medium',
            default => 'normal',
        };
    }
}
