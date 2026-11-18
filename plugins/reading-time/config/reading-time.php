<?php

return [

    // Average reading speed used to count minutes.
    'words_per_minute' => (int) env('READING_TIME_WORDS_PER_MINUTE', 200),

    // Add the reading time on top of every entry that has no reading-time block.
    'auto_insert' => (bool) env('READING_TIME_AUTO_INSERT', false),

    // Text shown in the badge. ":minutes" is replaced by the number. Empty uses the translated "N min read".
    'label' => env('READING_TIME_LABEL', ''),

];
