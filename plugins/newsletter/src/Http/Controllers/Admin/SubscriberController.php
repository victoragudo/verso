<?php

namespace Verso\Plugins\Newsletter\Http\Controllers\Admin;

use App\Verso\I18n\Translate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Verso\Plugins\Newsletter\Actions\ExportSubscribers;
use Verso\Plugins\Newsletter\Actions\ImportSubscribers;
use Verso\Plugins\Newsletter\Actions\Subscribe;
use Verso\Plugins\Newsletter\Enums\SubscriberStatus;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Models\Subscriber;

// Admin page for subscribers and lists, with CSV import and export.
class SubscriberController
{
    public function index(Request $request): Response
    {
        $status = SubscriberStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search', ''));
        $listId = $request->integer('list') ?: null;

        $subscribers = Subscriber::query()
            ->with('lists:id,name')
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($listId !== null, fn (Builder $query) => $query->whereHas('lists', fn (Builder $q) => $q->whereKey($listId)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate((int) config('newsletter.per_page'))
            ->withQueryString()
            ->through(fn (Subscriber $subscriber): array => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'name' => $subscriber->name,
                'status' => $subscriber->status->value,
                'source' => $subscriber->source,
                'lists' => $subscriber->lists->pluck('name')->all(),
                'created_at' => $subscriber->created_at?->toIso8601String(),
            ]);

        $counts = Subscriber::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/plugins/newsletter/subscribers', [
            'subscribers' => $subscribers,
            'filters' => ['status' => $status->value ?? 'all', 'search' => $search, 'list' => $listId],
            'counts' => [
                'all' => (int) $counts->sum(),
                ...array_combine(
                    array_map(fn (SubscriberStatus $case): string => $case->value, SubscriberStatus::cases()),
                    array_map(fn (SubscriberStatus $case): int => (int) ($counts[$case->value] ?? 0), SubscriberStatus::cases()),
                ),
            ],
            'lists' => MailingList::query()->withCount('subscribers')->orderBy('name')->get(['id', 'name', 'slug']),
            'urls' => [
                'index' => route('admin.newsletter.subscribers.index'),
                'store' => route('admin.newsletter.subscribers.store'),
                'import' => route('admin.newsletter.subscribers.import'),
                'export' => route('admin.newsletter.subscribers.export'),
                'subscriber' => route('admin.newsletter.subscribers.destroy', ['subscriber' => '__ID__']),
                'lists' => route('admin.newsletter.lists.store'),
                'campaigns' => route('admin.newsletter.campaigns.index'),
            ],
        ]);
    }

    // Add one person by hand. They still confirm by email.
    public function store(Request $request, Subscribe $subscribe): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'list_id' => ['nullable', 'integer', Rule::exists(MailingList::class, 'id')],
        ]);

        $list = isset($data['list_id']) ? MailingList::query()->find((int) $data['list_id']) : null;
        $subscriber = $subscribe->handle((string) $data['email'], $data['name'] ?? null, $list);

        Inertia::flash('toast', ['type' => 'success', 'message' => $subscriber->isSubscribed()
            ? __('newsletter:::email is already subscribed.', ['email' => $subscriber->email])
            : __('newsletter::We sent a confirmation email to :email.', ['email' => $subscriber->email])]);

        return back();
    }

    public function import(Request $request, ImportSubscribers $import): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(int) config('newsletter.import_max_kb')],
            'list_id' => ['nullable', 'integer', Rule::exists(MailingList::class, 'id')],
            'consent' => ['accepted'],
        ], ['consent.accepted' => __('newsletter::Confirm that these people agreed to get the newsletter.')]);

        $list = isset($data['list_id']) ? MailingList::query()->find((int) $data['list_id']) : null;
        $result = $import->handle($request->file('file')->getRealPath(), $list);

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('newsletter::{created} added, {updated} updated, {skipped} skipped.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ])]);

        return back();
    }

    public function export(ExportSubscribers $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'w');

            if ($output !== false) {
                $export->handle($output);
                fclose($output);
            }
        }, 'subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function unsubscribe(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->unsubscribe();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter:::email is unsubscribed.', ['email' => $subscriber->email])]);

        return back();
    }

    // Deleting removes the address and its history, for privacy requests.
    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter:::email was deleted.', ['email' => $subscriber->email])]);

        return back();
    }

    public function storeList(Request $request): RedirectResponse
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', Rule::unique(MailingList::class, 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
        ], ['slug.unique' => __('newsletter::A list with this name already exists.')]);

        MailingList::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::List :name created.', ['name' => (string) $data['name']])]);

        return back();
    }

    public function destroyList(MailingList $list): RedirectResponse
    {
        $list->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('newsletter::List :name deleted. Its subscribers stay.', ['name' => $list->name])]);

        return back();
    }
}
