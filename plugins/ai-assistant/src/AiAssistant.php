<?php

namespace Verso\Plugins\AiAssistant;

use App\Models\User;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Verso\Plugins\AiAssistant\Contracts\AiProvider;
use Verso\Plugins\AiAssistant\Exceptions\AiInputTooLong;
use Verso\Plugins\AiAssistant\Exceptions\AiProviderError;
use Verso\Plugins\AiAssistant\Support\BlockText;
use Verso\Plugins\AiAssistant\Support\BlockTranslation;

// The editor actions. Each one checks the guard, asks the provider, logs the tokens and returns a preview.
class AiAssistant
{
    // How a selected text can be rewritten.
    public const REWRITE_MODES = ['improve', 'shorten'];

    /**
     * @param  array{max_input_chars: int, max_image_bytes: int, max_translation_segments: int, max_section_pieces: int}  $limits
     * @param  array{excerpt_words: int, seo_title_chars: int, seo_description_chars: int, alt_chars: int, headline_count: int, headline_chars: int}  $targets
     * @param  list<string>  $imageTypes
     * @param  array<string, string>  $locales
     */
    public function __construct(
        protected AiProvider $provider,
        protected AiGuard $guard,
        protected UsageLog $usage,
        protected BlockText $blockText,
        protected BlockTranslation $translation,
        protected BlockTranslation $sectionPieces,
        protected array $limits,
        protected array $targets,
        protected array $imageTypes,
        protected array $locales,
    ) {}

    /**
     * Headings with a few points each, as heading and list blocks.
     *
     * @return array{blocks: list<array<string, mixed>>}
     */
    public function outline(User $user, string $title, string $notes = ''): array
    {
        $text = 'Title: '.$title.($notes !== '' ? "\n\nNotes from the author:\n".$notes : '');

        $data = $this->ask($user, AiRequest::text(
            'You help writers plan blog posts and pages. Draft a clear outline for the entry with the given title. '
            .'Give 4 to 7 sections. Each section has a short heading and 2 to 4 short points to cover. '
            .'Write in the same language as the title. Plain text only, no Markdown.',
            $this->limited($text),
            [
                'type' => 'object',
                'properties' => [
                    'sections' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'heading' => ['type' => 'string'],
                                'points' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                            'required' => ['heading', 'points'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['sections'],
                'additionalProperties' => false,
            ],
        ))->json();

        $blocks = [];

        foreach ((array) ($data['sections'] ?? []) as $section) {
            if (! is_array($section) || ! is_string($section['heading'] ?? null) || trim($section['heading']) === '') {
                continue;
            }

            $blocks[] = $this->block('heading', e(trim($section['heading'])), ['level' => 2]);

            $points = array_values(array_filter(
                array_map(fn (mixed $point): string => is_string($point) ? trim($point) : '', (array) ($section['points'] ?? [])),
                fn (string $point): bool => $point !== '',
            ));

            if ($points !== []) {
                $items = implode('', array_map(fn (string $point): string => '<li><p>'.e($point).'</p></li>', $points));
                $blocks[] = $this->block('list', '<ul>'.$items.'</ul>', ['ordered' => false]);
            }
        }

        if ($blocks === []) {
            throw AiProviderError::badAnswer();
        }

        return ['blocks' => $blocks];
    }

    /**
     * A better or shorter version of the selected text.
     *
     * @return array{text: string}
     */
    public function rewrite(User $user, string $text, string $mode): array
    {
        $task = $mode === 'shorten'
            ? 'Make the text shorter, about half its length, and keep the key facts.'
            : 'Improve the text: fix grammar and spelling, make it clear and easy to read, keep the meaning and the tone.';

        $response = $this->ask($user, AiRequest::text(
            'You are a careful copy editor. '.$task.' Keep the same language. '
            .'Reply with the new text only, with no notes, quotes or Markdown.',
            $this->limited($text),
        ));

        return ['text' => $response->text];
    }

    /**
     * A short summary for lists and search results.
     *
     * @param  array<mixed>  $blocks
     * @return array{excerpt: string}
     */
    public function excerpt(User $user, string $title, array $blocks): array
    {
        $response = $this->ask($user, AiRequest::text(
            'You write excerpts for a website. Summarize the entry in one short paragraph of about '
            .$this->targets['excerpt_words'].' words that makes people want to read it. '
            .'Use the language of the entry. Reply with the excerpt only, plain text.',
            $this->limited($this->entryText($title, $blocks)),
        ));

        return ['excerpt' => $this->clip($response->text, (int) config('verso.content.excerpt_max_length'))];
    }

    /**
     * An SEO title and meta description.
     *
     * @param  array<mixed>  $blocks
     * @return array{title: string, description: string}
     */
    public function seo(User $user, string $title, array $blocks): array
    {
        $data = $this->ask($user, AiRequest::text(
            'You are an SEO editor. Suggest a search result title of at most '.$this->targets['seo_title_chars']
            .' characters and a meta description of at most '.$this->targets['seo_description_chars']
            .' characters for the entry. Be specific and honest, no clickbait. Use the language of the entry. Plain text only.',
            $this->limited($this->entryText($title, $blocks)),
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                ],
                'required' => ['title', 'description'],
                'additionalProperties' => false,
            ],
        ))->json();

        if (! is_string($data['title'] ?? null) || ! is_string($data['description'] ?? null)) {
            throw AiProviderError::badAnswer();
        }

        return [
            'title' => $this->clip($data['title'], (int) config('verso.seo.title_max_length')),
            'description' => $this->clip($data['description'], (int) config('verso.seo.description_max_length')),
        ];
    }

