<?php

declare(strict_types=1);

namespace App\Data;

final readonly class SeoData
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $canonicalUrl = null,
        public ?string $image = null,
        public string $type = 'website',
    ) {}
}
