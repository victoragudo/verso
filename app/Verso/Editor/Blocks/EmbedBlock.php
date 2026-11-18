<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\EmbedResolver;

// A YouTube or Vimeo video shown with a privacy friendly player. Other URLs become a plain link.
class EmbedBlock extends BaseBlock
{
    // Browser features the player may use.
    protected const string ALLOW = 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen';

    // Limits what the player page can do inside our page.
    protected const string SANDBOX = 'allow-scripts allow-same-origin allow-presentation allow-popups';

    // Names shown in the default player title.
    protected const array PROVIDER_NAMES = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo'];

    public function __construct(protected EmbedResolver $resolver) {}

    public function name(): string
    {
        return 'embed';
    }

    public function rules(): array
    {
        return [
            'url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'title' => ['nullable', 'string', 'max:200'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $url = $block->string('url');
        $embed = $this->resolver->resolve($url);
        $caption = $this->caption($block->string('caption'), $renderer);

        if ($embed === null) {
            $link = $renderer->urls()->cleanHttp($url);

            return $link === null ? '' : '<figure class="verso-embed verso-embed--link"><a'.$this->attribute('href', $link)
                .' rel="noopener nofollow">'.e($link).'</a>'.$caption.'</figure>';
        }

        $title = trim($block->string('title')) ?: (self::PROVIDER_NAMES[$embed->provider] ?? ucfirst($embed->provider)).' video';

        return '<figure class="verso-embed verso-embed--'.$embed->provider.'"><div class="verso-embed__frame">'
            .'<iframe'.$this->attribute('src', $embed->src).$this->attribute('title', $title)
            .' loading="lazy" referrerpolicy="strict-origin-when-cross-origin"'
            .$this->attribute('allow', self::ALLOW).$this->attribute('sandbox', self::SANDBOX)
            .' allowfullscreen></iframe></div>'.$caption.'</figure>';
    }
}
