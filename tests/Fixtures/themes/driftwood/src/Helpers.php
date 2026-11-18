<?php

function driftwood_reading_time(string $text): int
{
    return (int) ceil(str_word_count($text) / 200);
}
