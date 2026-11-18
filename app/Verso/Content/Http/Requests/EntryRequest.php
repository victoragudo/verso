<?php

namespace App\Verso\Content\Http\Requests;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Fields\Support\EntryFieldsForm;
use App\Verso\Fields\Values\FieldValidation;
use App\Verso\Plugins\EntryPanels\EntryPanelRegistry;
use App\Verso\Theme\Templates\PageTemplates;
use App\Verso\Theme\Templates\TemplatePartChoices;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Validates the entry editor form for create and update.
class EntryRequest extends FormRequest
{
    protected ?FieldValidation $fieldValidation = null;

    // The content type of the route, like "post".
    protected function contentTypeName(): ?string
    {
        $type = $this->route('contentType');

        return $type instanceof ContentType ? $type->name : (is_string($type) ? $type : null);
    }

    public function authorize(): bool
    {
        $entry = $this->entry();

        return $entry === null
            ? $this->user()?->can('create', [Entry::class, $this->contentTypeName()]) ?? false
            : $this->user()?->can('update', $entry) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->contentType();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:'.(int) config('verso.content.slug_max_length')],
            'status' => ['required', Rule::in(array_map(fn (EntryStatus $status) => $status->value, $this->allowedStatuses()))],
            'published_at' => ['nullable', 'date', Rule::requiredIf($this->input('status') === EntryStatus::Scheduled->value)],
            'excerpt' => ['nullable', 'string', 'max:'.(int) config('verso.content.excerpt_max_length')],
            'blocks' => ['nullable', 'array', new ValidBlocks($this->user(), stored: $this->entry()->blocks ?? [], allowedBlocks: app(EditorAccess::class)->allowedBlocks($type->name))],
            'parent_id' => $type->hierarchical
                ? ['nullable', 'integer', Rule::exists(Entry::class, 'id')->where('type', $type->name)->whereNull('deleted_at')]
                : ['prohibited'],
            'menu_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['nullable', 'array'],
            'terms.*.*' => ['integer', Rule::exists(Term::class, 'id')],
            ...app(EntryPanelRegistry::class)->rules($type->name),
            ...app(PageTemplates::class)->rules($type->name, $this->entry()),
            ...app(TemplatePartChoices::class)->rules($this->entry()),
        ];
    }

    // The publish date in UTC. The editor sends it in the site time zone.
    public function publishedAt(): ?CarbonImmutable
    {
        if (! $this->filled('published_at') || ! is_string($this->input('published_at'))) {
            return null;
        }

        return app(SiteTime::class)->toUtc($this->string('published_at')->toString());
    }

    // Checks that need other fields: a scheduled date must be in the future and parents must not loop.
    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->fieldValidation()->errors as $key => $message) {
                    $validator->errors()->add($key, $message);
                }

                if ($this->input('status') === EntryStatus::Scheduled->value && ! $validator->errors()->has('published_at') && $this->publishedAt()?->isPast()) {
                    $validator->errors()->add('published_at', __('The scheduled date must be in the future.'));
                }

                $parentId = $this->integer('parent_id');

                if ($parentId === 0 || $validator->errors()->has('parent_id')) {
                    return;
                }

                $entry = $this->entry() ?? new Entry(['type' => $this->contentType()->name]);

                if (! $entry->canHaveParent(Entry::query()->find($parentId))) {
                    $validator->errors()->add('parent_id', __('This page cannot be placed under that parent.'));
                }
            },
        ];
    }

    /**
     * Statuses this user may pick. Users without the publish capability can only save drafts.
     *
     * @return list<EntryStatus>
     */
    public function allowedStatuses(): array
    {
        /** @var User|null $user */
        $user = $this->user();
        $entry = $this->entry();
        $canPublish = $user !== null && ($entry === null ? $user->can('publish', [Entry::class, $this->contentTypeName()]) : $user->can('publish', $entry));

        return $canPublish ? EntryStatus::editable() : [EntryStatus::Draft];
    }

    /**
     * The clean data for the CreateEntry and UpdateEntry actions.
     *
     * @return array{title: string, slug: string|null, status: string, published_at: string|null, excerpt: string|null, blocks: list<array<string, mixed>>, parent_id: int|null, menu_order: int, meta: array<string, mixed>, terms: array<string, list<int>>}
     */
    public function entryData(): array
    {
        // Take the whole blocks array, so nested children and attrs are kept as sent.
        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values((array) $this->input('blocks', []));
        // Take the whole meta, because plugin panel rules would make validated() drop other keys.
        /** @var array<string, mixed> $meta */
        $meta = (array) $this->input('meta', []);
        $fieldValues = $this->fieldValidation()->values;

        if ($fieldValues !== [] || array_key_exists('fields', $meta)) {
            $meta['fields'] = $fieldValues;
        }

        $terms = [];
        foreach ((array) $this->validated('terms', []) as $taxonomy => $ids) {
            $terms[(string) $taxonomy] = array_values(array_map(intval(...), (array) $ids));
        }

        return [
            'title' => (string) $this->validated('title'),
            'slug' => $this->filled('slug') ? (string) $this->validated('slug') : null,
            'status' => (string) $this->validated('status'),
            'published_at' => $this->publishedAt()?->toIso8601String(),
            'excerpt' => $this->filled('excerpt') ? (string) $this->validated('excerpt') : null,
            'blocks' => $blocks,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'menu_order' => $this->integer('menu_order'),
            'meta' => $meta,
            'terms' => $terms,
        ];
    }

    // Custom field values checked against the field groups that show for this entry.
    public function fieldValidation(): FieldValidation
    {
        if ($this->fieldValidation !== null) {
            return $this->fieldValidation;
        }

        $termIds = [];
        foreach ((array) $this->input('terms', []) as $ids) {
            foreach ((array) $ids as $id) {
                if (is_numeric($id)) {
                    $termIds[] = (int) $id;
                }
            }
        }

        /** @var User|null $user */
        $user = $this->user();

        return $this->fieldValidation = app(EntryFieldsForm::class)->validate(
            $this->contentType()->name,
            $user,
            (array) $this->input('meta', []),
            $termIds,
            $this->entry(),
        );
    }

    public function contentType(): ContentType
    {
        /** @var ContentType $type */
        $type = $this->route('contentType');

        return $type;
    }

    public function entry(): ?Entry
    {
        $entry = $this->route('entry');

        return $entry instanceof Entry ? $entry : null;
    }
}
