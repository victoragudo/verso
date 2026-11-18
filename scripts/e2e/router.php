<?php

// Router for the PHP built-in server in end to end tests: uploads come from the throwaway storage folder.

$public = dirname(__DIR__, 2).'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$storage = (string) getenv('LARAVEL_STORAGE_PATH');

if ($storage !== '' && str_starts_with($uri, '/storage/') && ! str_contains($uri, '..')) {
    $file = $storage.'/app/public/'.substr($uri, strlen('/storage/'));

    if (is_file($file)) {
        header('Content-Type: '.(mime_content_type($file) ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($file));
        readfile($file);

        return true;
    }
}

if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

require $public.'/index.php';
