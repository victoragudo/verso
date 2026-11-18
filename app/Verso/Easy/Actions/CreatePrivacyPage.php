<?php

namespace App\Verso\Easy\Actions;

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Details\SiteDetails;
use App\Verso\Easy\Launch\PrivacyPage;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Enums\MenuItemVisibility;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;

// "Create a privacy page": a published page from a plain template, with the site name and contact email filled in,
// linked from the footer menu so visitors can find it.
class CreatePrivacyPage
{
    public function __construct(
        protected CreateEntry $createEntry,
        protected PrivacyPage $privacyPage,
        protected SiteDetails $details,
        protected Settings $settings,
        protected EasyState $state,
        protected SectionKit $kit,
    ) {}

    // Returns the existing privacy page when there is one, so it never makes a copy.
    public function handle(User $user): Entry
    {
        $existing = $this->privacyPage->findAny();

        if ($existing !== null) {
            $this->state->rememberPrivacyPage((int) $existing->id);

            return $existing;
        }

        // i18n-ignore-start
        $entry = $this->createEntry->handle($user, 'page', [
            'title' => 'Privacy policy',
            'slug' => $this->privacyPage->slug(),
            'status' => 'published',
            'blocks' => $this->blocks(),
        ]);
        // i18n-ignore-end
        $this->state->rememberPrivacyPage((int) $entry->id);
        $this->linkFromMenu($entry);

        return $entry;
    }

    // Adds the page at the end of the menu in the configured location, unless that menu already links to it.
    protected function linkFromMenu(Entry $entry): void
    {
        $location = (string) config('verso.easy.privacy.menu_location');
        $menu = $location === '' ? null : Menu::forLocation($location);

        if ($menu === null || $menu->items()->where('entry_id', $entry->id)->exists()) {
            return;
        }

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'type' => MenuItemType::Entry,
            'entry_id' => $entry->id,
            'position' => ((int) $menu->items()->whereNull('parent_id')->max('position')) + 1,
            'visibility' => MenuItemVisibility::Everyone,
        ]);
    }

    /**
     * The template: short sections in plain words that the owner checks and changes.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(): array
    {
        $site = (string) ($this->settings->get('site.name') ?? config('app.name'));
        $email = $this->details->contact()['email'];
        $reach = $email !== '' ? "write to {$email}" : 'use the contact page';

        $parts = [
            'Who we are' => "This is the website of {$site}. This page explains which personal data we collect when you visit it, why we collect it and what you can ask us to do with it.",
            'What we collect' => "When you send us a message, we keep your name, your email address and your message so we can answer you.\n\nWhen you leave a comment, we keep your name, your email address and the comment. Your email address is never shown.",
            'Cookies' => 'This site only uses the cookies it needs to work, like keeping you signed in. We do not use cookies to follow you on other sites.',
            'How long we keep it' => 'We keep messages for as long as we need them to help you, and comments for as long as they are on the site.',
            'Your rights' => "You can ask us which data we keep about you, ask us to change it or ask us to delete it. To do that, {$reach}.",
        ];

        $blocks = [$this->kit->paragraph('Please read this page and change it so it matches what your site really does.')];

        foreach ($parts as $heading => $text) {
            $blocks = [...$blocks, $this->kit->heading($heading), ...$this->kit->paragraphs($text)];
        }

        return $blocks;
    }
}
