<?php

namespace App\Support\Notifications;

class NotificationUiConfig
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'reservation_created' => self::forType(NotificationType::RESERVATION),
            'reservation_queue_changed' => self::forType(NotificationType::RESERVATION),
            'reservation_ready' => self::forType(NotificationType::RESERVATION),
            'reservation_cancelled' => self::forType(NotificationType::RESERVATION),
            'reservation_fulfilled' => self::forType(NotificationType::RESERVATION),
            'loan_overdue' => self::forType(NotificationType::LOAN),
            'book_due_soon' => self::forType(NotificationType::LOAN),
            'book_returned' => self::forType(NotificationType::LOAN),
            'library_membership_added' => self::forType(NotificationType::INFO),
            'system' => self::forType(NotificationType::INFO),
            'new_user' => self::forType(NotificationType::INFO),
            'qr_scan' => self::forType(NotificationType::INFO),
            'report_ready' => self::forType(NotificationType::INFO),
            'issuance_summary' => self::forType(NotificationType::INFO),
            'system_warning' => self::forType(NotificationType::WARNING),
            'system_error' => self::forType(NotificationType::ERROR),
            'account_security' => self::forType(NotificationType::WARNING),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(string $type): array
    {
        $normalizedType = strtolower($type);
        $config = self::all()[$normalizedType] ?? null;

        if ($config === null) {
            return self::fallback();
        }

        return $config;
    }

    public static function has(string $type): bool
    {
        return array_key_exists(strtolower($type), self::all());
    }

    /**
     * @return array<int, string>
     */
    public static function typesForCategory(string $category): array
    {
        return collect(self::all())
            ->filter(fn (array $config) => $config['category_key'] === $category)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function typeCatalog(): array
    {
        return collect(NotificationType::cases())
            ->mapWithKeys(fn (NotificationType $type) => [$type->value => self::forType($type)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function fallback(): array
    {
        return self::forType(NotificationType::INFO);
    }

    /**
     * @return array<string, mixed>
     */
    private static function forType(NotificationType $type): array
    {
        $ui = $type->ui();

        return array_merge($ui, [
            'web' => self::webTokens($ui['color'], $ui['icon']),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function webTokens(string $color, string $icon): array
    {
        $palette = [
            'Blue' => [
                'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                'icon_wrap' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                'unread' => 'bg-sky-50/45 dark:bg-sky-500/10',
                'dot' => 'bg-sky-500',
            ],
            'Green' => [
                'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                'icon_wrap' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                'unread' => 'bg-emerald-50/45 dark:bg-emerald-500/10',
                'dot' => 'bg-emerald-500',
            ],
            'Orange' => [
                'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
                'icon_wrap' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
                'unread' => 'bg-orange-50/45 dark:bg-orange-500/10',
                'dot' => 'bg-orange-500',
            ],
            'Red' => [
                'badge' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                'icon_wrap' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                'unread' => 'bg-red-50/45 dark:bg-red-500/10',
                'dot' => 'bg-red-500',
            ],
            'Purple' => [
                'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                'icon_wrap' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                'unread' => 'bg-violet-50/45 dark:bg-violet-500/10',
                'dot' => 'bg-violet-500',
            ],
            'Indigo' => [
                'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                'icon_wrap' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                'unread' => 'bg-indigo-50/45 dark:bg-indigo-500/10',
                'dot' => 'bg-indigo-500',
            ],
            'Teal' => [
                'badge' => 'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
                'icon_wrap' => 'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
                'unread' => 'bg-teal-50/45 dark:bg-teal-500/10',
                'dot' => 'bg-teal-500',
            ],
        ];

        return array_merge($palette[$color], [
            'icon' => $icon,
            'icon_svg' => self::iconSvg($icon),
        ]);
    }

    private static function iconSvg(string $icon): string
    {
        return match ($icon) {
            'info' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01"/></svg>',
            'check_circle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>',
            'warning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
            'error' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v6"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01"/></svg>',
            'book' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v14"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 18a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 18a2 2 0 0 0-2-2h-5a2 2 0 0 0-2 2V6a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2z"/></svg>',
            'bookmark' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-4-6 4z"/></svg>',
            'schedule' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>',
            default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01"/></svg>',
        };
    }
}
