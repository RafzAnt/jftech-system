<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class LocationController extends Controller
{
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
