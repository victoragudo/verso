<?php

namespace App\Verso\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Media\Images\ImageServerFactory;
use App\Verso\System\Server\RuntimeLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use League\Glide\Filesystem\FileNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

// Serves resized images from signed /img/{path} URLs, cached on disk.
class ImageController extends Controller
{
    // Source file types Glide can read.
    protected const SOURCE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

    // Ways to fit an image into the asked size.
    protected const FITS = ['contain', 'max', 'fill', 'stretch', 'crop'];

    public function __invoke(Request $request, ImageServerFactory $factory, RuntimeLimits $limits, string $path): StreamedResponse
    {
        abort_unless($this->isSafeImagePath($path), 404);

        $params = $this->params($request);
        $server = $factory->make();

        // Resizing a new image is heavy work: give it more memory and time when PHP allows it.
        if (! $server->cacheFileExists($path, $params)) {
            $limits->raiseForHeavyTask();
        }

        try {
            $cachedPath = $server->makeImage($path, $params);
        } catch (FileNotFoundException) {
            abort(404);
        } catch (Throwable $exception) {
            report($exception);
            abort(404);
        }

        return Storage::disk($factory->cacheDisk())->response($cachedPath, null, [
            'Cache-Control' => 'public, max-age='.(int) config('verso.media.images.browser_cache_seconds').', immutable',
        ]);
    }

    // No folder tricks and only image files.
    public function isSafeImagePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::SOURCE_EXTENSIONS, true);
    }

    /**
     * Checks the size, format and quality asked for in the URL.
     *
     * @return array<string, int|string>
     */
    protected function params(Request $request): array
    {
        $max = (int) config('verso.media.images.max_dimension');

        $validator = Validator::make($request->query(), [
            'w' => ['nullable', 'integer', 'min:1', 'max:'.$max],
            'h' => ['nullable', 'integer', 'min:1', 'max:'.$max],
            'fit' => ['nullable', Rule::in(self::FITS)],
            'fm' => ['nullable', Rule::in((array) config('verso.media.images.formats'))],
            'q' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        abort_if($validator->fails(), 400);

        /** @var array{w?: int|string|null, h?: int|string|null, fit?: string|null, fm?: string|null, q?: int|string|null} $valid */
        $valid = $validator->validated();

        return array_filter([
            'w' => isset($valid['w']) ? (int) $valid['w'] : null,
            'h' => isset($valid['h']) ? (int) $valid['h'] : null,
            'fit' => $valid['fit'] ?? null,
            'fm' => $valid['fm'] ?? null,
            'q' => isset($valid['q']) ? (int) $valid['q'] : null,
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
