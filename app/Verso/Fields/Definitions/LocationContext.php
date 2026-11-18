<?php

namespace App\Verso\Fields\Definitions;

// What we know about the entry being edited or shown. Unknown parts are null (template: false).
final readonly class LocationContext
{
    /**
     * @param  list<int>|null  $termIds
     * @param  list<string>  $roles  every role of the user (main and extra), for user_role rules
     */
    public function __construct(
        public ?string $contentType = null,
        public string|false|null $template = false,
        public ?array $termIds = null,
        public ?string $role = null,
        public array $roles = [],
    ) {}
}
