<?php

namespace App\Modules\Flower\Support;

class Duration
{
    /** Format a number of seconds as "m:ss" (or "0:ss" under a minute). */
    public static function format(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) round($seconds);
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $rest);
    }
}
