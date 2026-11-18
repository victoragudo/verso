<?php

namespace Verso\Plugins\Forms\Actions;

use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use Verso\Plugins\Forms\Fields\FormField;
use Verso\Plugins\Forms\FormsServiceProvider;
use Verso\Plugins\Forms\Models\Form;

// Creates or updates a form from the builder data.
class SaveForm
{
    /**
     * @param  array<string, mixed>  $data  title, slug, fields and settings, already validated
     */
    public function handle(array $data, ?Form $form = null): Form
    {
        $form ??= new Form;

        $fields = array_values(array_filter(array_map(
            fn ($field): ?FormField => is_array($field) ? FormField::fromArray($field) : null,
            is_array($data['fields'] ?? null) ? $data['fields'] : [],
        )));

        $settings = array_intersect_key(is_array($data['settings'] ?? null) ? $data['settings'] : [], Form::DEFAULT_SETTINGS);
        $settings['notify_emails'] = array_values(array_unique(array_filter(array_map(
            fn ($email): string => mb_strtolower(trim((string) $email)),
            is_array($settings['notify_emails'] ?? null) ? $settings['notify_emails'] : [],
        ))));
        $settings['confirmation_enabled'] = (bool) ($settings['confirmation_enabled'] ?? false);

        $oldSlug = (string) $form->getOriginal('slug');

        $form->fill([
            'title' => (string) $data['title'],
            'slug' => (string) $data['slug'],
            'fields' => array_map(fn (FormField $field): array => $field->toArray(), $fields),
            'settings' => [...Form::DEFAULT_SETTINGS, ...array_map(fn ($value) => $value ?? '', $settings)],
        ])->save();

        // Pages that embed this form (by its old or new slug) must show the new version.
        app(EmbedInvalidator::class)->changed(FormsServiceProvider::BLOCK, $oldSlug, $form->slug);

        return $form;
    }
}
