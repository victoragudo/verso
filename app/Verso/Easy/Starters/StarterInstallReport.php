<?php

namespace App\Verso\Easy\Starters;

use App\Verso\I18n\Translate;

// What installing a starter site did: pages made, updated or left alone, plugins turned on or missing.
final readonly class StarterInstallReport
{
    /**
     * @param  string  $label  the starter name in the admin language
     * @param  list<string>  $created  titles of new pages and posts
     * @param  list<string>  $updated  titles of pages a starter made before and that got the new content
     * @param  list<string>  $skipped  titles of pages left alone, because the site already had them
     * @param  list<string>  $pluginsEnabled  slugs turned on now
     * @param  list<string>  $pluginsMissing  slugs that are not installed or cannot be turned on
     */
    public function __construct(
        public string $starter,
        public string $label,
        public array $created = [],
        public array $updated = [],
        public array $skipped = [],
        public array $pluginsEnabled = [],
        public array $pluginsMissing = [],
        public ?int $frontPageId = null,
    ) {}

    // One friendly line for the toast after the install.
    public function message(): string
    {
        $message = Translate::icu('{count, plural, one {Your site is ready: # page from the "{starter}" starter.} other {Your site is ready: # pages from the "{starter}" starter.}}', [
            'count' => count($this->created) + count($this->updated),
            'starter' => $this->label,
        ]);

        if ($this->skipped !== []) {
            $message .= ' '.__('We kept your own :pages.', ['pages' => implode(', ', $this->skipped)]);
        }

        return $message;
    }

    /**
     * @return array{starter: string, created: list<string>, updated: list<string>, skipped: list<string>, pluginsEnabled: list<string>, pluginsMissing: list<string>, frontPageId: int|null}
     */
    public function toArray(): array
    {
        return [
            'starter' => $this->starter,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'pluginsEnabled' => $this->pluginsEnabled,
            'pluginsMissing' => $this->pluginsMissing,
            'frontPageId' => $this->frontPageId,
        ];
    }
}
