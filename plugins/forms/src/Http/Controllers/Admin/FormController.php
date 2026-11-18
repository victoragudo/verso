<?php

namespace Verso\Plugins\Forms\Http\Controllers\Admin;

use App\Verso\Shortcodes\ShortcodeRegistry;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\Shortcodes\Usage\UsageIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Forms\Actions\SaveForm;
use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Enums\SubmissionStatus;
use Verso\Plugins\Forms\FormsServiceProvider;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Support\BuilderRules;

// Admin pages to list, build and delete forms.
class FormController
{
    public function __construct(
        protected ShortcodeRegistry $shortcodes,
        protected UsageIndex $usage,
    ) {}

    public function index(): Response
    {
        $forms = Form::query()
            ->withCount([
                'submissions as submissions_count' => fn ($query) => $query->where('status', '!=', SubmissionStatus::Spam->value),
                'submissions as new_count' => fn ($query) => $query->where('status', SubmissionStatus::New->value),
            ])
            ->orderBy('title')
            ->orderBy('id')
            ->get()
            ->map(fn (Form $form): array => [
                'id' => $form->id,
                'title' => $form->title,
                'slug' => $form->slug,
                'fields_count' => count($form->formFields()),
                'submissions_count' => (int) $form->getAttribute('submissions_count'),
                'new_count' => (int) $form->getAttribute('new_count'),
                'updated_at' => $form->updated_at?->toIso8601String(),
                'shortcode' => $this->shortcodes->snippet(FormsServiceProvider::BLOCK, ['slug' => $form->slug]),
                'edit_url' => route('admin.forms.edit', $form),
                'destroy_url' => route('admin.forms.destroy', $form),
                'submissions_url' => route('admin.forms.submissions.index', ['form' => $form->id]),
            ]);

        return Inertia::render('admin/plugins/forms/index', [
            'forms' => $forms,
            'urls' => $this->urls(),
        ]);
    }

    public function create(): Response
    {
        return $this->builder(null);
    }

    public function store(Request $request, BuilderRules $rules, SaveForm $save): RedirectResponse
    {
        $form = $save->handle($request->validate($rules->rules(), $rules->messages()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('forms::Form :title created.', ['title' => $form->title])]);

        return redirect()->route('admin.forms.edit', $form);
    }

    public function edit(Form $form): Response
    {
        return $this->builder($form);
    }

    public function update(Request $request, Form $form, BuilderRules $rules, SaveForm $save): RedirectResponse
    {
        $save->handle($request->validate($rules->rules($form), $rules->messages()), $form);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('forms::Form saved.')]);

        return back();
    }

    // Deleting a form also deletes its submissions and their files.
    public function destroy(Form $form, EmbedInvalidator $embeds): RedirectResponse
    {
        $form->submissions()->orderBy('id')->each(fn ($submission) => $submission->delete());
        $form->delete();
        $embeds->changed(FormsServiceProvider::BLOCK, $form->slug);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('forms::Form :title deleted.', ['title' => $form->title])]);

        return redirect()->route('admin.forms.index');
    }

    protected function builder(?Form $form): Response
    {
        return Inertia::render('admin/plugins/forms/edit', [
            'form' => $form === null ? null : [
                'id' => $form->id,
                'title' => $form->title,
                'slug' => $form->slug,
                'fields' => array_map(fn ($field): array => $field->toArray(), $form->formFields()),
                'settings' => $form->allSettings(),
            ],
            'defaults' => Form::DEFAULT_SETTINGS,
            'fieldTypes' => array_map(fn (FieldType $type): array => ['value' => $type->value, 'label' => $type->label()], FieldType::cases()),
            'limits' => [
                'max_fields' => (int) config('forms.max_fields'),
                'max_options' => (int) config('forms.max_options'),
                'upload_types' => array_values((array) config('forms.upload_types')),
                'upload_max_kb' => (int) config('forms.upload_max_kb'),
                'upload_default_kb' => (int) config('forms.upload_default_kb'),
                'textarea_max' => (int) config('forms.textarea_max'),
            ],
            'embed' => $form === null ? null : '<x-forms-form slug="'.$form->slug.'" />',
            'shortcode' => $form === null ? null : $this->shortcodes->snippet(FormsServiceProvider::BLOCK, ['slug' => $form->slug]),
            'bladeSnippet' => $form === null ? null : $this->shortcodes->bladeSnippet(FormsServiceProvider::BLOCK, ['slug' => $form->slug]),
            'usedIn' => $form === null ? [] : $this->usage->usedIn(FormsServiceProvider::BLOCK, [$form->slug], (int) config('verso.shortcodes.used_in_limit')),
            'urls' => [
                ...$this->urls(),
                'save' => $form === null ? route('admin.forms.store') : route('admin.forms.update', $form),
                'destroy' => $form === null ? null : route('admin.forms.destroy', $form),
                'submissions' => $form === null ? null : route('admin.forms.submissions.index', ['form' => $form->id]),
            ],
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    protected function urls(): array
    {
        return [
            'index' => route('admin.forms.index'),
            'create' => route('admin.forms.create'),
            'inbox' => route('admin.forms.submissions.index'),
            'settings' => auth()->user()?->can('manage_settings') ? route('admin.forms.settings.edit') : null,
        ];
    }
}
