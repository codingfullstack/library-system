<?php

declare(strict_types=1);

namespace App\Actions\Seo;

use App\Models\Book;
use App\Models\Library;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

final class GenerateSitemapAction
{
    public function execute(?string $path = null): string
    {
        $targetPath = $path ?? (string) config('seo.sitemap_path', public_path('sitemap.xml'));

        $sitemap = Sitemap::create();

        foreach ($this->staticUrls() as $url) {
            $sitemap->add($url);
        }

        $this->addPublicBooks($sitemap);
        $this->addPublicLibraries($sitemap);
        $this->addPublicNews($sitemap);

        $sitemap->writeToFile($targetPath);

        return $targetPath;
    }

    /**
     * @return list<Url>
     */
    private function staticUrls(): array
    {
        $routes = [
            ['name' => 'home', 'frequency' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 1.0],
            ['name' => 'public.libraries.index', 'frequency' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.9],
            ['name' => 'about', 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.7],
            ['name' => 'contact', 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY, 'priority' => 0.6],
            ['name' => 'books.index', 'frequency' => Url::CHANGE_FREQUENCY_DAILY, 'priority' => 0.9],
        ];

        return collect($routes)
            ->filter(fn (array $route): bool => Route::has($route['name']))
            ->map(fn (array $route): Url => Url::create(route($route['name']))
                ->setChangeFrequency($route['frequency'])
                ->setPriority($route['priority']))
            ->values()
            ->all();
    }

    private function addPublicBooks(Sitemap $sitemap): void
    {
        Book::query()
            ->select(['id', 'updated_at'])
            ->whereHas('bookCopies.library', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_public', true))
            ->orderBy('id')
            ->chunkById(500, function ($books) use ($sitemap): void {
                foreach ($books as $book) {
                    $sitemap->add(
                        Url::create(route('books.show', $book))
                            ->setLastModificationDate($book->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.8)
                    );
                }
            });
    }

    private function addPublicLibraries(Sitemap $sitemap): void
    {
        if (! Route::has('public.libraries.show')) {
            return;
        }

        Library::query()
            ->select(['id', 'updated_at'])
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('id')
            ->chunkById(500, function ($libraries) use ($sitemap): void {
                foreach ($libraries as $library) {
                    $sitemap->add(
                        Url::create(route('public.libraries.show', $library))
                            ->setLastModificationDate($library->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.8)
                    );
                }
            });
    }

    private function addPublicNews(Sitemap $sitemap): void
    {
        $modelClass = config('seo.news_model');
        $routeName = (string) config('seo.news_route', 'news.show');

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! Route::has($routeName)) {
            return;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass::query()
            ->when($this->hasColumn($modelClass, 'is_public'), fn (Builder $query) => $query->where('is_public', true))
            ->when($this->hasColumn($modelClass, 'published_at'), fn (Builder $query) => $query
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->orderBy($this->hasColumn($modelClass, 'published_at') ? 'published_at' : 'id')
            ->chunkById(500, function ($items) use ($sitemap, $routeName): void {
                foreach ($items as $item) {
                    $lastModified = $item->updated_at ?? $item->published_at ?? now();

                    $sitemap->add(
                        Url::create(route($routeName, $item))
                            ->setLastModificationDate($lastModified)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7)
                    );
                }
            });
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function hasColumn(string $modelClass, string $column): bool
    {
        /** @var Model $model */
        $model = new $modelClass();

        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
