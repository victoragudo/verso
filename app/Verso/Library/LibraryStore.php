<?php

namespace App\Verso\Library;

use App\Models\User;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\I18n\Translate;
use App\Verso\Library\Models\LibraryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

// Saves library items and moves them in and out as JSON files.
class LibraryStore
{
    // Version of the export file. Import refuses files of other versions.
    public const int FORMAT = 1;

    // Name of the search words field, and the most words one item keeps.
    protected const string KEYWORDS = 'keywords';

    protected const int MAX_KEYWORDS = 20;

    public function __construct(protected int $maxImport) {}

    /**
     * @param  array{kind?: string, title?: string, category?: string, keywords?: list<string>, blocks?: array<int, mixed>}  $data
     */
    public function save(?LibraryItem $item, array $data, ?User $user): LibraryItem
    {
        $item ??= new LibraryItem(['kind' => LibraryItem::PATTERN, 'keywords' => [], 'category' => '', 'blocks' => []]);
        $item->fill([
            'kind' => $data['kind'] ?? $item->kind,
            'title' => $data['title'] ?? $item->title,
            'category' => $data['category'] ?? $item->category,
            'keywords' => array_slice(array_filter(array_map(fn ($word): string => mb_substr(trim((string) $word), 0, 40), $data['keywords'] ?? $item->keywords)), 0, self::MAX_KEYWORDS),
            'blocks' => array_values($data['blocks'] ?? $item->blocks),
            'user_id' => $user->id ?? $item->user_id,
        ])->save();

        LibraryChanged::dispatch($item->id, $item->kind);

        return $item;
    }

    public function delete(LibraryItem $item): void
    {
        $item->delete();
        LibraryChanged::dispatch($item->id, $item->kind);
    }

    /**
     * Items as a JSON ready array: every item, or only some.
     *
     * @param  list<int>  $ids
     * @return array{format: int, items: list<array<string, mixed>>}
     */
    public function export(array $ids = []): array
    {
        $items = LibraryItem::query()->when($ids !== [], fn ($query) => $query->whereKey($ids))->orderBy('id')->get();

        return [
            'format' => self::FORMAT,
            'items' => array_values($items->map(fn (LibraryItem $item): array => [
                'kind' => $item->kind,
                'title' => $item->title,
                'category' => $item->category,
                'keywords' => $item->keywords,
                'blocks' => $item->blocks,
            ])->all()),
        ];
    }

    /**
     * Adds the items of an export file. Blocks are checked as if the person added them by hand.
     *
     * @param  array<mixed>  $data
     */
    public function import(array $data, User $user): int
    {
        if (($data['format'] ?? null) !== self::FORMAT || ! is_array($data['items'] ?? null)) {
            throw new InvalidArgumentException(Translate::text('This is not a library file of Verso.'));
        }

        if (count($data['items']) > $this->maxImport) {
            throw new InvalidArgumentException(Translate::text('The file has too many items. The most is :max.', ['max' => $this->maxImport]));
        }

        $clean = [];

        foreach (array_values($data['items']) as $index => $item) {
            $validator = Validator::make(is_array($item) ? $item : [], [
                'kind' => ['required', 'in:'.implode(',', LibraryItem::KINDS)],
                'title' => ['required', 'string', 'max:120'],
                'category' => ['nullable', 'string', 'max:60'],
                self::KEYWORDS => ['nullable', 'array', 'max:'.self::MAX_KEYWORDS],
                self::KEYWORDS.'.*' => ['string', 'max:40'],
                'blocks' => ['present', 'array', new ValidBlocks($user)],
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(Translate::text('Item :number: :problem', ['number' => $index + 1, 'problem' => $validator->errors()->first()]));
            }

            $clean[] = $validator->validated();
        }

        DB::transaction(function () use ($clean, $user): void {
            foreach ($clean as $item) {
                $this->save(null, [...$item, 'category' => (string) ($item['category'] ?? ''), 'keywords' => $item['keywords'] ?? []], $user);
            }
        });

        return count($clean);
    }
}
