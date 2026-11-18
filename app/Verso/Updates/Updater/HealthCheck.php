<?php

namespace App\Verso\Updates\Updater;

use App\Verso\Updates\Exceptions\UpdateFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Asks the site for a few pages (home and login) and fails when one of them does not work.
class HealthCheck
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        protected string $baseUrl,
        protected array $paths,
        protected int $timeout,
    ) {}

    // The checked URLs, for the update log.
    public function check(): string
    {
        $checked = [];

        foreach ($this->paths as $path) {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');

            try {
                $response = Http::timeout($this->timeout)->withHeaders(['Cache-Control' => 'no-cache'])->get($url);
            } catch (ConnectionException $exception) {
                throw UpdateFailed::because(__('The site did not answer at :url (:error).', ['url' => $url, 'error' => $exception->getMessage()]));
            }

            if (! $response->successful()) {
                throw UpdateFailed::because(__('The site answered :status at :url.', ['status' => $response->status(), 'url' => $url]));
            }

            $checked[] = $path;
        }

        return __('Pages answered: :pages.', ['pages' => implode(', ', $checked)]);
    }
}
