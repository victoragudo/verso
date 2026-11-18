<?php

namespace Verso\Plugins\Newsletter\Actions;

use Verso\Plugins\Newsletter\Enums\SubscriberStatus;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Models\Subscriber;

// Reads a CSV with an "email" column (and optional "name") and adds the people as subscribed.
class ImportSubscribers
{
    /**
     * The admin confirms that these people agreed to get the newsletter.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(string $path, ?MailingList $list = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $result;
        }

        $header = $this->header(fgetcsv($handle, escape: '\\') ?: []);
        $emailColumn = $header['email'] ?? 0;
        $nameColumn = $header['name'] ?? null;
        $isHeader = isset($header['email']);

        if (! $isHeader) {
            rewind($handle);
        }

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $email = mb_strtolower(trim((string) ($row[$emailColumn] ?? '')));

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $result['skipped']++;

                continue;
            }

            $subscriber = Subscriber::query()->firstOrNew(['email' => $email]);
            $result[$subscriber->exists ? 'updated' : 'created']++;

            $name = $nameColumn !== null ? trim((string) ($row[$nameColumn] ?? '')) : '';
            $subscriber->name = $name !== '' ? $name : $subscriber->name;

            // People who left on their own stay unsubscribed.
            if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
                $subscriber->status = SubscriberStatus::Subscribed;
                $subscriber->confirmed_at ??= now();
            }

            $subscriber->source = $subscriber->exists ? $subscriber->source : 'import';
            $subscriber->save();

            if ($list !== null) {
                $subscriber->lists()->syncWithoutDetaching([$list->id]);
            }
        }

        fclose($handle);

        return $result;
    }

    /**
     * Column positions by lower case header name.
     *
     * @param  array<int, string|null>  $row
     * @return array<string, int>
     */
    protected function header(array $row): array
    {
        $columns = [];

        foreach ($row as $index => $value) {
            $columns[mb_strtolower(trim((string) $value, " \t\n\r\0\x0B\u{FEFF}"))] = $index;
        }

        return $columns;
    }
}
