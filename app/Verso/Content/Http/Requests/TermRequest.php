<?php

namespace App\Verso\Content\Http\Requests;

use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Validates the term form for create and update.
class TermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $term = $this->term();

        return $term === null
            ? $this->user()?->can('create', Term::class) ?? false
            : $this->user()?->can('update', $term) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taxonomy = $this->taxonomy();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:'.(int) config('verso.content.slug_max_length')],
            'description' => ['nullable', 'string', 'max:'.(int) config('verso.content.excerpt_max_length')],
            'parent_id' => $taxonomy->hierarchical
                ? ['nullable', 'integer', Rule::exists(Term::class, 'id')->where('taxonomy', $taxonomy->name)]
                : ['prohibited'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $term = $this->term();
                $parentId = $this->integer('parent_id');

                if ($term === null || $parentId === 0 || $validator->errors()->has('parent_id')) {
                    return;
                }

                if (! $term->canHaveParent(Term::query()->find($parentId))) {
                    $validator->errors()->add('parent_id', __('A term cannot be placed under itself or its children.'));
                }
            },
        ];
    }

    /**
     * @return array{name: string, slug: string|null, description: string|null, parent_id: int|null}
     */
    public function termData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'slug' => $this->filled('slug') ? (string) $this->validated('slug') : null,
            'description' => $this->filled('description') ? (string) $this->validated('description') : null,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
        ];
    }

    public function taxonomy(): Taxonomy
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = $this->route('taxonomy');

        return $taxonomy;
    }

    public function term(): ?Term
    {
        $term = $this->route('term');

        return $term instanceof Term ? $term : null;
    }
}
