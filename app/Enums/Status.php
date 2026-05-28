<?php

namespace App\Enums;

/**
 * RAGB status used on projects, deliverables, and plan items.
 *
 * Definitions (per the 13 May 2026 review):
 *   R — won't deliver on the current plan; outcome not signed off / no
 *       target date / scope > capacity. DEFAULT for anything not done.
 *   A — on the plan, at risk; needs attention this week.
 *   G — outcome delivered, tested, signed off. Not "in progress" — done.
 *   B — blocked, waiting on external input.
 */
enum Status: string
{
    case Red = 'R';
    case Amber = 'A';
    case Green = 'G';
    case Blocked = 'B';

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Red',
            self::Amber => 'Amber',
            self::Green => 'Green',
            self::Blocked => 'Blocked',
        };
    }

    /** Tailwind classes for a pill/chip render of this status. */
    public function chipClasses(): string
    {
        return match ($this) {
            self::Red => 'bg-red-500 text-white',
            self::Amber => 'bg-amber-500 text-white',
            self::Green => 'bg-green-600 text-white',
            self::Blocked => 'bg-purple-600 text-white',
        };
    }

    /**
     * Roll up a collection of statuses into a single worst-of-children
     * status. Used to derive a Project's status from its Deliverables, and
     * (later) a monthly/quarterly plan's status from its weekly items.
     *
     * Priority order (worst first): R > B > A > G. Empty input → R, on the
     * grounds that nothing-delivered is the default Red.
     */
    public static function rollup(iterable $statuses): self
    {
        $set = collect($statuses);
        if ($set->contains(self::Red)) return self::Red;
        if ($set->contains(self::Blocked)) return self::Blocked;
        if ($set->contains(self::Amber)) return self::Amber;
        if ($set->contains(self::Green)) return self::Green;
        return self::Red;
    }
}
