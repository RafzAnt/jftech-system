<?php

namespace App\Support;

/** Clock arithmetic for attendance: times are "HH:MM" or "HH:MM:SS" strings, as the TIME columns return them. */
final class AttendanceTime
{
    public static function secondsOfDay(string $time): int
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', substr($time, 0, 8)) + [0, 0, 0]);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    /** Whole minutes from one time to a later one on the same day; 0 when the second is not after the first. */
    public static function minutesBetween(string $from, string $to): int
    {
        return max(0, intdiv(self::secondsOfDay($to) - self::secondsOfDay($from), 60));
    }

    /** The longest overtime a single day can hold, in minutes. */
    public const MAX_OVERTIME_MINUTES = 720;
}
