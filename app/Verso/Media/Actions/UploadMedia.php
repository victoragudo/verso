<?php

namespace App\Verso\Media\Actions;

use App\Models\User;
use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Filters\UploadRulesFilter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks an uploaded file and stores it in the media library.
 *
 * @phpstan-type MediaData array{alt?: string|null, caption?: string|null}
 */
class UploadMedia
{
    // Longest file name we keep on disk, without the extension.
    protected const NAME_MAX_LENGTH = 100;

    /**
     * @param  list<string>  $allowedMimes
     */
    public function __construct(
        protected FilesystemFactory $disks,
        protected string $disk,
        protected string $folderFormat,
        protected int $maxKilobytes,
        protected array $allowedMimes,
    ) {}

    /**
     * @param  MediaData  $data
     *
     * @throws ValidationException
     */
    public function handle(UploadedFile $file, ?User $uploader = null, array $data = []): Media
    {
        $this->validate($file, $uploader);

        $mime = (string) $file->getMimeType();
        [$width, $height] = $this->dimensions($file, $mime);

        $folder = Carbon::now()->format($this->folderFormat);
        $name = $this->uniqueName($folder, $file);
        $path = $file->storeAs($folder, $name, ['disk' => $this->disk]);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('The file could not be saved.')]);
        }

        $media = new Media([
            'disk' => $this->disk,
            'path' => $path,
            'filename' => $this->cleanOriginalName($file),
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt' => $data['alt'] ?? null,
            'caption' => $data['caption'] ?? null,
            'meta' => [],
        ]);
        $media->uploaded_by = $uploader?->id;
        $media->save();

        return $media;
    }

    /**
     * Upload rules: a valid file, an allowed type and not too big. Plugins can change them with the UploadRulesFilter.
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file, ?User $uploader = null): void
    {
        $rules = UploadRulesFilter::apply(
            ['required', 'file', 'max:'.$this->maxKilobytes, 'mimetypes:'.implode(',', $this->allowedMimes)],
            $file,
            $uploader,
        );

        Validator::make(['file' => $file], ['file' => $rules])->validate();
    }

    /**
     * Width and height of an image, or nulls for other files.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function dimensions(UploadedFile $file, string $mime): array
    {
        if (MediaType::fromMime($mime) !== MediaType::Image) {
            return [null, null];
        }

        $size = @getimagesize((string) $file->getRealPath());

        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return [null, null];
        }

        return [$size[0], $size[1]];
    }

    // A safe file name that is not taken yet in the folder, like "sunset-over-porto-2.jpg".
    public function uniqueName(string $folder, UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $base = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), self::NAME_MAX_LENGTH, '') ?: 'file';
        $disk = $this->disks->disk($this->disk);

        $name = $base.($extension !== '' ? '.'.$extension : '');
        $counter = 1;

        while ($disk->exists($folder.'/'.$name)) {
            $counter++;
            $name = $base.'-'.$counter.($extension !== '' ? '.'.$extension : '');
        }

        return $name;
    }

    protected function cleanOriginalName(UploadedFile $file): string
    {
        $name = trim(strip_tags($file->getClientOriginalName()));

        return Str::limit($name !== '' ? $name : 'file', 250, '');
    }
}
