<?php

namespace App\Verso\Content\Admin;

use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Taxonomy;
use App\Verso\I18n\Translate;

class ContentLabels
{
    protected const LOCALES_WITH_CAPITAL_NOUNS = ['de'];

    public function __construct(protected ContentTypeRegistry $types) {}

    public function typeLabel(ContentType $type): string
    {
        return Translate::text($type->label, plugin: $this->types->pluginOf($type->name));
    }

    public function typePluralLabel(ContentType $type): string
    {
        return Translate::text($type->pluralLabel, plugin: $this->types->pluginOf($type->name));
    }

    public function taxonomyLabel(Taxonomy $taxonomy): string
    {
        return Translate::text($taxonomy->label, plugin: $this->taxonomyPlugin($taxonomy));
    }

    public function taxonomyPluralLabel(Taxonomy $taxonomy): string
    {
        return Translate::text($taxonomy->pluralLabel, plugin: $this->taxonomyPlugin($taxonomy));
    }

    /**
     * @return array{name: string, label: string, pluralLabel: string, hierarchical: bool, hasArchive: bool, taxonomies: list<string>, icon: string}
     */
    public function typeArray(ContentType $type): array
    {
        return [
            ...$type->toArray(),
            'label' => $this->typeLabel($type),
            'pluralLabel' => $this->typePluralLabel($type),
        ];
    }

    /**
     * @return array{name: string, label: string, pluralLabel: string, hierarchical: bool, icon: string}
     */
    public function taxonomyArray(Taxonomy $taxonomy): array
    {
        return [
            ...$taxonomy->toArray(),
            'label' => $this->taxonomyLabel($taxonomy),
            'pluralLabel' => $this->taxonomyPluralLabel($taxonomy),
        ];
    }

    public function inline(string $label): string
    {
        $locale = app()->getLocale();
        $language = explode('-', str_replace('_', '-', $locale))[0];

        return in_array($language, self::LOCALES_WITH_CAPITAL_NOUNS, true) ? $label : mb_strtolower($label);
    }

    protected function taxonomyPlugin(Taxonomy $taxonomy): ?string
    {
        foreach ($taxonomy->contentTypes as $name) {
            if ($this->types->has($name)) {
                return $this->types->pluginOf($name);
            }
        }

        return null;
    }
}
