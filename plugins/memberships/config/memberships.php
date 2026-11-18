<?php

return [

    // Link of the "Join" button in the teaser. Empty hides the button. The admin can change it in Settings.
    'join_url' => env('MEMBERSHIPS_JOIN_URL', ''),

    // Text of the teaser shown in place of members only content.
    'teaser_message' => env('MEMBERSHIPS_TEASER_MESSAGE', 'This content is for members only.'),

    // Capability that always sees restricted content (editors and admins by default).
    'bypass_capability' => env('MEMBERSHIPS_BYPASS_CAPABILITY', 'edit_any_entry'),

    // Time of day when expired memberships are closed.
    'expire_at' => env('MEMBERSHIPS_EXPIRE_AT', '00:15'),

    // Members shown on one admin page.
    'per_page' => (int) env('MEMBERSHIPS_PER_PAGE', 25),

];
