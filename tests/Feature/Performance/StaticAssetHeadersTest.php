<?php

it('asks Apache to cache built assets for a year', function () {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('m#^/build/assets/#')
        ->toContain('Header set Cache-Control "public, max-age=31536000, immutable"');
});

it('asks Apache to cache fonts for a month', function () {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('m#^/fonts/#')
        ->toContain('Header set Cache-Control "public, max-age=2592000"');
});

it('keeps the header rules optional when mod_headers is missing', function () {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('<IfModule mod_headers.c>');
});
