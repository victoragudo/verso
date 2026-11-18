<?php

namespace App\Verso\Easy\Http\Requests;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\Rules\ValidBlocks;
use Illuminate\Foundation\Http\FormRequest;

// Checks the blocks the visual page editor sends, with the same rules as the entry editor.
class EasyPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->entry()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $entry = $this->entry();

        return [
            'blocks' => ['present', 'array', new ValidBlocks($this->user(), stored: $entry->blocks ?? [], allowedBlocks: app(EditorAccess::class)->allowedBlocks($entry->type))],
            'publish' => ['sometimes', 'boolean'],
            'confirmEmpty' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blocks.present' => __('The page has no content to save.'),
            'blocks.array' => __('The page could not be read. Undo your last change, then try again.'),
        ];
    }

    /**
     * The blocks as sent, so nested children and attrs are kept.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(): array
    {
        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values((array) $this->input('blocks', []));

        return $blocks;
    }

    public function entry(): Entry
    {
        $entry = $this->route('entry');

        abort_unless($entry instanceof Entry, 404);

        return $entry;
    }
}
