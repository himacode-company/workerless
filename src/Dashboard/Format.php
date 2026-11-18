<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Dashboard;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Small display helpers for the dashboard views.
 */
class Format
{
    /** "3 minutes ago" / "in 2 minutes" from a unix time, a date string or a date. */
    public static function ago(mixed $value, string $empty = 'Never'): string
    {
        $date = self::date($value);

        return $date === null ? $empty : $date->diffForHumans();
    }

    /** Full date for tooltips. */
    public static function exact(mixed $value): string
    {
        $date = self::date($value);

        return $date === null ? '' : $date->format('Y-m-d H:i:s');
    }

    public static function duration(?int $ms): string
    {
        if ($ms === null) {
            return '-';
        }

        if ($ms < 1000) {
            return $ms . ' ms';
        }

        if ($ms < 60000) {
            return rtrim(rtrim(number_format($ms / 1000, 2), '0'), '.') . ' s';
        }

        return intdiv($ms, 60000) . ' m ' . intdiv($ms % 60000, 1000) . ' s';
    }

    public static function number(?int $value): string
    {
        return $value === null ? '-' : number_format($value);
    }

    /** Short class name with its namespace kept for the tooltip. */
    public static function shortName(string $name): string
    {
        $pos = strrpos($name, '\\');

        return $pos === false ? $name : substr($name, $pos + 1);
    }

    protected static function date(mixed $value): ?Carbon
    {
        try {
            if ($value === null || $value === '' || $value === PHP_INT_MAX) {
                return null;
            }

            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return Carbon::createFromTimestamp((int) $value);
            }

            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value);
            }

            return is_string($value) ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
