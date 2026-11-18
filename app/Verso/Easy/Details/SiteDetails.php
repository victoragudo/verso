<?php

namespace App\Verso\Easy\Details;

use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Seo\SeoSettings;

// The details a visitor looks for: how to reach you, your social profiles and the short text search engines show.
class SiteDetails
{
    public const EMAIL = 'site.contact.email';

    public const PHONE = 'site.contact.phone';

    public const ADDRESS = 'site.contact.address';

    public const HOURS = 'site.contact.hours';

    public const TAGLINE = 'site.tagline';

    // Fields of the "Your site details" form and the setting each one is saved in.
    public const FIELDS = [
        'email' => self::EMAIL,
        'phone' => self::PHONE,
        'address' => self::ADDRESS,
        'hours' => self::HOURS,
        'tagline' => self::TAGLINE,
        'description' => SeoSettings::DEFAULT_DESCRIPTION,
    ];

    public function __construct(
        protected Settings $settings,
        protected SeoSettings $seo,
    ) {}

    /**
     * How to reach the site owner. Empty parts are empty strings.
     *
     * @return array{email: string, phone: string, address: string, hours: string}
     */
    public function contact(): array
    {
        return [
            'email' => $this->text(self::EMAIL),
            'phone' => $this->text(self::PHONE),
            'address' => $this->text(self::ADDRESS),
            'hours' => $this->text(self::HOURS),
        ];
    }

    // True when visitors have at least one way to reach the owner.
    public function hasContact(): bool
    {
        $contact = $this->contact();

        return $contact['email'] !== '' || $contact['phone'] !== '' || $contact['address'] !== '';
    }

    /**
     * Addresses of the social profiles. They are the same "same as" links search engines read.
     *
     * @return list<string>
     */
    public function socialLinks(): array
    {
        return $this->seo->sameAs();
    }

    // True when the tagline and the search engine description are both filled in.
    public function hasSearchBasics(): bool
    {
        return $this->text(self::TAGLINE) !== '' && $this->seo->defaultDescription() !== null;
    }

    /**
     * Every value of the form.
     *
     * @return array{email: string, phone: string, address: string, hours: string, tagline: string, description: string, social: list<string>}
     */
    public function values(): array
    {
        return [
            ...$this->contact(),
            'tagline' => $this->text(self::TAGLINE),
            'description' => (string) $this->seo->defaultDescription(),
            'social' => $this->socialLinks(),
        ];
    }

    /**
     * Contact details as the extra values of a contact section.
     *
     * @return array<string, string>
     */
    public function sectionExtra(): array
    {
        return array_filter($this->contact(), fn (string $value): bool => $value !== '');
    }

    protected function text(string $key): string
    {
        $value = $this->settings->get($key);

        return is_string($value) ? trim($value) : '';
    }
}
