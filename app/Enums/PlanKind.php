<?php

namespace App\Enums;

/**
 * The three kinds of plan period the app supports.
 *
 *   Weekly    — Mon → Sun, the current calendar week.
 *   Monthly   — first → last day of the current calendar month.
 *   Quarterly — current month → end of (current month + 2). A rolling
 *               3-month window that advances each calendar month.
 */
enum PlanKind: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
        };
    }

    /** Plural noun for headings ("This week", "This month", "This quarter"). */
    public function thisPeriodLabel(): string
    {
        return match ($this) {
            self::Weekly => 'This week',
            self::Monthly => 'This month',
            self::Quarterly => 'This quarter',
        };
    }
}
