<?php

namespace App\Verso\Api\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Query string of the entry list endpoint: paging, search, author, sort and term filters.
class EntryIndexRequest extends FormRequest
{
    // Allowed values for ?sort. A leading dash sorts from high to low.
    public const SORTS = ['published_at', '-published_at', 'title', '-title', 'menu_order', '-menu_order'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('verso.api.max_per_page')],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'author' => ['sometimes', 'integer'],
            'parent' => ['sometimes', 'integer'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', self::SORTS)],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', (int) config('verso.api.per_page'));
    }

    // The column and direction for ORDER BY.
    /** @return array{0: string, 1: 'asc'|'desc'} */
    public function sort(): array
    {
        $sort = (string) $this->string('sort', '-published_at');

        return str_starts_with($sort, '-') ? [substr($sort, 1), 'desc'] : [$sort, 'asc'];
    }

    public function search(): string
    {
        return trim((string) $this->string('search'));
    }
}
