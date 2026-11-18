<?php

namespace Verso\Plugins\Newsletter\Actions;

use Verso\Plugins\Newsletter\Models\Subscriber;

// Writes all subscribers to a CSV stream: email, name, status, lists and dates.
class ExportSubscribers
{
    // Header row of the export file.
    public const COLUMNS = ['email', 'name', 'status', 'lists', 'source', 'confirmed_at', 'created_at'];

    /**
     * @param  resource  $output
     */
    public function handle($output): void
    {
        fputcsv($output, self::COLUMNS, escape: '\\');

        Subscriber::query()->with('lists:id,name')->orderBy('id')->chunk(500, function ($subscribers) use ($output): void {
            foreach ($subscribers as $subscriber) {
                fputcsv($output, [
                    $subscriber->email,
                    $subscriber->name,
                    $subscriber->status->value,
                    $subscriber->lists->pluck('name')->implode('|'),
                    $subscriber->source,
                    $subscriber->confirmed_at?->toIso8601String(),
                    $subscriber->created_at?->toIso8601String(),
                ], escape: '\\');
            }
        });
    }
}
