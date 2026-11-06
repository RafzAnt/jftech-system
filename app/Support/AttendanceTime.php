<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

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

    /** The shortest overtime a day must reach to be paid; a day with less is recorded but not counted in the payroll. */
    public const MIN_OVERTIME_MINUTES = 60;

    /**
     * Leaving a session this many minutes before it ends still counts as the full session: the QR code of a session only works until
     * its end, so someone who works to the end scans a few minutes before it.
     */
    public const UNDERTIME_GRACE_MINUTES = 5;

    /** How early before a session starts the QR code can be scanned, when the setting `early_scan_minutes` has not been saved. */
    public const DEFAULT_EARLY_SCAN_MINUTES = 60;

    /** The most minutes the early scan can be set to. */
    public const MAX_EARLY_SCAN_MINUTES = 180;

    /** Minutes before a session starts that its QR code already works (the setting `early_scan_minutes`, 0 = only from the start). */
    public static function earlyScanMinutes(): int
    {
        $value = DB::table('system_settings')->where('setting_key', 'early_scan_minutes')->value('setting_value');

        return $value === null || ! is_numeric($value)
            ? self::DEFAULT_EARLY_SCAN_MINUTES
            : max(0, min(self::MAX_EARLY_SCAN_MINUTES, (int) $value));
    }

    /** The first time of the day a session can be scanned: its start less the early scan minutes (not before midnight). */
    public static function earliestScan(string $sessionStart): string
    {
        return gmdate('H:i:s', max(0, self::secondsOfDay($sessionStart) - self::earlyScanMinutes() * 60));
    }

    /** "17:00" or "17:00:00" as "17:00:00". */
    public static function clock(string $time): string
    {
        return gmdate('H:i:s', self::secondsOfDay($time));
    }

    /**
     * The last time of the day a QR code can be scanned (the "Last scan of the day" of the settings, 22:00 by default). The afternoon
     * code works until then, so someone who works past the end of the afternoon can still scan out; the time after the end of the
     * afternoon is the overtime.
     */
    public static function lastScan(): string
    {
        $value = DB::table('system_settings')->where('setting_key', 'qr_overtime_end')->value('setting_value');

        return self::clock($value ?: '22:00:00');
    }

    /** The overtime of a time out after the end of the session: the minutes from the end to the time out (at most a day's maximum). */
    public static function overtimeAfter(string $sessionEnd, string $timeOut): int
    {
        return min(self::MAX_OVERTIME_MINUTES, self::minutesBetween($sessionEnd, $timeOut));
    }

    /**
     * The start and the end of the morning and afternoon sessions, from the business settings.
     *
     * @return array{morning: array{start: string, end: string}, afternoon: array{start: string, end: string}}
     */
    public static function schedule(): array
    {
        $stored = DB::table('system_settings')
            ->whereIn('setting_key', ['qr_morning_start', 'qr_morning_end', 'qr_afternoon_start', 'qr_afternoon_end'])
            ->pluck('setting_value', 'setting_key')->all();

        return [
            'morning' => ['start' => $stored['qr_morning_start'] ?? '08:00:00', 'end' => $stored['qr_morning_end'] ?? '12:00:00'],
            'afternoon' => ['start' => $stored['qr_afternoon_start'] ?? '13:00:00', 'end' => $stored['qr_afternoon_end'] ?? '17:00:00'],
        ];
    }

    /**
     * Hours worked in a day: every morning and afternoon session that has both a time in and a time out. The overtime session
     * has its own minutes and is not part of it. With the schedule, a session is counted from its start at the earliest: the time
     * before it (an early scan) is not working time.
     *
     * @param object|array<string, mixed> $row an attendance row
     * @param array{morning: array{start: string, end: string}, afternoon: array{start: string, end: string}}|null $schedule from {@see schedule()}
     */
    public static function totalHours(object|array $row, ?array $schedule = null): float
    {
        $row = (array) $row;
        $seconds = 0;
        foreach (['morning', 'afternoon'] as $part) {
            $in = $row["{$part}_time_in"] ?? null;
            $out = $row["{$part}_time_out"] ?? null;
            if ($in && $out) {
                $from = self::secondsOfDay($in);
                if ($schedule !== null) {
                    $from = max($from, self::secondsOfDay($schedule[$part]['start']));
                }
                $seconds += max(0, self::secondsOfDay($out) - $from);
            }
        }

        return round($seconds / 3600, 2);
    }

    /**
     * Minutes a session was left before its end (0 when the time out is within the grace period of the end, or missing).
     */
    public static function undertimeOf(?string $timeIn, ?string $timeOut, string $sessionEnd): int
    {
        if (! $timeIn || ! $timeOut) {
            return 0;
        }
        $early = self::minutesBetween($timeOut, $sessionEnd);

        return $early > self::UNDERTIME_GRACE_MINUTES ? $early : 0;
    }

    /** Minutes a session was started after its start, when it was marked late (the same rule the payroll uses). */
    public static function lateOf(?string $status, ?string $timeIn, string $sessionStart): int
    {
        return $status === 'late' && $timeIn ? self::minutesBetween($sessionStart, $timeIn) : 0;
    }

    /**
     * What an attendance row says about the day: the minutes it was late, the minutes of early leaving and the hours worked.
     *
     * @param object|array<string, mixed> $row
     * @param array{morning: array{start: string, end: string}, afternoon: array{start: string, end: string}} $schedule from {@see schedule()}
     * @return array{late_minutes: int, undertime_minutes: int, total_hours: float}
     */
    public static function dayFigures(object|array $row, array $schedule): array
    {
        $row = (array) $row;
        $late = 0;
        $undertime = 0;
        foreach (['morning', 'afternoon'] as $part) {
            $late += self::lateOf($row["status_{$part}"] ?? null, $row["{$part}_time_in"] ?? null, $schedule[$part]['start']);
            $undertime += self::undertimeOf($row["{$part}_time_in"] ?? null, $row["{$part}_time_out"] ?? null, $schedule[$part]['end']);
        }

        return ['late_minutes' => $late, 'undertime_minutes' => $undertime, 'total_hours' => self::totalHours($row, $schedule)];
    }
}
