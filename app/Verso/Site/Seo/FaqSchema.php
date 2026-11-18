<?php

namespace App\Verso\Site\Seo;

// Reads questions and answers from the blocks of a page for a schema.org FAQPage node.
// A question is a "details" element block whose first child is a "summary", or an item of a "faq" block.
class FaqSchema
{
    public const FAQ_BLOCK = 'faq';

    public function __construct(protected int $maxQuestions) {}

    /**
     * The FAQPage node, or null when the page has no questions.
     *
     * @param  array<mixed>  $blocks
     * @return array<string, mixed>|null
     */
    public function node(array $blocks, string $url): ?array
    {
        $questions = $this->questions($blocks);

        if ($questions === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $url.'#faq',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $questions),
        ];
    }

    /**
     * @param  array<mixed>  $blocks
     * @return list<array{question: string, answer: string}>
     */
    public function questions(array $blocks): array
    {
        $found = [];
        $this->collect($blocks, $found);

        return array_slice($found, 0, max(0, $this->maxQuestions));
    }

    /**
     * @param  array<mixed>  $blocks
     * @param  list<array{question: string, answer: string}>  $found
     */
    protected function collect(array $blocks, array &$found): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
            $children = is_array($block['children'] ?? null) ? $block['children'] : [];

            if ($type === self::FAQ_BLOCK) {
                array_push($found, ...$this->fromFaqBlock($attrs));

                continue;
            }

            if ($type === 'element' && ($attrs['tag'] ?? null) === 'details') {
                $item = $this->fromDetails($block, $children);

                if ($item !== null) {
                    $found[] = $item;

                    continue;
                }
            }

            $this->collect($children, $found);
        }
    }

    /**
     * @param  array<mixed>  $attrs
     * @return list<array{question: string, answer: string}>
     */
    protected function fromFaqBlock(array $attrs): array
    {
        $items = [];

        foreach (is_array($attrs['items'] ?? null) ? $attrs['items'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = $this->text($item['question'] ?? null);
            $answer = $this->text($item['answer'] ?? null);

            if ($question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }

    /**
     * @param  array<mixed>  $block
     * @param  array<mixed>  $children
     * @return array{question: string, answer: string}|null
     */
    protected function fromDetails(array $block, array $children): ?array
    {
        $question = '';
        $answer = [$this->text($block['content'] ?? null)];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $isSummary = ($child['type'] ?? null) === 'element' && (($child['attrs']['tag'] ?? null) === 'summary');

            if ($isSummary && $question === '') {
                $question = $this->text($child['content'] ?? null);

                continue;
            }

            $answer[] = $this->plainText($child);
        }

        $answer = trim(implode(' ', array_filter($answer, fn (string $part): bool => $part !== '')));

        return $question !== '' && $answer !== '' ? ['question' => $question, 'answer' => $answer] : null;
    }

    // The text of a block and its children, without tags.
    /** @param array<mixed> $block */
    protected function plainText(array $block): string
    {
        $parts = [$this->text($block['content'] ?? null)];

        foreach (is_array($block['children'] ?? null) ? $block['children'] : [] as $child) {
            if (is_array($child)) {
                $parts[] = $this->plainText($child);
            }
        }

        return trim(implode(' ', array_filter($parts, fn (string $part): bool => $part !== '')));
    }

    protected function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
