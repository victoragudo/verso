<?php

namespace App\Verso\Importer\WordPress;

use App\Verso\Importer\Content\ImportedMedia;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Wxr\WxrItem;
use finfo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

// Downloads WordPress attachments into Verso storage. Failed downloads are skipped and reported.
class AttachmentImporter
{
    protected ?bool $hasMediaTable = null;

    /** @var list<string>|null */
    protected ?array $mediaColumns = null;

    public function __construct(
        protected string $disk,
        protected string $directory,
        protected bool $enabled = true,
        protected int $timeout = 30,
        protected int $retries = 2,
        protected int $retryDelay = 500,
        protected int $maxBytes = 26214400,
    ) {}

    public function import(WxrItem $item, ImportState $state): ?ImportedMedia
    {
        if (! $this->enabled) {
            $state->report->add('media', ImportReport::SKIPPED);

            return null;
        }

        $url = $item->attachmentUrl;

        if (preg_match('#^https?://#i', $url) !== 1) {
            return $this->fail($state, __('Attachment :id has no valid URL, so it was skipped.', ['id' => $item->id]));
        }

        $path = $this->pathFor($item);
        $storage = Storage::disk($this->disk);

        if ($storage->exists($path)) {
            $state->report->add('media', ImportReport::SKIPPED);

            return $this->remember($item, $state, $path, $storage, null);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retries + 1, $this->retryDelay, throw: false)
                ->get($url);
        } catch (Throwable $exception) {
            return $this->fail($state, __('Could not download :url: :error', ['url' => $url, 'error' => $exception->getMessage()]));
        }

        $problem = $this->problemWith($response, $url);

        if ($problem !== null) {
            return $this->fail($state, $problem);
        }

        $body = $response->body();
        $storage->put($path, $body);
        $state->report->add('media', ImportReport::CREATED);

        return $this->remember($item, $state, $path, $storage, $body);
    }

    // Keep the WordPress folder layout ("2024/03/photo.jpg") under the media folder.
    public function pathFor(WxrItem $item): string
    {
        $relative = (string) $item->metaValue('_wp_attached_file');

        if ($relative === '' || preg_match('#^https?://#i', $relative) === 1) {
            $date = $item->date !== '' ? substr($item->date, 0, 7) : '';
            $relative = str_replace('-', '/', $date).'/'.basename((string) parse_url($item->attachmentUrl, PHP_URL_PATH));
        }

        $segments = array_filter(
            explode('/', str_replace('\\', '/', rawurldecode($relative))),
            fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..',
        );
        $file = array_pop($segments) ?? 'attachment-'.$item->id;
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $name = Str::slug(pathinfo($file, PATHINFO_FILENAME)) ?: 'attachment-'.$item->id;
        $folders = array_map(fn (string $segment): string => Str::slug($segment) ?: 'files', $segments);

        return implode('/', [trim($this->directory, '/'), ...$folders, $name.($extension !== '' ? '.'.strtolower($extension) : '')]);
    }

    protected function problemWith(Response $response, string $url): ?string
    {
        if (! $response->successful()) {
            return __('Could not download :url: the server answered :status.', ['url' => $url, 'status' => $response->status()]);
        }

        $length = (int) $response->header('Content-Length');
        $size = strlen($response->body());

        if ($length > $this->maxBytes || $size > $this->maxBytes) {
            return __('Could not download :url: the file is larger than :bytes bytes.', ['url' => $url, 'bytes' => $this->maxBytes]);
        }

        if ($size === 0) {
            return __('Could not download :url: the file is empty.', ['url' => $url]);
        }

        return null;
    }

    protected function remember(WxrItem $item, ImportState $state, string $path, Filesystem $storage, ?string $body): ImportedMedia
    {
        $alt = trim((string) $item->metaValue('_wp_attachment_image_alt'));
        $media = new ImportedMedia(
            mediaId: $this->mediaRow($item, $state, $path, $storage, $body, $alt),
            url: $storage->url($path),
            path: $path,
            alt: $alt,
        );

        $state->media->add($item->id, $item->attachmentUrl, $media);

        return $media;
    }

    // Add a row to the media table when the Media module has created it.
    protected function mediaRow(WxrItem $item, ImportState $state, string $path, Filesystem $storage, ?string $body, string $alt): ?int
    {
        if (! $this->hasMediaTable()) {
            return null;
        }

        $existing = DB::table('media')->where('disk', $this->disk)->where('path', $path)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $body ??= (string) $storage->get($path);
        $size = $this->imageSize($body);
        $now = now();

        $row = array_intersect_key([
            'disk' => $this->disk,
            'path' => $path,
            'filename' => basename($path),
            'mime_type' => (new finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: 'application/octet-stream',
            'size' => strlen($body),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'alt' => $alt !== '' ? $alt : null,
            'caption' => trim(strip_tags($item->excerpt)) ?: null,
            'uploaded_by' => $state->authorFor($item->creator)->id,
            'meta' => json_encode(['wordpress' => ['id' => (string) $item->id, 'url' => $item->attachmentUrl]]),
            'created_at' => $now,
            'updated_at' => $now,
        ], array_flip($this->mediaColumns()));

        return (int) DB::table('media')->insertGetId($row);
    }

    /** @return array{0: int, 1: int}|null */
    protected function imageSize(string $body): ?array
    {
        $size = @getimagesizefromstring($body);

        return $size === false ? null : [$size[0], $size[1]];
    }

    protected function fail(ImportState $state, string $message): null
    {
        $state->report->add('media', ImportReport::FAILED);
        $state->report->warn($message);

        return null;
    }

    protected function hasMediaTable(): bool
    {
        return $this->hasMediaTable ??= Schema::hasTable('media');
    }

    /** @return list<string> */
    protected function mediaColumns(): array
    {
        return $this->mediaColumns ??= Schema::getColumnListing('media');
    }
}
