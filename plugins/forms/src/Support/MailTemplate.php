<?php

namespace Verso\Plugins\Forms\Support;

use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Models\Submission;

// Fills {placeholders} in email subjects and bodies with the values of a submission.
class MailTemplate
{
    // Fills a template. Unknown placeholders stay as they are.
    public function render(string $template, Submission $submission): string
    {
        $values = $this->values($submission);

        return (string) preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', fn (array $match): string => $values[$match[1]] ?? $match[0], $template);
    }

    // A subject is one line, so line breaks from values are removed.
    public function subject(string $template, Submission $submission): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $this->render($template, $submission)));
    }

    /**
     * @return array<string, string>
     */
    public function values(Submission $submission): array
    {
        $form = $submission->form;
        $values = [
            'form_title' => $form->title,
            'site_name' => (string) config('app.name'),
            'submitted_at' => $submission->created_at?->toDayDateTimeString() ?? '',
            'page_url' => (string) $submission->page_url,
        ];
        $lines = [];

        foreach ($form->formFields() as $field) {
            $text = $submission->text($field->name);
            $values[$field->name] = $text;

            if ($field->type !== FieldType::Hidden) {
                $lines[] = $field->label.': '.($text === '' ? '-' : $text);
            }
        }

        $values['all_fields'] = implode("\n", $lines);

        return $values;
    }
}
