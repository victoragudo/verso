<?php

namespace App\Verso\Layouts\Conditions;

// One kind of rule that says where a layout shows, like "Content type is Post" or "Category is Recipes".
interface LayoutCondition
{
    // Key stored in the rule, like "content_type".
    public function key(): string;

    // Plain name for the editor, in English (translated when sent).
    public function label(): string;

    // How much more specific than other rules it is. The most specific matching layout wins.
    public function weight(): int;

    /**
     * The values people can pick, or null when the rule takes no value.
     *
     * @return list<array{value: string, label: string}>|null
     */
    public function options(): ?array;

    public function matches(PageContext $page, string $value): bool;
}
