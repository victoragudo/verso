<?php

namespace App\Verso\Editor\Rules;

use App\Models\User;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Builder\DropRuleRegistry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\Contracts\Block;
use App\Verso\Editor\Contracts\RequiresFeature;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Editor\Styles\StylePreset;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

// Checks a list of blocks: known types, valid attrs, allowed children, limits and user rights.
class ValidBlocks implements ValidationRule
{
    protected int $count = 0;

    /** @var array<string, true> */
    protected array $ids = [];

    /** @var array<string, array<mixed>>|null */
    protected ?array $storedById = null;

    /**
     * @param  Authorizable|null  $user  user who saves the blocks (the logged in user when null)
     * @param  bool  $trusted  skip capability checks, for system tasks like imports
     * @param  array<mixed>  $stored  blocks saved before; a restricted block kept unchanged from them is allowed
     * @param  list<string>|null  $allowedBlocks  block types this content may hold, null for every type
     */
    public function __construct(
        protected ?Authorizable $user = null,
        protected bool $trusted = false,
        protected array $stored = [],
        protected ?array $allowedBlocks = null,
    ) {}

    // A rule that does not check capabilities, for trusted system code.
    public static function trusted(): self
    {
        return new self(trusted: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            $fail(Translate::text('The content must be a list of blocks.'));

            return;
        }

        $this->count = 0;
        $this->ids = [];

        foreach ($value as $index => $block) {
            if (! $this->check($block, (string) ($index + 1), 1, null, $fail)) {
                return;
            }
        }
    }

    // Checks one block and its children. Returns false to stop at the first error.
    protected function check(mixed $block, string $position, int $depth, ?Block $parent, Closure $fail): bool
    {
        $error = is_array($block) ? $this->error($block, $depth, $parent) : Translate::text('it is not a block.');

        if ($error !== null) {
            $fail(Translate::text('Block :position: :problem', ['position' => $position, 'problem' => $error]));

            return false;
        }

        $type = app(BlockRegistry::class)->find(is_string($block['type'] ?? null) ? $block['type'] : '');
        $children = is_array($block['children'] ?? null) ? $block['children'] : [];

        foreach ($children as $index => $child) {
            if (! $this->check($child, $position.'.'.((int) $index + 1), $depth + 1, $type, $fail)) {
                return false;
            }
        }

        return true;
    }

    // The first problem with a block, or null when it is fine.
    /** @param array<mixed> $block */
    protected function error(array $block, int $depth, ?Block $parent): ?string
    {
        if (++$this->count > (int) config('verso.editor.max_blocks')) {
            return Translate::text('there are too many blocks.');
        }

        if ($depth > (int) config('verso.editor.max_depth')) {
            return Translate::text('blocks are nested too deeply.');
        }

        $id = $block['id'] ?? null;

        if (! is_string($id) || $id === '' || strlen($id) > 64) {
            return Translate::text('the id must be a text of 1 to 64 characters.');
        }

        if (isset($this->ids[$id])) {
            return Translate::text('the id ":id" is used twice.', ['id' => $id]);
        }

        $this->ids[$id] = true;
        $type = is_string($block['type'] ?? null) ? app(BlockRegistry::class)->find($block['type']) : null;

        if ($type === null) {
            return Translate::text('the block type is unknown.');
        }

        if ($this->allowedBlocks !== null && ! in_array($type->name(), $this->allowedBlocks, true) && ! $this->isUnchanged($block)) {
            return Translate::text('":type" blocks are turned off here.', ['type' => $type->name()]);
        }

        if ($parent !== null && ! app(DropRuleRegistry::class)->accepts($parent, $type->name())) {
            return Translate::text('a ":type" block cannot be placed inside a ":parent" block.', ['type' => $type->name(), 'parent' => $parent->name()]);
        }

        if ($parent === null && $type instanceof PluginBlockAdapter && $type->parents() !== null) {
            return Translate::text('a ":type" block must be placed inside a ":parent" block.', ['type' => $type->name(), 'parent' => implode('", "', $type->parents())]);
        }

        if (! $this->mayUse($type) && ! $this->isUnchanged($block)) {
            return Translate::text('you are not allowed to add ":type" blocks.', ['type' => $type->name()]);
        }

        return $this->shapeError($block, $type) ?? $this->inlineError($block);
    }

    // Shortcodes in the text need the same capability as their block.
    /** @param array<mixed> $block */
    protected function inlineError(array $block): ?string
    {
        if (! is_string($block['content'] ?? null) || $this->isUnchanged($block)) {
            return null;
        }

        foreach (app(BlockRenderer::class)->inlineNames($block['content']) as $name) {
            $type = app(BlockRegistry::class)->find($name);

            if ($type !== null && ! $this->mayUse($type)) {
                return Translate::text('you are not allowed to add ":name" shortcodes.', ['name' => $name]);
            }
        }

        return null;
    }

