<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Seo\GenerateSitemapAction;
use Illuminate\Console\Command;

final class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate {--path= : Custom sitemap output path}';

    protected $description = 'Generate the public sitemap.xml file.';

    public function handle(GenerateSitemapAction $generateSitemap): int
    {
        $path = $generateSitemap->execute($this->option('path') ?: null);

        $this->components->info("Sitemap generated: {$path}");

        return self::SUCCESS;
    }
}
