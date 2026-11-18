<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Enums\MenuItemVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the whole item tree sent by the menu builder.
 *
 * @phpstan-import-type MenuItemData from SyncMenuItems
 */
class MenuItemsRequest extends FormRequest
{
    // Custom links may be local paths, web links, mail or phone links, or anchors.
    public const URL_PATTERN = '#^(/|https?://|mailto:|tel:|\#)#i';

    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageMenus->value) ?? false;
    }

    /**
     * Rules for every level of the tree, down to the max depth. Deeper levels are refused.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['items' => ['present', 'array']];
        $maxDepth = max((int) config('verso.menus.max_depth'), 1);
        $prefix = 'items.*';

        for ($level = 1; $level <= $maxDepth; $level++) {
            $rules += $this->itemRules($prefix);
            $rules[$prefix.'.children'] = $level === $maxDepth ? ['nullable', 'array', 'max:0'] : ['nullable', 'array'];
            $prefix .= '.children.*';
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.children.max' => Translate::icu('Menus can be at most {count, plural, one {# level} other {# levels}} deep.', ['count' => (int) config('verso.menus.max_depth')]),
            '*.url.regex' => __('Use a path starting with /, a full http(s) URL, mailto:, tel: or #.'),
        ];
    }

    /**
     * @return list<MenuItemData>
     */
    public function items(): array
    {
        /** @var list<MenuItemData> $items */
        $items = array_values((array) $this->validated('items', []));

        return $items;
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function itemRules(string $prefix): array
    {
        return [
            $prefix.'.type' => ['required', Rule::enum(MenuItemType::class)],
            $prefix.'.entry_id' => ['nullable', 'required_if:'.$prefix.'.type,entry', 'integer', Rule::exists(Entry::class, 'id')->whereNull('deleted_at')],
            $prefix.'.term_id' => ['nullable', 'required_if:'.$prefix.'.type,term', 'integer', Rule::exists(Term::class, 'id')],
            $prefix.'.label' => ['nullable', 'required_if:'.$prefix.'.type,custom', 'string', 'max:255'],
            $prefix.'.url' => ['nullable', 'required_if:'.$prefix.'.type,custom', 'string', 'max:2048', 'regex:'.self::URL_PATTERN],
            $prefix.'.open_in_new_tab' => ['boolean'],
            $prefix.'.mega' => ['boolean'],
            $prefix.'.rel' => ['nullable', 'array'],
            $prefix.'.rel.*' => ['string', Rule::in(SyncMenuItems::REL_WORDS)],
            $prefix.'.visibility' => ['nullable', Rule::enum(MenuItemVisibility::class)],
        ];
    }
}
