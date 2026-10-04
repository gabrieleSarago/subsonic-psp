<?php

namespace App\Support;

/**
 * Formats durations for the index page.
 */
final class Duration
{
    /**
     * Write a length in seconds as hours, minutes and seconds.
     *
     * Zero is written as "0:00" rather than as an empty string, so a line never
     * ends up with a dangling separator.
     */
    public static function human(int $seconds): string
    {
        $seconds = max(0, $seconds);

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }
}
