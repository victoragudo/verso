<?php

namespace App\Verso\Importer\Content;

use App\Verso\Shortcodes\Parsing\ShortcodeNode;
use App\Verso\Shortcodes\ShortcodeExpander;
use App\Verso\Shortcodes\ShortcodeRegistry;
use Illuminate\Support\Str;

// Maps WordPress shortcodes alone on a line to Verso blocks: [contact-form-7], [audio] and [video].
// [gallery], [embed] and [caption] are read by the classic converter. Other shortcodes stay as text.
class ShortcodeMapper
{
    // Block name of the Forms plugin, used when it is enabled.
    protected const FORM_BLOCK = 'form';

    public function __construct(
        protected ShortcodeExpander $expander,
        protected ShortcodeRegistry $shortcodes,
    ) {}

    // The one shortcode a chunk holds, or null when the chunk has other text too.
    public function single(string $chunk): ?ShortcodeNode
    {
        $nodes = $this->expander->parser()->parse(trim($chunk));

        return count($nodes) === 1 && $nodes[0] instanceof ShortcodeNode ? $nodes[0] : null;
    }

    /** @return array<string, mixed>|null */
    public function map(string $chunk, BlockBuilder $builder): ?array
    {
        $node = $this->single($chunk);

        return match ($node?->name) {
            'contact-form-7' => $this->contactForm($node, $builder),
            'audio' => $this->audio($node, $builder),
            'video' => $this->video($node, $builder),
            default => null,
        };
    }

    // A Forms plugin form with the imported title or id as slug, or a notice for the editor.
    /** @return array<string, mixed> */
    protected function contactForm(ShortcodeNode $node, BlockBuilder $builder): array
    {
        $id = $this->text($node, 'id');
        $title = $this->text($node, 'title');
        $definition = $this->shortcodes->find(self::FORM_BLOCK);
        $candidates = array_values(array_filter([Str::slug($title), $id !== '' ? 'contact-form-'.$id : '', $id]));

        foreach ($definition === null ? [] : $candidates as $slug) {
            if ($definition->validate(['form' => $slug])['errors'] === []) {
                return $builder->make(self::FORM_BLOCK, ['form' => $slug, 'title' => '', 'text' => '']);
            }
        }

        return $builder->paragraph(e('A Contact Form 7 form'.($title !== '' ? ' "'.$title.'"' : '').($id !== '' ? ' (id '.$id.')' : '')
            .' was here. Build it again under Forms and add the Form block here.'));
    }

    /** @return array<string, mixed>|null */
    protected function audio(ShortcodeNode $node, BlockBuilder $builder): ?array
    {
        $src = $this->firstUrl($node, ['src', 'mp3', 'm4a', 'ogg', 'wav']);

        return $src === null ? null : $builder->html('<audio controls preload="none" src="'.e($src).'"></audio>');
    }

    /** @return array<string, mixed>|null */
    protected function video(ShortcodeNode $node, BlockBuilder $builder): ?array
    {
        $src = $this->firstUrl($node, ['src', 'mp4', 'm4v', 'webm', 'ogv']);

        if ($src === null) {
            return null;
        }

        $poster = $this->text($node, 'poster');

        return $builder->make('video', array_filter([
            'src' => $src,
            'poster' => str_starts_with($poster, 'http') ? $poster : null,
            'loop' => $this->text($node, 'loop') === 'on' ? true : null,
            'autoplay' => $this->text($node, 'autoplay') === 'on' ? true : null,
        ], fn ($value) => $value !== null));
    }

    /** @param list<string> $keys */
    protected function firstUrl(ShortcodeNode $node, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->text($node, $key);

            if (preg_match('#^https?://#i', $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    protected function text(ShortcodeNode $node, string $key): string
    {
        $value = $node->attrs[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Shortcodes in the converted blocks that no plugin knows. They stay as text.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    public function unknown(array $blocks): array
    {
        $names = [];

        foreach ($blocks as $block) {
            if (is_string($block['content'] ?? null) && ($block['type'] ?? '') !== 'code') {
                foreach ($this->expander->parser()->all($block['content']) as $node) {
                    if (! $this->shortcodes->has($node->name)) {
                        $names[] = $node->name;
                    }
                }
            }

            if (is_array($block['children'] ?? null)) {
                $names = [...$names, ...$this->unknown(array_values(array_filter($block['children'], is_array(...))))];
            }
        }

        return array_values(array_unique($names));
    }
}