    // Checks the content, children and attrs fields of a block.
    /** @param array<mixed> $block */
    protected function shapeError(array $block, Block $type): ?string
    {
        if (isset($block['content']) && ! is_string($block['content'])) {
            return Translate::text('the content must be text.');
        }

        $children = $block['children'] ?? [];

        if (! is_array($children) || ! array_is_list($children)) {
            return Translate::text('the children must be a list of blocks.');
        }

        $attrs = $block['attrs'] ?? [];

        if (! is_array($attrs) || ($attrs !== [] && array_is_list($attrs))) {
            return Translate::text('the attrs must be an object.');
        }

        $rules = $type->rules();
        $shared = array_values(array_diff([BlockStyleSchema::ATTR, BlockStyleSchema::CUSTOM_CSS_ATTR, BlockStyleSchema::PRESET_ATTR], array_keys($rules)));
        $styleError = $this->styleError($block, Arr::only($attrs, $shared));

        if ($styleError !== null) {
            return $styleError;
        }

        $validator = Validator::make(Arr::except($attrs, $shared), $rules);

        return $validator->fails() ? lcfirst((string) $validator->errors()->first()) : null;
    }

    // True when the stored blocks hold a block with the same id, type, content and attrs.
    /** @param array<mixed> $block */
    protected function isUnchanged(array $block): bool
    {
        $stored = $this->storedBlocks()[(string) $block['id']] ?? null;

        return $stored !== null
            && ($stored['type'] ?? null) === $block['type']
            && (string) ($stored['content'] ?? '') === (string) ($block['content'] ?? '')
            && $this->sameAttrs($stored['attrs'] ?? [], $block['attrs'] ?? []);
    }

    // Attrs match when they hold the same keys and values, in any order.
    protected function sameAttrs(mixed $stored, mixed $sent): bool
    {
        return is_array($stored) && is_array($sent) && $stored == $sent;
    }

    /**
     * The stored blocks and their children, by id.
     *
     * @return array<string, array<mixed>>
     */
    protected function storedBlocks(): array
    {
        if ($this->storedById !== null) {
            return $this->storedById;
        }

        $this->storedById = [];
        $pending = $this->stored;

        while ($pending !== []) {
            $block = array_shift($pending);

            if (! is_array($block)) {
                continue;
            }

            if (is_string($block['id'] ?? null)) {
                $this->storedById[$block['id']] ??= $block;
            }

            if (is_array($block['children'] ?? null)) {
                array_push($pending, ...array_values($block['children']));
            }
        }

        return $this->storedById;
    }

    // Every block may carry attrs.design, unless it uses that name for its own field. Custom CSS needs its own capability.
    /**
     * @param  array<mixed>  $block
     * @param  array<mixed>  $attrs
     */
    protected function styleError(array $block, array $attrs): ?string
    {
        if (array_key_exists(BlockStyleSchema::ATTR, $attrs)) {
            $errors = app(BlockStyleSchema::class)->errors($attrs[BlockStyleSchema::ATTR]);

            if ($errors !== []) {
                return Translate::text('design :problem', ['problem' => $errors[0]]);
            }
        }

        $preset = $attrs[BlockStyleSchema::PRESET_ATTR] ?? null;

        if ($preset !== null && (! is_string($preset) || preg_match(StylePreset::KEY_PATTERN, $preset) !== 1)) {
            return Translate::text('the style preset name is not valid.');
        }

        $customCss = $attrs[BlockStyleSchema::CUSTOM_CSS_ATTR] ?? null;

        if (! array_key_exists(BlockStyleSchema::CUSTOM_CSS_ATTR, $attrs)) {
            return null;
        }

        if (! is_string($customCss) || mb_strlen($customCss) > (int) config('verso.editor.custom_css_max')) {
            return Translate::text('the custom CSS must be a short text.');
        }

        if ($this->trusted || $this->isUnchanged($block)) {
            return null;
        }

        $user = $this->user ?? Auth::user();

        return $user instanceof Authorizable && $user->can((string) config('verso.editor.custom_css_capability'))
            ? null
            : Translate::text('you are not allowed to add custom CSS.');
    }

    protected function mayUse(Block $type): bool
    {
        $capability = $type->capability();
        $user = $this->user ?? Auth::user();

        if ($this->trusted) {
            return true;
        }

        if ($type instanceof RequiresFeature && ! ($user instanceof User && app(EditorAccess::class)->enabled($type->feature(), $user))) {
            return false;
        }

        if ($capability === null) {
            return true;
        }

        return $user instanceof Authorizable && $user->can($capability);
    }
}
