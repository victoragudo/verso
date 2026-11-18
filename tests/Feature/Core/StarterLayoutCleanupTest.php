<?php

// The admin uses the Verso shell, so the starter header layout must stay removed.
it('has no starter header layout files left', function (string $path) {
    expect(file_exists(resource_path($path)))->toBeFalse();
})->with([
    'js/layouts/app/app-header-layout.tsx',
    'js/components/app-header.tsx',
    'js/components/nav-main.tsx',
    'js/components/app-logo.tsx',
    'js/components/app-content.tsx',
    'js/components/app-shell.tsx',
]);

it('keeps the sidebar layout the admin uses', function () {
    expect(file_get_contents(resource_path('js/layouts/app-layout.tsx')))->toContain('@/layouts/app/app-sidebar-layout');
});
