<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * What the one search box of a list page suggests while someone types (see LocationFilter). The box is typed in the order of its
 * placeholder, "Branch, Barangay, Clients", and each step suggests only its own kind:
 *
 *   1. a branch (only the super admin has one to type; a branch admin's own is automatic): only branches are offered;
 *   2. then a barangay: only the barangays of that branch;
 *   3. then a record (client, employee, NAP box or user account): only those of that branch and barangay.
 *
 * The step comes from what was typed before: once a branch is in the text the next step is a barangay, once a barangay is in it the
 * next is a record. A step that finds nothing lets the next one try (typing "Boctol" first still finds the barangay). Everything is
 * limited to what the account may see (BranchScope), so a branch admin is never offered another branch's names.
 */
final class SearchSuggestions
{
    /** The lists that have a suggestion. The value is whether the records have a barangay (user accounts do not). */
    public const TYPES = ['clients' => true, 'employees' => true, 'nap_boxes' => true, 'users' => false];

    private const BRANCHES = 8;
    private const BARANGAYS = 8;
    private const RECORDS = 8;

    /** The records are only looked up from this many letters; branches and barangays from the first one. */
    private const RECORD_MIN_LENGTH = 2;

    /**
     * The suggestions for the text of the box. The part being typed is what follows the last comma; when that finds nothing and has
     * several words, its last word is tried (so "Loon Ags" finds Agsoso). Returns that part ("query": the caller replaces it with the
     * suggestion picked), the step it was looked up in ("stage": branch, barangay or record) and the suggestions, each
     * {group, label, detail, value}.
     *
     * @return array{query: string, stage: ?string, suggestions: array<int, array{group: string, label: string, detail: ?string, value: string}>}
     */
    public static function for(string $type, string $text, ?string $scopeBranch): array
    {
        $comma = mb_strrpos($text, ',');
        $before = $comma === false ? '' : mb_substr($text, 0, $comma + 1);
        $segment = trim($comma === false ? $text : mb_substr($text, $comma + 1));

        $attempts = [[$segment, $before]];
        $words = LocationFilter::words($segment);
        if (count($words) > 1) {
            // What precedes the last word still says which branch and barangay are meant.
            $last = end($words);
            $attempts[] = [$last, $before . ' ' . mb_substr($segment, 0, (int) mb_strrpos($segment, $last))];
        }

        if (array_key_exists($type, self::TYPES)) {
            foreach ($attempts as [$query, $context]) {
                if ($query === '') {
                    continue;
                }
                [$stage, $found] = self::lookup($type, $query, $context, $scopeBranch);
                if ($found !== []) {
                    return ['query' => $query, 'stage' => $stage, 'suggestions' => $found];
                }
            }
        }

        return ['query' => $segment, 'stage' => null, 'suggestions' => []];
    }

    /** @return array{0: string, 1: array<int, array{group: string, label: string, detail: ?string, value: string}>} */
    private static function lookup(string $type, string $query, string $context, ?string $scopeBranch): array
    {
        $hasBarangays = self::TYPES[$type];
        // The branch is the account's own, or the one typed before (only the super admin can).
        $branch = $scopeBranch ?? self::branchIn($context);
        $barangay = $hasBarangays ? self::barangayIn($context, $branch) : null;

        $stages = [];
        if ($scopeBranch === null && $branch === null && $barangay === null) {
            $stages[] = 'branch';
        }
        if ($hasBarangays && $barangay === null) {
            $stages[] = 'barangay';
        }
        $stages[] = 'record';

        foreach ($stages as $stage) {
            $found = match ($stage) {
                'branch' => array_map(
                    static fn (string $name): array => ['group' => 'Branch', 'label' => $name, 'detail' => null, 'value' => $name],
                    self::match(array_keys((array) config('bohol_branches', [])), $query, self::BRANCHES),
                ),
                'barangay' => array_map(
                    static fn (array $row): array => ['group' => 'Barangay', 'label' => $row['name'], 'detail' => $row['branch'], 'value' => $row['name']],
                    self::barangays($query, $branch),
                ),
                default => mb_strlen($query) < self::RECORD_MIN_LENGTH ? [] : match ($type) {
                    'clients' => self::clients($query, $branch, $barangay),
                    'employees' => self::employees($query, $branch, $barangay),
                    'nap_boxes' => self::napBoxes($query, $branch, $barangay, $scopeBranch === null),
                    'users' => self::users($query, $branch, $scopeBranch !== null),
                },
            };
            if ($found !== []) {
                return [$stage, $found];
            }
        }

        return ['record', []];
    }

    /**
     * The names that contain the text, those that start with it first.
     *
     * @param array<int, string> $names
     * @return array<int, string>
     */
    private static function match(array $names, string $query, int $limit): array
    {
        $found = array_values(array_filter($names, static fn (string $name): bool => mb_stripos($name, $query) !== false));
        usort($found, static fn (string $a, string $b): int => [mb_stripos($a, $query) === 0 ? 0 : 1, $a] <=> [mb_stripos($b, $query) === 0 ? 0 : 1, $b]);

        return array_slice($found, 0, $limit);
    }

    /** The branch named last in the text, or null. */
    private static function branchIn(string $context): ?string
    {
        return self::lastNameIn($context, array_keys((array) config('bohol_branches', [])));
    }

    /** The barangay named last in the text (among the barangays of $branch when it is known), or null. */
    private static function barangayIn(string $context, ?string $branch): ?string
    {
        $names = [];
        foreach (self::barangayLists($branch) as $list) {
            array_push($names, ...$list);
        }

        return self::lastNameIn($context, $names);
    }

    /**
     * Which of the names stands last in the text as a whole word (the longer one when two end at the same place, so "Bonbon Lower"
     * wins over "Bonbon").
     *
     * @param array<int, string> $names
     */
    private static function lastNameIn(string $text, array $names): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $best = null;
        $bestEnd = -1;
        foreach (array_unique($names) as $name) {
            if (preg_match_all('/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/iu', $text, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            $end = end($matches[0])[1] + strlen($name);
            if ($end > $bestEnd || ($end === $bestEnd && mb_strlen($name) > mb_strlen((string) $best))) {
                $best = $name;
                $bestEnd = $end;
            }
        }

        return $best;
    }

    /**
     * The barangays of every branch, or of one branch when it is known.
     *
     * @return array<string, array<int, string>>
     */
    private static function barangayLists(?string $branch): array
    {
        $lists = [];
        foreach ((array) config('bohol_barangays', []) as $name => $barangays) {
            if ($branch === null || strcasecmp((string) $name, $branch) === 0) {
                $lists[(string) $name] = array_values($barangays);
            }
        }

        return $lists;
    }

    /** @return array<int, array{name: string, branch: ?string}> */
    private static function barangays(string $query, ?string $branch): array
    {
        $found = [];
        foreach (self::barangayLists($branch) as $branchName => $names) {
            foreach ($names as $name) {
                if (mb_stripos($name, $query) !== false) {
                    // Without a branch (the super admin typing a barangay first) the branch is shown, as several have a Poblacion.
                    $found[] = ['name' => $name, 'branch' => $branch === null ? $branchName : null];
                }
            }
        }
        usort($found, static fn (array $a, array $b): int => [mb_stripos($a['name'], $query) === 0 ? 0 : 1, $a['name'], $a['branch']] <=> [mb_stripos($b['name'], $query) === 0 ? 0 : 1, $b['name'], $b['branch']]);

        return array_slice($found, 0, self::BARANGAYS);
    }

    /** @return array<int, array{group: string, label: string, detail: ?string, value: string}> */
    private static function clients(string $query, ?string $branch, ?string $barangay): array
    {
        [$scopeSql, $scopeParams] = BranchScope::clients($branch, 'c');
        [$searchSql, $searchParams] = LocationFilter::wordConditions($query, ['c.full_name', 'c.client_code', 'c.contact_number']);
        $barangaySql = '';
        $barangayParams = [];
        if ($barangay !== null) {
            $barangaySql = " AND LOWER(COALESCE(c.registered_barangay_name, b.barangay_name, '')) = LOWER(?)";
            $barangayParams = [$barangay];
        }
        $rows = DB::select(
            "SELECT c.full_name, c.client_code, COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name
             FROM clients c LEFT JOIN barangays b ON c.barangay_id = b.id
             WHERE 1 = 1{$scopeSql}{$barangaySql}{$searchSql} ORDER BY c.full_name LIMIT " . self::RECORDS,
            [...$scopeParams, ...$barangayParams, ...$searchParams],
        );

        return array_map(static fn (object $row): array => [
            'group' => 'Client', 'label' => (string) $row->full_name,
            'detail' => implode(' · ', array_filter([$row->client_code, $barangay === null ? $row->barangay_name : null])), 'value' => (string) $row->full_name,
        ], $rows);
    }

    /** @return array<int, array{group: string, label: string, detail: ?string, value: string}> */
    private static function employees(string $query, ?string $branch, ?string $barangay): array
    {
        [$scopeSql, $scopeParams] = BranchScope::employees($branch, 'e');
        [$searchSql, $searchParams] = LocationFilter::wordConditions($query, ['e.full_name', 'e.employee_code', 'e.position']);
        // An employee has no barangay of their own, only an address that mentions it.
        $barangaySql = '';
        $barangayParams = [];
        if ($barangay !== null) {
            $barangaySql = " AND LOWER(COALESCE(e.address, '')) LIKE LOWER(?)";
            $barangayParams = ['%' . $barangay . '%'];
        }
        $rows = DB::select(
            "SELECT e.full_name, e.employee_code, e.position FROM employees e WHERE 1 = 1{$scopeSql}{$barangaySql}{$searchSql} ORDER BY e.full_name LIMIT " . self::RECORDS,
            [...$scopeParams, ...$barangayParams, ...$searchParams],
        );

        return array_map(static fn (object $row): array => [
            'group' => 'Employee', 'label' => (string) $row->full_name,
            'detail' => implode(' · ', array_filter([$row->employee_code, $row->position])), 'value' => (string) $row->full_name,
        ], $rows);
    }

    /** @return array<int, array{group: string, label: string, detail: ?string, value: string}> */
    private static function napBoxes(string $query, ?string $branch, ?string $barangay, bool $showBranch): array
    {
        [$scopeSql, $scopeParams] = BranchScope::clients($branch, 'n');
        [$searchSql, $searchParams] = LocationFilter::wordConditions($query, ['n.name', 'n.location']);
        $barangaySql = '';
        $barangayParams = [];
        if ($barangay !== null) {
            $barangaySql = " AND LOWER(n.barangay_name) = LOWER(?)";
            $barangayParams = [$barangay];
        }
        $rows = DB::select(
            "SELECT n.name, n.barangay_name, n.branch_name FROM nap_boxes n WHERE 1 = 1{$scopeSql}{$barangaySql}{$searchSql} ORDER BY n.name LIMIT " . self::RECORDS,
            [...$scopeParams, ...$barangayParams, ...$searchParams],
        );

        return array_map(static fn (object $row): array => [
            'group' => 'NAP Box', 'label' => (string) $row->name,
            'detail' => implode(' · ', array_filter([$barangay === null ? $row->barangay_name : null, $showBranch && $branch === null ? $row->branch_name : null])), 'value' => (string) $row->name,
        ], $rows);
    }

    /** @return array<int, array{group: string, label: string, detail: ?string, value: string}> */
    private static function users(string $query, ?string $branch, bool $techniciansOnly): array
    {
        [$scopeSql, $scopeParams] = BranchScope::users($branch, 'u');
        // A branch admin only manages the technician accounts of their branch (see the User Accounts page).
        $roleSql = $techniciansOnly ? " AND u.role = 'employee'" : '';
        [$searchSql, $searchParams] = LocationFilter::wordConditions($query, ['u.username', 'u.full_name']);
        $rows = DB::select(
            "SELECT u.username, u.full_name FROM users u WHERE 1 = 1{$scopeSql}{$roleSql}{$searchSql} ORDER BY u.full_name LIMIT " . self::RECORDS,
            [...$scopeParams, ...$searchParams],
        );

        return array_map(static fn (object $row): array => [
            'group' => 'User', 'label' => (string) ($row->full_name ?: $row->username),
            'detail' => (string) $row->username, 'value' => (string) ($row->full_name ?: $row->username),
        ], $rows);
    }
}
