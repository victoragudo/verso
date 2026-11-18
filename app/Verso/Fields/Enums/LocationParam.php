<?php

namespace App\Verso\Fields\Enums;

// What a location rule looks at to decide if a field group shows.
enum LocationParam: string
{
    case ContentType = 'content_type';
    case PageTemplate = 'page_template';
    case TaxonomyTerm = 'taxonomy_term';
    case UserRole = 'user_role';

    public function label(): string
    {
        return match ($this) {
            self::ContentType => __('Content type'),
            self::PageTemplate => __('Page template'),
            self::TaxonomyTerm => __('Taxonomy term'),
            self::UserRole => __('User role'),
        };
    }
}
