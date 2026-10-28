<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The search of a list page: ONE text box for everything. The super admin types a branch, a barangay or a name (or several of
 * them together, for example "Loon Agsoso Ana"); a branch admin has no branch to type because their own is automatic, so only a
 * barangay or a name. Every word has to be found in at least one of the columns (name, code, mobile number, branch, barangay, ...),
 * so the words narrow each other. Leaving it empty shows every record the account may see (BranchScope still applies first).
 * Used with resources/views/pages/partials/client_location_filter, which draws the box.
 */
final class LocationFilter
{
    /**
     * @return array{searchTerm: string, scopeBranch: ?string}
     */
    public static function fromRequest(Request $request): array
    {
        return [
            'searchTerm' => trim((string) $request->query('search', '')),
            // The branch the account is limited to; null for the super admin.
            'scopeBranch' => BranchScope::current(),
        ];
    }

    /**
     * What the box says it is for: "Branch, Barangay, Clients" for the super admin and "Barangay, Clients" for a branch admin
     * (their branch is automatic). $withBarangay is false for lists that have no barangay (users, login history).
     */
    public static function placeholder(?string $scopeBranch, string $subject, bool $withBarangay = true): string
    {
        return implode(', ', array_filter([
            $scopeBranch === null ? 'Branch' : null,
            $withBarangay ? 'Barangay' : null,
            $subject,
        ]));
    }

    /**
     * The words of a search, as typed: separated by spaces or commas.
     *
     * @return array<int, string>
     */
    public static function words(string $term): array
    {
        return array_values(array_filter(preg_split('/[\s,]+/u', trim($term)) ?: [], static fn (string $word): bool => $word !== ''));
    }

    /**
     * " AND (every word matches one of the columns)" as [sql, params], to append to a WHERE that already has a condition.
     *
     * @param array<int, string> $columns SQL expressions to look in
     */
    public static function wordConditions(string $term, array $columns): array
    {
        $sql = '';
        $params = [];
        foreach (self::words($term) as $word) {
            $sql .= ' AND (' . implode(' OR ', array_map(static fn (string $column): string => "LOWER(COALESCE({$column}, '')) LIKE LOWER(?)", $columns)) . ')';
            foreach ($columns as $unused) {
                $params[] = '%' . $word . '%';
            }
        }

        return [$sql, $params];
    }

    /**
     * The conditions of the search for a query over clients, as [sql, params] to append to a WHERE that already has one.
     *
     * @param array{searchTerm: string} $filter    from fromRequest()
     * @param string               $clientAlias    the clients table, for example "c"
     * @param string               $barangayJoin   the barangays table joined on clients.barangay_id, for example "br"
     * @param array<int, string>   $moreSearchColumns other columns the search looks in (a reference number, ...)
     */
    public static function clientConditions(array $filter, string $clientAlias = 'c', string $barangayJoin = 'br', array $moreSearchColumns = []): array
    {
        return self::wordConditions($filter['searchTerm'], array_merge([
            "{$clientAlias}.full_name", "{$clientAlias}.client_code", "{$clientAlias}.contact_number", "{$clientAlias}.branch_name",
            "COALESCE({$clientAlias}.registered_barangay_name, {$barangayJoin}.barangay_name)",
        ], $moreSearchColumns));
    }

    /**
     * The same for a query over employees. An employee has a branch but no barangay of their own, only a free-text address, so a
     * barangay is found when the address mentions it.
     *
     * @param array{searchTerm: string} $filter       from fromRequest()
     * @param string               $employeeAlias     the employees table, for example "e"
     * @param array<int, string>   $moreSearchColumns other columns (for example the branch recorded on an attendance row)
     */
    public static function employeeConditions(array $filter, string $employeeAlias = 'e', array $moreSearchColumns = []): array
    {
        return self::wordConditions($filter['searchTerm'], array_merge(
            array_map(static fn (string $column): string => "{$employeeAlias}.{$column}", ['full_name', 'employee_code', 'position', 'contact_number', 'branch_name', 'address']),
            $moreSearchColumns,
        ));
    }
}
