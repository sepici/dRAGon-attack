<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Permissive date parsing for agent-facing inputs.
 *
 * Accepts:
 *   - "today", "yesterday", "tomorrow"
 *   - day-of-week names ("monday", "last friday")
 *   - ISO "YYYY-MM-DD"
 *   - anything Carbon can `parse()` (RFC dates etc.)
 *
 * Returns null for empty / unparseable input. Callers decide whether null
 * is an error or a fall-through to a default.
 */
class DateInput
{
    public static function parse(?string $input, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        if ($input === null) {
            return null;
        }
        $s = trim(strtolower($input));
        if ($s === '') {
            return null;
        }

        return match (true) {
            $s === 'today' => $now->startOfDay(),
            $s === 'yesterday' => $now->subDay()->startOfDay(),
            $s === 'tomorrow' => $now->addDay()->startOfDay(),
            default => self::carbonParseSafe($input, $now),
        };
    }

    /**
     * Parse two values that bound a range. Either may be relative or
     * absolute. Returns null tuple entries when input is empty/invalid.
     *
     * @return array{0:?CarbonImmutable,1:?CarbonImmutable}
     */
    public static function parseRange(?string $from, ?string $to, ?CarbonImmutable $now = null): array
    {
        return [self::parse($from, $now), self::parse($to, $now)];
    }

    private static function carbonParseSafe(string $input, CarbonImmutable $now): ?CarbonImmutable
    {
        try {
            // Carbon::parse handles ISO and natural-language phrases like
            // "last monday", "3 days ago", "next week" via PHP's strtotime.
            $parsed = CarbonImmutable::parse($input, $now->getTimezone());
            return $parsed->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