    /**
     * Alt text for an image of the media library. The image is sent as base64.
     *
     * @return array{alt: string}
     */
    public function altText(User $user, Media $media): array
    {
        if (! in_array($media->mime_type, $this->imageTypes, true)) {
            throw AiProviderError::unsupportedImage();
        }

        if ($media->size > $this->limits['max_image_bytes']) {
            throw AiInputTooLong::image($this->limits['max_image_bytes']);
        }

        // Fail before any request when the file is gone.
        $this->guard->ensureAvailable();
        $bytes = Storage::disk($media->disk)->get($media->path);

        if ($bytes === null) {
            throw AiProviderError::missingImage();
        }

        if (strlen($bytes) > $this->limits['max_image_bytes']) {
            throw AiInputTooLong::image($this->limits['max_image_bytes']);
        }

        $context = trim((string) $media->caption) !== '' ? 'Caption: '.$media->caption : 'File name: '.$media->filename;

        $response = $this->ask($user, new AiRequest(
            'You write alt text for images on a website. Describe what matters in the image for a person who cannot see it, '
            .'in one sentence of at most '.$this->targets['alt_chars'].' characters. '
            .'Do not start with "Image of" or "Picture of". Reply with the alt text only.',
            [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $media->mime_type, 'data' => base64_encode($bytes)]],
                ['type' => 'text', 'text' => $context],
            ],
        ));

        return ['alt' => $this->clip($response->text, 1000)];
    }

    /**
     * The blocks and the title in another language, with the same block structure.
     *
     * @param  array<mixed>  $blocks
     * @return array{title: string, blocks: array<mixed>, locale: string}
     */
    public function translate(User $user, string $title, array $blocks, string $locale): array
    {
        $segments = $this->translation->segments($blocks);

        if (count($segments) > $this->limits['max_translation_segments']) {
            throw AiInputTooLong::segments($this->limits['max_translation_segments']);
        }

        $payload = (string) json_encode(['title' => $title, 'segments' => $segments], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $language = $this->locales[$locale] ?? $locale;

        $data = $this->ask($user, AiRequest::text(
            "You translate website content into {$language} ({$locale}). You get a JSON object with a title and a list of text segments. "
            .'Translate the title and every segment. Return exactly the same number of segments in the same order. '
            .'Segments may hold inline HTML: keep every tag and attribute as it is and translate only the text between tags. '
            .'Do not translate URLs or code.',
            $this->limited($payload),
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'segments' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['title', 'segments'],
                'additionalProperties' => false,
            ],
        ))->json();

        $translated = array_values(array_filter((array) ($data['segments'] ?? []), is_string(...)));

        if (! is_string($data['title'] ?? null) || count($translated) !== count($segments)) {
            throw AiProviderError::lostSegments();
        }

        return [
            'title' => trim($data['title']),
            'blocks' => $this->translation->apply($blocks, $translated),
            'locale' => $locale,
        ];
    }

    /**
     * New texts for one section of a page, written for the business. The blocks keep their ids, types and order.
     *
     * @param  array<mixed>  $section  the top level block of the section
     * @param  array{name: string, tagline: string}  $site
     * @return array{section: array<mixed>}
     */
    public function sectionText(User $user, array $section, string $business, array $site): array
    {
        $children = is_array($section['children'] ?? null) ? $section['children'] : [];
        $pieces = $this->sectionPieces->segments($children);

        if ($pieces === []) {
            throw new AiProviderError(__('ai-assistant::This section has no text to write yet.'), 422);
        }

        if (count($pieces) > $this->limits['max_section_pieces']) {
            throw AiInputTooLong::sectionPieces($this->limits['max_section_pieces']);
        }

        $label = is_string($section['attrs']['label'] ?? null) ? trim($section['attrs']['label']) : '';
        $payload = (string) json_encode([
            'business' => $business,
            'site' => $site,
            'section' => $label,
            'pieces' => $pieces,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $data = $this->ask($user, AiRequest::text(
            'You write the texts of one section of a small business website. You get a JSON object with a short description of the business, '
            .'the site name and tagline, the section name and the current text pieces of the section in reading order. '
            .'Write a new text for every piece that fits the business and keeps the role of the piece: a heading stays a short heading, '
            .'a button label stays two to four words, a paragraph stays about as long. Be warm, clear and specific, never invent prices, '
            .'addresses, phone numbers or awards. Return exactly the same number of pieces in the same order. '
            .'Pieces may hold inline HTML: keep the same tags (like <p>, <strong>, <ul>, <li>) and change only the text. '
            .'Write in the language of the business description.',
            $this->limited($payload),
            [
                'type' => 'object',
                'properties' => [
                    'pieces' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['pieces'],
                'additionalProperties' => false,
            ],
        ))->json();

        $written = array_values(array_filter((array) ($data['pieces'] ?? []), is_string(...)));

        if (count($written) !== count($pieces)) {
            throw new AiProviderError(__('ai-assistant::The AI helper lost some text pieces. Try again.'));
        }

        $section['children'] = $this->sectionPieces->apply($children, array_map(trim(...), $written));

        return ['section' => $section];
    }

    /**
     * A few headline ideas for a heading, written for the business.
     *
     * @return array{headlines: list<string>}
     */
    public function headlines(User $user, string $headline, string $context, string $business): array
    {
        $count = max(1, $this->targets['headline_count']);
        $text = 'Business: '.$business."\n\nCurrent headline: ".($headline !== '' ? $headline : '(none yet)')
            .($context !== '' ? "\n\nText around it:\n".$context : '');

        $data = $this->ask($user, AiRequest::text(
            'You write headlines for a small business website. Suggest '.min(3, $count).' to '.$count.' different headlines '
            .'for the place of the current one, each at most '.$this->targets['headline_chars'].' characters. '
            .'Make them clear, friendly and specific to the business, no clickbait and no made up facts. '
            .'Write in the language of the business description. Plain text only.',
            $this->limited($text),
            [
                'type' => 'object',
                'properties' => [
                    'headlines' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['headlines'],
                'additionalProperties' => false,
            ],
        ))->json();

        $headlines = [];

        foreach ((array) ($data['headlines'] ?? []) as $item) {
            $line = is_string($item) ? $this->clip($item, $this->targets['headline_chars']) : '';

            if ($line !== '' && ! in_array($line, $headlines, true)) {
                $headlines[] = $line;
            }
        }

        if ($headlines === []) {
            throw AiProviderError::badAnswer();
        }

        return ['headlines' => array_slice($headlines, 0, $count)];
    }

    // Run the checks, send the request and log the tokens.
    protected function ask(User $user, AiRequest $request): AiResponse
    {
        $this->guard->ensureAvailable();
        $this->guard->hit($user);

        $response = $this->provider->send($request);

        $this->usage->record($user, $response);

        return $response;
    }

    /**
     * @param  array<mixed>  $blocks
     */
    protected function entryText(string $title, array $blocks): string
    {
        return 'Title: '.$title."\n\n".$this->blockText->plain($blocks);
    }

    // Long texts are refused, never cut, so the model always sees all of it.
    protected function limited(string $text): string
    {
        if (mb_strlen($text) > $this->limits['max_input_chars']) {
            throw AiInputTooLong::text($this->limits['max_input_chars']);
        }

        return $text;
    }

    protected function clip(string $text, int $max): string
    {
        $text = trim($text, " \t\n\r\0\x0B\"“”");

        return $max > 0 ? Str::limit($text, $max, '') : $text;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    protected function block(string $type, string $content, array $attrs = []): array
    {
        return ['id' => 'b_'.Str::lower(Str::random(8)), 'type' => $type, 'attrs' => $attrs, 'content' => $content, 'children' => []];
    }
}
