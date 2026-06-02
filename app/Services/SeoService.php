<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SeoData;
use Illuminate\Support\Str;

final class SeoService
{
    public function make(
        ?string $title = null,
        ?string $description = null,
        ?string $canonicalUrl = null,
        string $type = 'website',
    ): SeoData {
        return new SeoData(
            title: $this->title($title),
            description: $this->description($description),
            canonicalUrl: $canonicalUrl,
            type: $type,
        );
    }

    public function title(?string $title = null): string
    {
        $defaultTitle = (string) config('seo.title', config('app.name', 'LibraryApp'));

        if (blank($title)) {
            return $defaultTitle;
        }

        return Str::of($title)->contains($defaultTitle)
            ? trim($title)
            : trim($title).' - '.$defaultTitle;
    }

    public function description(?string $description = null): string
    {
        $value = filled($description)
            ? (string) $description
            : (string) config('seo.description', '');

        return Str::limit(trim(strip_tags($value)), 160, '');
    }
}
