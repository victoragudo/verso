<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use App\Verso\Site\Seo\SeoSettings;

// Links to the social profiles of the site. The name of each network is found from the address.
// With no addresses of its own it shows the social profiles of the site settings.
class SocialLinksBlock extends SiteBlock
{
    // Known hosts and the name shown for them.
    public const NETWORKS = [
        'instagram.com' => 'Instagram', 'facebook.com' => 'Facebook', 'x.com' => 'X', 'twitter.com' => 'X',
        'linkedin.com' => 'LinkedIn', 'youtube.com' => 'YouTube', 'tiktok.com' => 'TikTok', 'pinterest.com' => 'Pinterest',
        'github.com' => 'GitHub', 'mastodon.social' => 'Mastodon', 'bsky.app' => 'Bluesky', 'threads.net' => 'Threads',
    ];

    public function name(): string
    {
        return 'social-links';
    }

    public function label(): string
    {
        return __('Social links');
    }

    public function description(): string
    {
        return __('Links to your social profiles.');
    }

    public function fields(): array
    {
        return [
            new BlockField('links', __('Profile addresses'), BlockFieldType::Textarea, '', help: __('One address per line, like https://instagram.com/yourname. Leave it empty to show the social profiles of the site.'), max: 2000),
        ];
    }

    protected function html(array $attrs): string
    {
        $items = '';

        $links = $this->text($attrs, 'links');
        $links = trim($links) !== '' ? $links : implode("\n", app(SeoSettings::class)->sameAs());

        foreach ($this->linkLines($links) as $link) {
            $items .= '<li>'.$this->link($link['url'], $this->network($link['url']) ?? $link['label'], newTab: true).'</li>';
        }

        return $items === '' ? '' : '<ul class="verso-social" aria-label="Social links">'.$items.'</ul>';
    }

    protected function network(string $url): ?string
    {
        return self::networkOf($url);
    }

    // The name of a known network from a profile address, like "Instagram". Null for other hosts.
    public static function networkOf(string $url): ?string
    {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

        return self::NETWORKS[$host] ?? null;
    }
}
