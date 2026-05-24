<?php

namespace App\Support;

/**
 * Single source of truth for Sanctum token abilities.
 *
 * Each ability is a string in the form "{resource}:{action}". A token issued
 * with ability "time-logs:write" can call any endpoint that requires that
 * ability. The "*" wildcard token can do anything the user can.
 *
 * Group the abilities into resource buckets so the token-creation UI can
 * show "read" and "write" checkboxes per area without listing every fine-
 * grained verb. Right now read/write maps cleanly to GET vs POST/PUT/DELETE.
 */
class ApiAbility
{
    /** Read any of the tracker's data (clients, projects, deliverables, plans, time-logs, reports). */
    public const READ_ALL = 'read:all';

    /** Modify any of the tracker's data (CRUD across the board). */
    public const WRITE_ALL = 'write:all';

    /** Just read the user's time logs. Useful for "show me my hours" agents. */
    public const TIME_LOGS_READ = 'time-logs:read';

    /** Append / edit / delete time logs (the journal). Most common agent ability. */
    public const TIME_LOGS_WRITE = 'time-logs:write';

    /** Read clients / projects / deliverables / plans. */
    public const TRACKER_READ = 'tracker:read';

    /** Create / edit / delete clients / projects / deliverables / plans. */
    public const TRACKER_WRITE = 'tracker:write';

    /**
     * Every ability the system knows about. Order is also the display order
     * in the token-creation form.
     *
     * @return array<int,array{value:string,label:string,description:string}>
     */
    public static function all(): array
    {
        return [
            [
                'value' => self::TIME_LOGS_READ,
                'label' => 'Read time logs',
                'description' => 'See your daily journal entries.',
            ],
            [
                'value' => self::TIME_LOGS_WRITE,
                'label' => 'Write time logs',
                'description' => 'Add, edit, or delete journal entries.',
            ],
            [
                'value' => self::TRACKER_READ,
                'label' => 'Read tracker',
                'description' => 'See clients, projects, deliverables, and plans.',
            ],
            [
                'value' => self::TRACKER_WRITE,
                'label' => 'Write tracker',
                'description' => 'Create / edit clients, projects, deliverables, plan items.',
            ],
            [
                'value' => self::READ_ALL,
                'label' => 'Read everything',
                'description' => 'Convenience grant covering both read scopes above.',
            ],
            [
                'value' => self::WRITE_ALL,
                'label' => 'Write everything',
                'description' => 'Full read + write. Be careful who you hand this to.',
            ],
        ];
    }

    /** Just the bare ability strings, for `Rule::in()` validation. */
    public static function values(): array
    {
        return array_column(self::all(), 'value');
    }

    /**
     * Expand a granted set into the underlying read/write atoms.
     * E.g. READ_ALL → [TIME_LOGS_READ, TRACKER_READ].
     * Used at request-authorization time so endpoints can check just the
     * atom they need without worrying about wildcards.
     *
     * @param array<int,string> $granted
     * @return array<int,string>
     */
    public static function expand(array $granted): array
    {
        $out = [];
        foreach ($granted as $a) {
            $out[] = $a;
            if ($a === self::READ_ALL) {
                $out[] = self::TIME_LOGS_READ;
                $out[] = self::TRACKER_READ;
            }
            if ($a === self::WRITE_ALL) {
                $out[] = self::TIME_LOGS_READ;
                $out[] = self::TIME_LOGS_WRITE;
                $out[] = self::TRACKER_READ;
                $out[] = self::TRACKER_WRITE;
            }
        }
        return array_values(array_unique($out));
    }
}
