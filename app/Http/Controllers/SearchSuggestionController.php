<?php

namespace App\Http\Controllers;

use App\Support\BranchScope;
use App\Support\SearchSuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the one search box of a list page suggests while someone types (see App\Support\SearchSuggestions): q is the whole text of the
 * box, so the step (branch, barangay, record) can follow from what was typed before.
 */
class SearchSuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', 'clients');
        abort_unless(array_key_exists($type, SearchSuggestions::TYPES), 404);

        // The branch is the account's own, whatever the address bar says: a branch admin is never offered another branch's names.
        return response()->json(SearchSuggestions::for($type, mb_substr((string) $request->query('q', ''), 0, 200), BranchScope::current()));
    }
}
