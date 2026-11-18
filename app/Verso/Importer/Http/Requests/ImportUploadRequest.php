<?php

namespace App\Verso\Importer\Http\Requests;

use App\Verso\Core\Enums\Capability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

// Validates the uploaded WordPress export file.
class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ImportContent->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xml', 'max:'.(int) config('verso.importer.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => __('Upload the .xml file from Tools > Export in WordPress.'),
        ];
    }

    public function exportFile(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : abort(422);
    }
}
