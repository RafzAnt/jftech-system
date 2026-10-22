<?php

namespace App\Support;

class AttendanceOrder
{
    /**
     * Newest day first and, within a day, the most recent scan first. Employees without a scan yet come last (the
     * sort is stable, so they keep the order the query gave them).
     */
    public static function latestFirst(array $rows): array
    {
        $key = static fn (array $row): array => [
            (string) ($row['attendance_date'] ?? ''),
            self::lastScan($row),
            (int) ($row['attendance_id'] ?? $row['id'] ?? 0),
        ];
        usort($rows, static fn (array $a, array $b): int => $key($b) <=> $key($a));

        return $rows;
    }

    private static function lastScan(array $row): string
    {
        $times = array_filter([
            $row['morning_time_in'] ?? null, $row['morning_time_out'] ?? null,
            $row['afternoon_time_in'] ?? null, $row['afternoon_time_out'] ?? null,
            $row['overtime_time_in'] ?? null, $row['overtime_time_out'] ?? null,
        ]);

        return $times ? (string) max($times) : '';
    }
}
