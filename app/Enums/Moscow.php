<?php

namespace App\Enums;

/**
 * MoSCoW priority used on projects and deliverables.
 *
 *   M — Must have this cycle
 *   S — Should have if at all possible
 *   C — Could have if time permits
 *   W — Won't have this cycle (move to Backlog)
 *
 * Use M sparingly. Over half of Musts typically don't get delivered;
 * if everything is Must, nothing is.
 */
enum Moscow: string
{
    case Must = 'M';
    case Should = 'S';
    case Could = 'C';
    case Wont = 'W';

    public function label(): string
    {
        return match ($this) {
            self::Must => 'Must',
            self::Should => 'Should',
            self::Could => 'Could',
            self::Wont => "Won't",
        };
    }

    public function chipClasses(): string
    {
        return match ($this) {
            self::Must => 'bg-indigo-900 text-white',
            self::Should => 'bg-emerald-600 text-white',
            self::Could => 'bg-amber-500 text-white',
            self::Wont => 'bg-gray-500 text-white',
        };
    }
}
