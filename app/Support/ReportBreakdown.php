<?php

namespace App\Support;

/**
 * The grouping behind the printed reports: rows are counted and added up by a label (a status, a plan, a category), so a report
 * prints "Billing by status" instead of every bill. The tables are drawn by partials/report_breakdown.blade.php.
 */
final class ReportBreakdown
{
    /** The label of a row that has no value for the grouping (a client with no plan, an item with no supplier). */
    public const BLANK = 'Not specified';

    /**
     * Group rows by a label. Every group has the number of rows (`count`) and one figure per entry of $sums.
     *
     * @param iterable<array<string, mixed>|object> $rows
     * @param callable(mixed): mixed $label the label of a row
     * @param array<string, callable(mixed): (int|float)> $sums figures to add up, keyed by the name they get in the group
     * @param list<string> $order labels printed first, in this order (a fixed order such as Paid, Pending, Overdue); the rest follow by size
     * @param int $limit when above 0, the groups past this many are folded into "All other (N)"
     * @return list<array<string, mixed>>
     */
    public static function group(iterable $rows, callable $label, array $sums = [], array $order = [], int $limit = 0): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) $label($row));
            $name = $name === '' ? self::BLANK : $name;
            $groups[$name] ??= ['label' => $name, 'count' => 0] + array_fill_keys(array_keys($sums), 0);
            $groups[$name]['count']++;
            foreach ($sums as $key => $sum) {
                $groups[$name][$key] += $sum($row);
            }
        }

        $position = array_flip($order);
        uasort($groups, static function (array $a, array $b) use ($position): int {
            $first = $position[$a['label']] ?? PHP_INT_MAX;
            $second = $position[$b['label']] ?? PHP_INT_MAX;

            return [$first, $b['count'], $a['label']] <=> [$second, $a['count'], $b['label']];
        });
        $groups = array_values($groups);

        if ($limit > 0 && count($groups) > $limit) {
            $rest = array_splice($groups, $limit);
            $other = ['label' => 'All other (' . count($rest) . ')', 'count' => 0] + array_fill_keys(array_keys($sums), 0);
            foreach ($rest as $group) {
                foreach ($other as $key => $value) {
                    if ($key !== 'label') {
                        $other[$key] += $group[$key];
                    }
                }
            }
            $groups[] = $other;
        }

        return $groups;
    }
}
