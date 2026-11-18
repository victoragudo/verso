<?php

namespace App\Verso\Theme\Console;

use App\Verso\System\Server\RuntimeLimits;
use App\Verso\Theme\Support\StaticExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

// "php artisan verso:export": writes the public site as static HTML files.
class ExportStaticSite extends Command
{
    protected $signature = 'verso:export
        {path? : Folder to write the site to}
        {--url= : Public URL of the static site, used in links}';

    protected $description = 'Export the public site as static HTML files';

    public function handle(StaticExporter $exporter, RuntimeLimits $limits): int
    {
        $directory = $this->directory();
        $url = $this->option('url');

        if (is_string($url) && $url !== '') {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->components->error("The URL [{$url}] is not valid.");

                return self::FAILURE;
            }

            URL::forceRootUrl(rtrim($url, '/'));
            URL::forceScheme((string) parse_url($url, PHP_URL_SCHEME));
        }

        // Exported pages must look like a guest visit, without saved sessions.
        config(['session.driver' => 'array']);

        $limits->raiseForHeavyTask();
        $written = $exporter->export($directory);

        $this->components->info(count($written).' files written to '.$directory.'.');

        return self::SUCCESS;
    }

    protected function directory(): string
    {
        $path = $this->argument('path');
        $path = is_string($path) && $path !== '' ? $path : (string) config('verso.export.path');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
