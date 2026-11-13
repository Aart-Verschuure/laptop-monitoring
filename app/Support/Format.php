<?php

namespace App\Support;

class Format
{
    /** 3725 => "1u 2m", 90061 => "1d 1u 1m" */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days) {
            $parts[] = $days.'d';
        }
        if ($hours || $days) {
            $parts[] = $hours.'u';
        }
        $parts[] = $minutes.'m';

        return implode(' ', $parts);
    }

    public static function mb(?int $mb): string
    {
        if ($mb === null) {
            return '—';
        }

        return $mb >= 1024 ? number_format($mb / 1024, 1, ',', '.').' GB' : $mb.' MB';
    }

    /** Leesbare naam voor een spec-sleutel, bv. "cpu_cores" => "Cpu cores" */
    public static function specLabel(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
