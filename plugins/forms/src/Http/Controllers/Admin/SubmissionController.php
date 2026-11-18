<?php

namespace Verso\Plugins\Forms\Http\Controllers\Admin;

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Core\Support\SiteTime;
use App\Verso\I18n\Translate;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Verso\Plugins\Forms\Actions\EraseSubmitterData;
use Verso\Plugins\Forms\Actions\ExportSubmissions;
use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Enums\SubmissionStatus;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Models\Submission;

// The submissions inbox: list, detail, status changes, CSV export and privacy deletes.
class SubmissionController
{
    // Characters of the first answer shown as a preview in the list.
    protected const PREVIEW_LENGTH = 140;

    public function index(Request $request, SiteTime $siteTime, #[CurrentUser] User $user): Response
    {
        $status = SubmissionStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search', ''));
        $since = (string) $request->query('since', '');
        $since = preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) === 1 ? $since : '';
        $form = $request->integer('form') ? Form::query()->find($request->integer('form')) : null;

        $base = Submission::query()->when($form !== null, fn (Builder $query) => $query->where('form_id', $form?->id));

        $submissions = (clone $base)
            ->with('form')
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status), fn (Builder $query) => $query->inbox())
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['search_text', 'email'], $search))
            // The date is a day in the site time zone, so it starts at local midnight.
            ->when($since !== '', fn (Builder $query) => $query->where('created_at', '>=', $siteTime->toUtc($since.' 00:00:00')))
            ->orderByDesc('id')
            ->paginate((int) config('forms.per_page'))
            ->withQueryString()
            ->through(fn (Submission $submission): array => $this->row($submission));

        $counts = collect(SubmissionStatus::values())->mapWithKeys(fn (string $value): array => [$value => (clone $base)->where('status', $value)->count()]);

        return Inertia::render('admin/plugins/forms/submissions', [
            'submissions' => $submissions,
            'filters' => ['status' => $status->value ?? 'inbox', 'search' => $search, 'form' => $form?->id, 'since' => $since],
            'listView' => AdminPreferences::of($user)->listViewProps('forms.submissions'),
            'counts' => [
                'inbox' => (int) $counts->except(SubmissionStatus::Spam->value)->sum(),
                ...array_combine(SubmissionStatus::values(), array_map(fn (string $value): int => (int) ($counts[$value] ?? 0), SubmissionStatus::values())),
            ],
            'forms' => Form::query()->orderBy('title')->orderBy('id')->get(['id', 'title'])->map(fn (Form $item): array => ['value' => (string) $item->id, 'label' => $item->title]),
            'urls' => [
                'index' => route('admin.forms.submissions.index'),
                'forms' => route('admin.forms.index'),
                'bulk' => route('admin.forms.submissions.bulk'),
                'erase' => route('admin.forms.submissions.erase'),
                'export' => $form !== null ? route('admin.forms.submissions.export', ['form' => $form->id, 'status' => $status?->value]) : null,
            ],
        ]);
    }

    public function show(Submission $submission): Response
    {
        $submission->load('form');
        $submission->markRead();
        $form = $submission->form;
        $files = is_array($submission->files) ? $submission->files : [];

        return Inertia::render('admin/plugins/forms/submission', [
            'submission' => [
                ...$this->row($submission),
                'fields' => array_map(fn ($field): array => [
                    'name' => $field->name,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'value' => $submission->text($field->name),
                    'file' => isset($files[$field->name]) ? [
                        'name' => (string) $files[$field->name]['name'],
                        'size' => (int) $files[$field->name]['size'],
                        'url' => route('admin.forms.submissions.file', ['submission' => $submission->id, 'field' => $field->name]),
                    ] : null,
                ], $form->formFields()),
                'page_url' => $submission->page_url,
                'spam_reason' => $submission->spam_reason,
                'user_agent' => $submission->user_agent,
            ],
            'urls' => [
                'index' => route('admin.forms.submissions.index', ['form' => $form->id]),
                'status' => route('admin.forms.submissions.status', $submission),
                'destroy' => route('admin.forms.submissions.destroy', $submission),
                'erase' => route('admin.forms.submissions.erase'),
            ],
        ]);
    }

    public function status(Request $request, Submission $submission): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(SubmissionStatus::class)]]);
        $status = SubmissionStatus::from((string) $data['status']);
        $submission->moveTo($status);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('forms::Moved to :status.', ['status' => $status->label()])]);

        return back();
    }

    // Change the status of many submissions, or delete them.
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:'.(int) config('forms.per_page')],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in([...SubmissionStatus::values(), 'delete'])],
        ]);

        $submissions = Submission::query()->whereKey($data['ids'])->get();

        foreach ($submissions as $submission) {
            $data['action'] === 'delete' ? $submission->delete() : $submission->moveTo(SubmissionStatus::from((string) $data['action']));
        }

        $count = $submissions->count();
        Inertia::flash('toast', ['type' => 'success', 'message' => $data['action'] === 'delete'
            ? Translate::icu('forms::{count, plural, one {# submission deleted.} other {# submissions deleted.}}', ['count' => $count])
            : Translate::icu('forms::{count, plural, one {# submission moved to {status}.} other {# submissions moved to {status}.}}', ['count' => $count, 'status' => SubmissionStatus::from((string) $data['action'])->label()])]);

        return back();
    }

    public function destroy(Submission $submission): RedirectResponse
    {
        $formId = $submission->form_id;
        $submission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('forms::Submission deleted.')]);

        return redirect()->route('admin.forms.submissions.index', ['form' => $formId]);
    }

    // Privacy request: delete everything one person sent.
    public function erase(Request $request, EraseSubmitterData $erase): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $count = $erase->handle((string) $data['email']);

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('forms::{count, plural, =0 {No submissions from {email}.} one {Deleted # submission from {email}.} other {Deleted # submissions from {email}.}}', ['count' => $count, 'email' => (string) $data['email']])]);

        return redirect()->route('admin.forms.submissions.index');
    }

    public function export(Request $request, ExportSubmissions $export): StreamedResponse
    {
        $data = $request->validate([
            'form' => ['required', 'integer', Rule::exists(Form::class, 'id')],
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
        ]);
        $form = Form::query()->findOrFail((int) $data['form']);
        $status = isset($data['status']) ? SubmissionStatus::from((string) $data['status']) : null;

        return response()->streamDownload(function () use ($export, $form, $status): void {
            $output = fopen('php://output', 'w');

            if ($output !== false) {
                $export->handle($output, $form, $status);
                fclose($output);
            }
        }, $form->slug.'-submissions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Uploaded files are private, so only the admin gets them, always as a download.
    public function file(Submission $submission, string $field): StreamedResponse
    {
        $file = is_array($submission->files) ? ($submission->files[$field] ?? null) : null;
        $disk = Storage::disk((string) config('forms.disk'));

        abort_if(! is_array($file) || ! $disk->exists((string) $file['path']), 404);

        return $disk->download((string) $file['path'], (string) $file['name'], [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Submission $submission): array
    {
        $form = $submission->form;
        $preview = '';

        foreach ($form->formFields() as $field) {
            if (in_array($field->type, [FieldType::Textarea, FieldType::Text], true) && $submission->text($field->name) !== '') {
                $preview = $submission->text($field->name);

                if ($field->type === FieldType::Textarea) {
                    break;
                }
            }
        }

        $nameField = $form->field('name');

        return [
            'id' => $submission->id,
            'status' => $submission->status->value,
            'email' => $submission->email,
            'name' => $nameField !== null ? $submission->text('name') : null,
            'preview' => mb_strimwidth($preview, 0, self::PREVIEW_LENGTH, '…'),
            'form' => ['id' => $form->id, 'title' => $form->title],
            'created_at' => $submission->created_at?->toIso8601String(),
            'files' => is_array($submission->files) ? count($submission->files) : 0,
            'url' => route('admin.forms.submissions.show', $submission),
        ];
    }
}
