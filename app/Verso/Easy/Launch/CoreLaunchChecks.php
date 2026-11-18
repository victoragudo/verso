<?php

namespace App\Verso\Easy\Launch;

use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Details\SiteDetails;
use App\Verso\Easy\Styles\ContrastChecker;
use App\Verso\Easy\Styles\ThemeStyleOptions;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\ThemeManager;

// The core steps of the launch checklist: logo, colors, readable colors, pages, contact details, social links, search basics, privacy page and go live.
class CoreLaunchChecks
{
    public function __construct(
        protected EasyState $state,
        protected SiteDetails $details,
        protected PrivacyPage $privacyPage,
        protected SeoSettings $seo,
        protected ThemeManager $themes,
        protected ThemeCustomizer $customizer,
        protected GlobalStyles $globalStyles,
        protected ThemeStyleOptions $styleOptions,
        protected ContrastChecker $contrast,
    ) {}

    public function register(LaunchChecklist $checklist): void
    {
        $details = fn (string $part): string => route('admin.easy.details', absolute: false).'#'.$part;

        $checklist->register(new LaunchCheck(
            key: 'logo',
            label: 'Add your logo',
            description: 'Your logo shows at the top of every page.',
            href: fn (): string => route('admin.easy.style', absolute: false),
            done: fn (): bool => $this->customizer->logo($this->themes->active()) !== null,
            order: 10,
            applies: fn (): bool => $this->customizer->logoField($this->themes->active()) !== null,
        ));
        $checklist->register(new LaunchCheck(
            key: 'colors',
            label: 'Choose your colors',
            description: 'Pick colors and fonts that fit your brand.',
            href: fn (): string => route('admin.easy.style', absolute: false),
            done: fn (): bool => $this->state->hasStyled() || $this->hasOwnColors(),
            order: 20,
        ));
        $checklist->register(new LaunchCheck(
            key: 'readable-colors',
            label: 'Make your colors easy to read',
            description: 'Text, links and buttons need to stand out from the background, so everyone can read them.',
            href: fn (): string => route('admin.easy.style', absolute: false).'#colors',
            done: fn (): bool => $this->contrast->review($this->styleOptions->palette($this->themes->active())) === [],
            order: 25,
            applies: fn (): bool => $this->hasTextColors(),
        ));
        $checklist->register(new LaunchCheck(
            key: 'pages',
            label: 'Add your pages',
            description: 'A home page, a page about you and a contact page are a good start.',
            href: fn (): string => $this->publishedPages() > 0
                ? route('admin.entries.index', ['contentType' => 'page'], false)
                : route('admin.easy.start', absolute: false),
            done: fn (): bool => $this->state->hasStarter() || $this->publishedPages() >= (int) config('verso.easy.launch.min_pages'),
            order: 30,
        ));
        $checklist->register(new LaunchCheck(
            key: 'contact',
            label: 'Add your contact details',
            description: 'An email address, a phone number or an address, so people can reach you.',
            href: fn (): string => $details('contact'),
            done: fn (): bool => $this->details->hasContact(),
            order: 40,
        ));
        $checklist->register(new LaunchCheck(
            key: 'social',
            label: 'Add your social links',
            description: 'Links to your Instagram, Facebook or other profiles.',
            href: fn (): string => $details('social'),
            done: fn (): bool => $this->details->socialLinks() !== [],
            order: 50,
        ));
        $checklist->register(new LaunchCheck(
            key: 'search',
            label: 'Describe your site for search engines',
            description: 'A short line under your site name and a sentence that search results show.',
            href: fn (): string => $details('search'),
            done: fn (): bool => $this->details->hasSearchBasics(),
            order: 60,
        ));
        $checklist->register(new LaunchCheck(
            key: 'privacy',
            label: 'Add a privacy page',
            description: 'Tell visitors what you do with their messages and data.',
            href: fn (): string => route('admin.easy.launch.privacy', absolute: false),
            done: fn (): bool => $this->privacyPage->find() !== null,
            order: 70,
            action: 'Create a privacy page',
            method: 'post',
        ));
        $checklist->register(new LaunchCheck(
            key: 'go-live',
            label: 'Go live',
            description: 'Let search engines like Google find your site.',
            href: fn (): string => route('admin.easy.launch.go-live', absolute: false),
            done: fn (): bool => $this->state->hasLaunched() && ! $this->seo->discourage(),
            order: 80,
            action: 'Go live',
            method: 'post',
        ));
    }

    // True when a color token of the active theme no longer has its default value.
    protected function hasOwnColors(): bool
    {
        $theme = $this->themes->active();
        $values = $this->globalStyles->values($theme);

        foreach ($this->globalStyles->tokens($theme) as $id => $token) {
            if ($token['group'] === 'color' && ($values[$id] ?? $token['default']) !== $token['default']) {
                return true;
            }
        }

        return false;
    }

    // True when the theme lets people pick both a text and a background color, so there is something to check.
    protected function hasTextColors(): bool
    {
        $roles = $this->styleOptions->colorRoles($this->themes->active());

        return isset($roles['text'], $roles['background']);
    }

    protected function publishedPages(): int
    {
        return Entry::query()->ofType('page')->published()->count();
    }
}
