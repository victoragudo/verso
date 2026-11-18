<?php

namespace Verso\Plugins\AiAssistant\Support;

// Turns entry blocks into plain text, so the model can read the entry.
class BlockText
{
    /**
     * @param  array<mixed>  $blocks
     */
    public function plain(array $blocks): string
    {
        $lines = [];

        $this->collect($blocks, $lines);

        return trim(implode("\n\n", $lines));
    }

    /**
     * @param  array<mixed>  $blocks
     * @param  list<string>  $lines
     */
    protected function collect(array $blocks, array &$lines): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $text = $this->text(is_string($block['content'] ?? null) ? $block['content'] : '');

            if ($text !== '' && ! in_array($block['type'] ?? '', ['code', 'html'], true)) {
                $lines[] = ($block['type'] ?? '') === 'heading' ? '## '.$text : $text;
            }

            if (is_array($block['children'] ?? null)) {
                $this->collect($block['children'], $lines);
            }
        }
    }

    // Inline HTML to clean text. List items and paragraphs become new lines.
    public function text(string $html): string
    {
        $html = (string) preg_replace('/<\/(p|li|h[1-6])>|<br\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n\s*\n+/', "\n", $text);

        return trim($text);
    }
}
