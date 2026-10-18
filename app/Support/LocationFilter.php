<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The branch and barangay search of a list page: the branch (the super admin picks any; a branch admin only has their own),
 * the barangays of that branch, and a text search. Used with resources/views/pages/partials/client_location_filter, which
 * draws the fields. Leaving everything empty shows every record the account may see.
 */
final class LocationFilter
{
    /**
     * @return array{branches: array<string, mixed>, branchBarangays: array<string, array<int, string>>, selectedBranch: string, selectedBarangays: array<int, string>, searchTerm: string, scopeBranch: ?string}
     */
    public static function fromRequest(Request $request): array
    {
        $branches = config('bohol_branches', []);
        $branchBarangays = config('bohol_barangays', []);

        // A branch admin only works with their own branch: it is the only option and is always selected.
        $own = BranchScope::current();
        if ($own !== null) {
            $branches = array_intersect_key($branches, array_flip(array_filter(array_keys($branches), static fn (string $name): bool => strcasecmp($name, $own) === 0)));
        }

        $selectedBranch = $own !== null ? (array_key_first($branches) ?? '') : trim((string) $request->query('branch', ''));
        foreach (array_keys($branches) as $name) {
            if (strcasecmp($name, $selectedBranch) === 0) {
                $selectedBranch = $name;
                break;
            }
        }
        if (! array_key_exists($selectedBranch, $branches)) {
            $selectedBranch = '';
        }

        $valid = $selectedBranch !== '' ? ($branchBarangays[$selectedBranch] ?? []) : [];
        $requested = $request->query('barangays', []);
        $selectedBarangays = [];
        foreach (is_array($requested) ? $requested : [] as $name) {
            foreach ($valid as $option) {
                if (is_string($name) && strcasecmp(trim($name), $option) === 0) {
                    $selectedBarangays[$option] = $option;
                }
            }
        }

        return [
            'branches' => $branches,
            'branchBarangays' => $branchBarangays,
            'selectedBranch' => $selectedBranch,
            'selectedBarangays' => array_values($selectedBarangays),
            'searchTerm' => trim((string) $request->query('search', '')),
            // The branch the account is limited to; null for the super admin (the filter partial locks the branch box when set).
            'scopeBranch' => $own,
        ];
    }

    /**
     * The conditions of the filter for a query over clients, as [sql, params] to append to a WHERE that already has one.
     *
     * @param array<string, mixed> $filter       from fromRequest()
     * @param string               $clientAlias  the clients table, for example "c"
     * @param string               $barangayJoin the barangays table joined on clients.barangay_id, for example "br"
     * @param array<int, string>   $moreSearchColumns other columns the text search looks in (a reference number, ...)
     */
    public static function clientConditions(array $filter, string $clientAlias = 'c', string $barangayJoin = 'br', array $moreSearchColumns = []): array
    {
        $sql = '';
        $params = [];
        $barangayName = "COALESCE({$clientAlias}.registered_barangay_name, {$barangayJoin}.barangay_name, '')";

        if ($filter['selectedBranch'] !== '') {
            $sql .= " AND LOWER(COALESCE({$clientAlias}.branch_name, '')) = LOWER(?)";
            $params[] = $filter['selectedBranch'];
        }
        if ($filter['selectedBarangays'] !== []) {
            $sql .= " AND LOWER({$barangayName}) IN (" . implode(', ', array_fill(0, count($filter['selectedBarangays']), '?')) . ')';
            array_push($params, ...array_map('strtolower', $filter['selectedBarangays']));
        }
        if ($filter['searchTerm'] !== '') {
            $columns = array_merge(
                ["{$clientAlias}.full_name", "{$clientAlias}.client_code", "{$clientAlias}.contact_number", "{$clientAlias}.branch_name", $barangayName],
                $moreSearchColumns,
            );
            $sql .= ' AND (' . implode(' OR ', array_map(static fn (string $column): string => "LOWER(COALESCE({$column}, '')) LIKE LOWER(?)", $columns)) . ')';
            foreach ($columns as $unused) {
                $params[] = '%' . $filter['searchTerm'] . '%';
            }
        }

        return [$sql, $params];
    }
}
