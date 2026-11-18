<?php

namespace Tests\Support;

use App\Models\User;
use App\Verso\Editor\Rules\ValidBlocks;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Helpers for Easy mode tests: block checks and small, fast sample pictures.
class EasyFixtures
{
    // Small pictures keep the starter tests fast. The media disk is faked.
    public static function fastImages(): void
    {
        config([
            'verso.easy.image_width' => 48,
            'verso.easy.image_height' => 32,
            'verso.easy.portrait_size' => 32,
        ]);
        Storage::fake((string) config('verso.media.disk'));
    }

    /**
     * The first problem ValidBlocks finds, or null when the blocks are fine for an admin.
     *
     * @param  array<mixed>  $blocks
     */
    public static function blockError(array $blocks, ?User $user = null): ?string
    {
        $validator = Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks($user ?? User::factory()->admin()->create())]]);

        return $validator->fails() ? (string) $validator->errors()->first('blocks') : null;
    }

    /**
     * Every block id at any depth.
     *
     * @param  array<mixed>  $blocks
     * @return list<string>
     */
    public static function ids(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            $ids[] = (string) $block['id'];
            $ids = [...$ids, ...self::ids($block['children'] ?? [])];
        }

        return $ids;
    }

    /**
     * Every "var:" token used in attrs.design at any depth.
     *
     * @param  array<mixed>  $blocks
     * @return list<string>
     */
    public static function tokens(array $blocks): array
    {
        $tokens = [];

        foreach ($blocks as $block) {
            $design = $block['attrs']['design'] ?? [];
            array_walk_recursive($design, function (mixed $value) use (&$tokens): void {
                if (is_string($value) && str_starts_with($value, 'var:')) {
                    $tokens[] = $value;
                }
            });
            $tokens = [...$tokens, ...self::tokens($block['children'] ?? [])];
        }

        return array_values(array_unique($tokens));
    }
}
