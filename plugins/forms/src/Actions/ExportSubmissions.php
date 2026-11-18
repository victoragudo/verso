<?php

namespace Verso\Plugins\Forms\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Enums\SubmissionStatus;
use Verso\Plugins\Forms\Fields\FormField;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Models\Submission;

// Writes the submissions of one form to a CSV stream, one column per field.
class ExportSubmissions
{
    // Rows read from the database at a time.
    protected const CHUNK = 500;

    /**
     * @param  resource  $output
     */
    public function handle($output, Form $form, ?SubmissionStatus $status = null): void
    {
        $fields = array_values(array_filter($form->formFields(), fn (FormField $field): bool => $field->type !== FieldType::Hidden || $field->value !== ''));

        fputcsv($output, ['id', 'status', 'submitted_at', ...array_map(fn (FormField $field): string => $field->label, $fields)], escape: '\\');

        Submission::query()
            ->where('form_id', $form->id)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderBy('id')
            ->chunk(self::CHUNK, function (Collection $submissions) use ($output, $form, $fields): void {
                foreach ($submissions as $submission) {
                    $submission->setRelation('form', $form);

                    fputcsv($output, [
                        $submission->id,
                        $submission->status->value,
                        $submission->created_at?->toIso8601String(),
                        ...array_map(fn (FormField $field): string => self::safeCell($submission->text($field->name)), $fields),
                    ], escape: '\\');
                }
            });
    }

    // Spreadsheet apps run cells that start with = + - @ as formulas, so those get a quote first.
    public static function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
