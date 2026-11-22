<?php

namespace Verso\Plugins\Forms\Models;

use App\Verso\Core\Database\JsonArray;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Fields\FormField;

// A form built in the admin: its fields and what happens after someone sends it.
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property array<mixed>|null $fields
 * @property array<mixed>|null $settings
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Form extends Model
{
    protected $table = 'forms';

    protected $fillable = ['title', 'slug', 'fields', 'settings'];

    // Settings every form has, with their default values.
    public const DEFAULT_SETTINGS = [
        'submit_label' => 'Send',
        'success_message' => 'Thank you! Your message was sent.',
        'redirect_url' => '',
        'notify_emails' => [],
        'notify_subject' => 'New message from {form_title}',
        'notify_body' => "You got a new message:\n\n{all_fields}",
        'confirmation_enabled' => false,
        'confirmation_subject' => 'We got your message',
        'confirmation_body' => "Hello,\n\nThank you for writing to us. We will answer soon.\n\n{all_fields}",
    ];

    protected function casts(): array
    {
        return [
            'fields' => JsonArray::class,
            'settings' => JsonArray::class,
        ];
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * The valid fields of the form, in order. Broken rows are skipped.
     *
     * @return list<FormField>
     */
    public function formFields(): array
    {
        return array_values(array_filter(array_map(
            fn ($field): ?FormField => is_array($field) ? FormField::fromArray($field) : null,
            is_array($this->fields) ? $this->fields : [],
        )));
    }

    public function field(string $name): ?FormField
    {
        foreach ($this->formFields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function setting(string $key): mixed
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return $settings[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    // A text visitors read. A default that was never changed shows in the site language.
    public function visitorText(string $key): string
    {
        $value = (string) $this->setting($key);

        if ($value !== (self::DEFAULT_SETTINGS[$key] ?? null)) {
            return $value;
        }

        return match ($key) {
            'submit_label' => __('forms::Send'),
            'success_message' => __('forms::Thank you! Your message was sent.'),
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function allSettings(): array
    {
        return [...self::DEFAULT_SETTINGS, ...array_intersect_key(is_array($this->settings) ? $this->settings : [], self::DEFAULT_SETTINGS)];
    }

    /**
     * Valid notification addresses.
     *
     * @return list<string>
     */
    public function notifyEmails(): array
    {
        $emails = $this->setting('notify_emails');

        return array_values(array_filter(
            is_array($emails) ? array_map('strval', $emails) : [],
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    // The first email field, used for reply-to, the confirmation email and privacy requests.
    public function emailField(): ?FormField
    {
        foreach ($this->formFields() as $field) {
            if ($field->type === FieldType::Email) {
                return $field;
            }
        }

        return null;
    }

    public function hasFileFields(): bool
    {
        foreach ($this->formFields() as $field) {
            if ($field->type === FieldType::File) {
                return true;
            }
        }

        return false;
    }

    // The id of the form element on public pages, also used as the link anchor.
    public function anchor(): string
    {
        return 'form-'.$this->slug;
    }
}
