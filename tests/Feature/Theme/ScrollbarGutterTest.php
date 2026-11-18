<?php

// A classic scrollbar must not change the page width, or full width blocks look like they touch the right edge.
it('keeps a stable scrollbar gutter in the Ashgrove theme', function () {
    $css = (string) file_get_contents(base_path('themes/ashgrove/assets/theme.css'));

    expect($css)->toMatch('/html\s*\{[^}]*scrollbar-gutter:\s*stable;/');
});
