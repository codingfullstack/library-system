<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\ImageMeta;
use RalphJSmit\Laravel\SEO\Support\SEOData;

final class SeoService
{
    /**
     * @var list<string>
     */
    private const INDEXABLE_ROUTE_NAMES = [
        'home',
        'public.libraries.index',
        'about',
        'contacts',
        'help',
    ];

    public function make(
        ?string $title = null,
        ?string $description = null,
        ?string $canonicalUrl = null,
        ?string $image = null,
        string $type = 'website',
        ?SchemaCollection $schema = null,
        ?string $robots = null,
    ): SEOData {
        $resolvedTitle = $this->title($title);

        return new SEOData(
            title: $resolvedTitle,
            description: $this->description($description),
            image: $this->image($image),
            imageMeta: $this->imageMeta($image),
            url: $canonicalUrl,
            schema: $schema,
            type: $type,
            site_name: (string) config('seo.site_name', 'Bibliotekų sistema'),
            locale: 'lt_LT',
            robots: $robots ?? $this->robots(),
            canonical_url: $canonicalUrl,
            openGraphTitle: $resolvedTitle,
        );
    }

    public function title(?string $title = null): string
    {
        if (filled($title)) {
            return trim($title);
        }

        return (string) config('seo.site_name', config('app.name', 'Bibliotekų sistema'));
    }

    public function description(?string $description = null): string
    {
        $value = filled($description)
            ? (string) $description
            : (string) config('seo.description.fallback', '');

        return Str::limit(trim(strip_tags($value)), 160, '');
    }

    public function image(?string $image = null): ?string
    {
        $value = filled($image)
            ? (string) $image
            : (string) config('seo.image.fallback', '');

        if (blank($value)) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return secure_url(ltrim($value, '/'));
    }

    private function imageMeta(?string $image = null): ?ImageMeta
    {
        $value = filled($image)
            ? (string) $image
            : (string) config('seo.image.fallback', '');

        if (blank($value) || Str::startsWith($value, ['http://', 'https://'])) {
            return null;
        }

        return new ImageMeta(ltrim($value, '/'));
    }

    private function robots(): ?string
    {
        $route = request()->route();

        if (! $route) {
            return null;
        }

        if (in_array($route->getName(), self::INDEXABLE_ROUTE_NAMES, true)) {
            return null;
        }

        return in_array('auth', $route->gatherMiddleware(), true)
            ? 'noindex,nofollow'
            : 'noindex,nofollow';
    }
}
