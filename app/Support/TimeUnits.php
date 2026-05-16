<?php

namespace App\Support;

/**
 * Hours <-> days conversion.
 *
 * Hours is the source of truth in the database. Days are derived for display
 * (e.g. "12.0h (1.5d)"). The conversion factor lives in one place so we can
 * change it (e.g. for a 7-hour work day) without hunting through views.
 */
class TimeUnits
{
    /** Hours per working day. 8 by default. */
    public const HOURS_PER_DAY = 8.0;

    public static function daysFromHours(float|int|string|null $hours): float
    {
        return (float) $hours / self::HOURS_PER_DAY;
    }

    public static function hoursFromDays(float|int|string|null $days): float
    {
        return (float) $days * self::HOURS_PER_DAY;
    }

    /**
     * Display string like "12.5h (1.6d)". One decimal by default.
     * Hours lead — use this for values that were *entered* in hours
     * (spent / logged time), where seeing the hours number matters first.
     */
    public static function formatHoursWithDays(float|int|string|null $hours, int $decimals = 1): string
    {
        $h = (float) $hours;
        $d = self::daysFromHours($h);

        return sprintf(
            '%sh (%sd)',
            self::trim(number_format($h, $decimals)),
            self::trim(number_format($d, $decimals)),
        );
    }

    /**
     * Display string like "2d (16h)". Days lead — use this for values that
     * were *entered* in days (targets, allocations, capacity), where the
     * days number is the source-of-thinking and hours is the implied detail.
     * Input is still hours (the storage unit).
     */
    public static function formatDaysWithHours(float|int|string|null $hours, int $decimals = 1): string
    {
        $h = (float) $hours;
        $d = self::daysFromHours($h);

        return sprintf(
            '%sd (%sh)',
            self::trim(number_format($d, $decimals)),
            self::trim(number_format($h, $decimals)),
        );
    }

    /** Just the hours portion, formatted (e.g. "12.5h"). */
    public static function formatHours(float|int|string|null $hours, int $decimals = 1): string
    {
        return self::trim(number_format((float) $hours, $decimals)) . 'h';
    }

    /** Just the days portion, formatted (e.g. "1.6d"). */
    public static function formatDays(float|int|string|null $hours, int $decimals = 1): string
    {
        return self::trim(number_format(self::daysFromHours($hours), $decimals)) . 'd';
    }

    /**
     * Trim trailing ".0" so "8.0" displays as "8" but "8.5" keeps its decimal.
     */
    private static function trim(string $formatted): string
    {
        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
