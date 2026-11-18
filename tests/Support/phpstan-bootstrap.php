<?php

// Lets PHPStan know the Blade views of every plugin, which only load while a plugin is enabled.
foreach (glob(dirname(__DIR__, 2).'/plugins/*/resources/views', GLOB_ONLYDIR) ?: [] as $views) {
    app('view')->addNamespace(basename(dirname($views, 2)), $views);

    if (is_dir($views.'/theme')) {
        app('view')->addNamespace('theme', $views.'/theme');
    }
}
