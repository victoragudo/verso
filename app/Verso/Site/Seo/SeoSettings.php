<?php

namespace App\Verso\Site\Seo;

use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;

// The site wide search engine settings, with defaults from config/verso.php.
class SeoSettings
{
    public const DISCOURAGE = 'reading.discourage_search';

    public const ROBOTS_TXT = 'seo.robots_txt';

    public const TITLE_FORMAT = 'seo.title_format';

    public const TITLE_SEPARATOR = 'seo.title_separator';

    public const DEFAULT_DESCRIPTION = 'seo.default_description';

    public const DEFAULT_IMAGE = 'seo.default_image';

    public const SCHEMA_TYPE = 'seo.schema_type';

    public const SCHEMA_NAME = 'seo.schema_name';

    public const SCHEMA_LOGO = 'seo.schema_logo';

    public const SCHEMA_SAME_AS = 'seo.schema_same_as';

    public const VERIFICATION_GOOGLE = 'seo.verification_google';

    public const VERIFICATION_BING = 'seo.verification_bing';

    public const INDEXNOW_ENABLED = 'seo.indexnow_enabled';

    public const INDEXNOW_KEY = 'seo.indexnow_key';

    public const SCHEMA_TYPES = ['organization', 'person'];

    // Tokens of the title format.
    public const TITLE_TOKENS = ['{title}', '{sep}', '{site}', '{tagline}'];

    public function __construct(protected Settings $settings) {}

    public function discourage(): bool
    {
        return (bool) $this->settings->get(self::DISCOURAGE);
    }

    public function robotsTxt(): string
    {
        return (string) $this->settings->get(self::ROBOTS_TXT, (string) config('verso.seo.robots_default'));
    }

    public function titleFormat(): string
    {
        return $this->text(self::TITLE_FORMAT, (string) config('verso.seo.title_format'));
    }

    // The separator symbol, like "|" or "·", without spaces.
    public function separatorSymbol(): string
    {
        $value = $this->settings->get(self::TITLE_SEPARATOR);
        $symbol = trim(is_string($value) ? $value : '');

        return $symbol !== '' ? $symbol : trim((string) config('verso.seo.title_separator'));
    }

    // The separator with a space on each side, as printed between title parts.
    public function separator(): string
    {
        return ' '.$this->separatorSymbol().' ';
    }

    // Builds a page title from the format, like "Riding Tram 28 | Harbor Lane".
    public function title(string $title, string $site, ?string $tagline): string
    {
        $text = strtr($this->titleFormat(), [
            '{title}' => $title,
            '{sep}' => $this->separator(),
            '{site}' => $site,
            '{tagline}' => (string) $tagline,
        ]);

        // Drop a separator left at the start or end by an empty part.
        $separator = preg_quote(trim($this->separator()), '/');
        $text = trim((string) preg_replace('/^\s*'.$separator.'\s*|\s*'.$separator.'\s*$/u', '', trim($text)));

        return $text === '' ? $title : $text;
    }

    public function defaultDescription(): ?string
    {
        $value = $this->text(self::DEFAULT_DESCRIPTION, '');

        return $value === '' ? null : $value;
    }

    public function defaultImageId(): ?int
    {
        return $this->mediaId(self::DEFAULT_IMAGE);
    }

    public function defaultImageUrl(): ?string
    {
        return $this->mediaUrl($this->defaultImageId());
    }

    public function schemaType(): string
    {
        $value = $this->settings->get(self::SCHEMA_TYPE);

        return in_array($value, self::SCHEMA_TYPES, true) ? $value : 'organization';
    }

    public function schemaName(): string
    {
        return $this->text(self::SCHEMA_NAME, '');
    }

    public function schemaLogoId(): ?int
    {
        return $this->mediaId(self::SCHEMA_LOGO);
    }

    /** @return list<string> */
    public function sameAs(): array
    {
        $value = $this->settings->get(self::SCHEMA_SAME_AS);

        return array_values(array_filter(is_array($value) ? $value : [], fn (mixed $link): bool => is_string($link) && $link !== ''));
    }

    // The schema.org Organization or Person node of the site, or null when no name is set.
    /** @return array<string, mixed>|null */
    public function publisher(string $siteUrl): ?array
    {
        $name = $this->schemaName();

        if ($name === '') {
            return null;
        }

        $person = $this->schemaType() === 'person';
        $logo = $this->mediaUrl($this->schemaLogoId());

        return array_filter([
            '@type' => $person ? 'Person' : 'Organization',
            '@id' => $siteUrl.'/#'.($person ? 'person' : 'organization'),
            'name' => $name,
            'url' => $siteUrl.'/',
            $person ? 'image' : 'logo' => $logo,
            'sameAs' => $this->sameAs() === [] ? null : $this->sameAs(),
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> meta name => content */
    public function verificationTags(): array
    {
        return array_filter([
            'google-site-verification' => $this->text(self::VERIFICATION_GOOGLE, ''),
            'msvalidate.01' => $this->text(self::VERIFICATION_BING, ''),
        ], fn (string $value): bool => $value !== '');
    }

    public function indexNowKey(): ?string
    {
        $key = $this->text(self::INDEXNOW_KEY, '');

        return (bool) $this->settings->get(self::INDEXNOW_ENABLED) && $key !== '' ? $key : null;
    }

    protected function text(string $key, string $default): string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    protected function mediaId(string $key): ?int
    {
        $value = $this->settings->get($key);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function mediaUrl(?int $id): ?string
    {
        $media = $id === null ? null : Media::query()->find($id);

        return $media instanceof Media ? url($media->url()) : null;
    }
}
