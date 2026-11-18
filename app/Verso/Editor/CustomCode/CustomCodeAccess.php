<?php

namespace App\Verso\Editor\CustomCode;

use App\Models\User;
use App\Verso\Editor\Builder\EditorAccess;

// Who may change custom CSS and JavaScript: the editor feature must be on for the role,
// and the user needs the capability from config (admins by default).
class CustomCodeAccess
{
    public function __construct(
        protected EditorAccess $access,
        protected string $cssCapability,
        protected string $jsCapability,
    ) {}

    public function css(?User $user, ?string $type = null): bool
    {
        return $user !== null && $this->access->enabled('custom_css', $user, $type) && $user->can($this->cssCapability);
    }

    public function js(?User $user, ?string $type = null): bool
    {
        return $user !== null && $this->access->enabled('custom_js', $user, $type) && $user->can($this->jsCapability);
    }
}
