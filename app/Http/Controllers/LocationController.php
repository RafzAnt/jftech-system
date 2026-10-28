<?php

namespace App\Http\Controllers;

use App\Support\BranchScope;
use App\Support\Geocoder;
use App\Support\NapBoxes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class LocationController extends Controller
{
    /**
     * Where a barangay is, for the coordinates of a new client to fill in by themselves (the branch's town when the map has no such
     * barangay). A branch admin always gets their own branch, whatever the address bar says.
     */
    public function geocode(Request $request): JsonResponse
    {
        $branch = BranchScope::current() ?? trim((string) $request->query('branch', ''));

        return response()->json(self::place($branch, (string) $request->query('barangay', '')));
    }

    /**
     * The answer of Geocoder::place for a barangay of the branch (a barangay the branch does not have is looked up as the town), as
     * the page and the employee app read it.
     *
     * @return array<string, mixed>
     */
    public static function place(string $branch, string $barangay): array
    {
        $barangay = trim($barangay);
        $list = NapBoxes::barangaysOf($branch);
        foreach ($list as $option) {
            if (strcasecmp($option, $barangay) === 0) {
                $barangay = $option;
                break;
            }
        }
        if ($list !== [] && ! in_array($barangay, $list, true)) {
            $barangay = '';
        }

        return Geocoder::place($branch, $barangay);
    }

    /** Search OpenStreetMap locations only within Balilihan, Bohol. */
    public function searchBalilihan(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:160']]);
        if (RateLimiter::tooManyAttempts('balilihan-geocode', 1)) {
            return response()->json(['message' => 'Please wait a moment before searching again.'], 429);
        }
        RateLimiter::hit('balilihan-geocode', 1);
        $query = trim($data['q']);
        if (! str_contains(strtolower($query), 'balilihan')) $query .= ', Balilihan, Bohol, Philippines';

        $response = Http::acceptJson()
            ->withUserAgent('JFTECH-WiFi-Management-System/1.0 (address-search)')
            ->timeout(8)
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query, 'format' => 'jsonv2', 'addressdetails' => 1, 'limit' => 5,
                'countrycodes' => 'ph', 'viewbox' => '123.88,9.88,124.08,9.62', 'bounded' => 1,
            ]);

        if (! $response->successful()) {
            return response()->json(['message' => 'Address search is temporarily unavailable. Please place the pin manually on the map.'], 503);
        }

        return response()->json(['results' => collect($response->json())->map(fn (array $place) => [
            'label' => $place['display_name'], 'lat' => (float) $place['lat'], 'lng' => (float) $place['lon'],
        ])->values()]);
    }
}
