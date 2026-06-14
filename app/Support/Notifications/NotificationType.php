<?php

namespace App\Support\Notifications;

enum NotificationType: string
{
    case INFO = 'INFO';
    case SUCCESS = 'SUCCESS';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case BOOK = 'BOOK';
    case RESERVATION = 'RESERVATION';
    case LOAN = 'LOAN';

    /**
     * @return array<string, mixed>
     */
    public function ui(): array
    {
        return match ($this) {
            self::INFO => $this->make('Informacinis', 'info', 'Blue', 'info'),
            self::SUCCESS => $this->make('Atlikta', 'success', 'Green', 'check_circle'),
            self::WARNING => $this->make('Perspėjimas', 'warning', 'Orange', 'warning'),
            self::ERROR => $this->make('Klaida', 'error', 'Red', 'error'),
            self::BOOK => $this->make('Knyga', 'book', 'Purple', 'book'),
            self::RESERVATION => $this->make('Rezervacija', 'reservation', 'Indigo', 'bookmark'),
            self::LOAN => $this->make('Paskola', 'loan', 'Teal', 'schedule'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function make(string $label, string $categoryKey, string $color, string $icon): array
    {
        return [
            'type' => $this->value,
            'label' => $label,
            'category_key' => $categoryKey,
            'category' => $label,
            'color' => $color,
            'icon' => $icon,
        ];
    }
}
