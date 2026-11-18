<?php

namespace App\Verso\Site\Jobs;

use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

// Tells IndexNow search engines (Bing, Yandex, Seznam and others) that some pages are new or changed.
class PingIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param  list<string>  $urls */
    public function __construct(public array $urls)
    {
        $this->onQueue((string) config('verso.seo.indexnow.queue'));
    }

    public function handle(SeoSettings $seo): void
    {
        $key = $seo->indexNowKey();

        // The key or the switch may have changed since the job was queued.
        if ($key === null || $seo->discourage() || $this->urls === []) {
            return;
        }

        try {
            $response = Http::timeout((int) config('verso.seo.indexnow.timeout'))
                ->acceptJson()
                ->post((string) config('verso.seo.indexnow.endpoint'), [
                    'host' => (string) parse_url(url('/'), PHP_URL_HOST),
                    'key' => $key,
                    'keyLocation' => url('/'.$key.'.txt'),
                    'urlList' => $this->urls,
                ]);

            if ($response->failed()) {
                Log::warning('IndexNow ping failed.', ['status' => $response->status()]);
            }
        } catch (Throwable $exception) {
            Log::warning('IndexNow ping failed.', ['error' => $exception->getMessage()]);
        }
    }
}
