<?php

namespace App\Verso\Plugins\Filters;

use App\Models\User;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use Illuminate\Http\UploadedFile;

// Changes the validation rules of a media upload, for example a smaller size limit or more types.
// Callbacks: fn (array $rules, UploadedFile $file, ?User $uploader): array. The rules are for the "file" field.
final class UploadRulesFilter implements Filter
{
    /**
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    public static function apply(array $rules, UploadedFile $file, ?User $uploader): array
    {
        return array_values((array) app(FilterRegistry::class)->apply(self::class, $rules, $file, $uploader));
    }
}
