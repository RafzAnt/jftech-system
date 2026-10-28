<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Where a place is, so the coordinates of a new client fill in by themselves once the barangay is chosen: the barangay of the branch
 * from OpenStreetMap (Nominatim), or the town of the branch when the map has no such barangay. The result is a starting point near
 * the client; the technician or admin still moves it to the exact house by pasting coordinates from Google Maps.
 *
 * Nominatim asks for at most one request a second and a real User-Agent, and a barangay never moves, so every answer is kept (a place
 * that was not found for a day, a place that was found for three months) and a failed request is never kept.
 */
final class Geocoder
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    /** Bohol: a result outside it is some other place of the same name. */
    private const BOHOL = ['south' => 9.40, 'north' => 10.30, 'west' => 123.60, 'east' => 124.80];

    private const FOUND_FOR_DAYS = 90;
    private const NOT_FOUND_FOR_DAYS = 1;

    /**
     * The place of a barangay of a branch (the branch's town when $barangay is empty or not on the map).
     *
     * @return array{found: bool, error?: bool, latitude?: float, longitude?: float, coordinates?: string, label?: string, precision?: string}
     */
    public static function place(string $branch, string $barangay = ''): array
    {
        $town = self::town($branch);
        $barangay = trim($barangay);
        if ($town === null) {
            return ['found' => false];
        }

        $key = 'geocode:' . mb_strtolower($branch) . ':' . mb_strtolower($barangay);
        $kept = Cache::get($key);
        if (is_array($kept)) {
            return $kept;
        }

        $answer = null;
        if ($barangay !== '') {
            $hit = self::search("{$barangay}, {$town}, Bohol, Philippines", $town, $barangay);
            if ($hit === false) {
                return ['found' => false, 'error' => true];
            }
            if ($hit !== null) {
                $answer = self::answer($hit, "{$barangay}, {$branch}", 'barangay');
            }
        }
        if ($answer === null) {
            $hit = self::search("{$town}, Bohol, Philippines", $town, null);
            if ($hit === false) {
                return ['found' => false, 'error' => true];
            }
            $answer = $hit === null ? ['found' => false] : self::answer($hit, $branch, 'town');
        }

        Cache::put($key, $answer, now()->addDays($answer['found'] ? self::FOUND_FOR_DAYS : self::NOT_FOUND_FOR_DAYS));

        return $answer;
    }

    /** The name the map knows the branch's town by ("City of Tagbilaran" for Tagbilaran), or null for a branch that does not exist. */
    private static function town(string $branch): ?string
    {
        foreach ((array) config('bohol_branches', []) as $name => $town) {
            if (strcasecmp((string) $name, trim($branch)) === 0) {
                return (string) $town;
            }
        }

        return null;
    }

    /** @return array{found: true, latitude: float, longitude: float, coordinates: string, label: string, precision: string} */
    private static function answer(array $hit, string $label, string $precision): array
    {
        return [
            'found' => true, 'latitude' => $hit['latitude'], 'longitude' => $hit['longitude'],
            'coordinates' => Coordinates::format($hit['latitude'], $hit['longitude']), 'label' => $label, 'precision' => $precision,
        ];
    }

    /**
     * The first result that is a place (a village, a barangay boundary, a town) in the town, and when $name is given has that name.
     * Null when the map has none, false when the map could not be asked.
     *
     * @return array{latitude: float, longitude: float}|null|false
     */
    private static function search(string $query, string $town, ?string $name): array|null|false
    {
        self::politeWait();

        try {
            $response = Http::acceptJson()
                ->withUserAgent('JFTECH-WiFi-Management-System/1.0 (client-location)')
                ->timeout(8)
                ->get(self::URL, [
                    'q' => $query, 'format' => 'jsonv2', 'limit' => 8, 'countrycodes' => 'ph', 'addressdetails' => 1,
                    'viewbox' => self::BOHOL['west'] . ',' . self::BOHOL['north'] . ',' . self::BOHOL['east'] . ',' . self::BOHOL['south'], 'bounded' => 1,
                ]);
        } catch (Throwable) {
            return false;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            return false;
        }

        $townWord = trim((string) preg_replace('/^(city|municipality) of /i', '', $town));
        foreach ($response->json() as $place) {
            $latitude = (float) ($place['lat'] ?? 0);
            $longitude = (float) ($place['lon'] ?? 0);
            $inBohol = $latitude >= self::BOHOL['south'] && $latitude <= self::BOHOL['north'] && $longitude >= self::BOHOL['west'] && $longitude <= self::BOHOL['east'];
            // A school or a shop that happens to carry the name is somewhere else: only a place counts.
            $isPlace = in_array($place['category'] ?? '', ['place', 'boundary'], true);
            $inTown = mb_stripos((string) ($place['display_name'] ?? ''), $townWord) !== false;
            $named = $name === null || mb_strtolower(trim((string) ($place['name'] ?? ''))) === mb_strtolower($name);
            if ($inBohol && $isPlace && $inTown && $named) {
                return ['latitude' => round($latitude, 7), 'longitude' => round($longitude, 7)];
            }
        }

        return null;
    }

    /** Keeps to Nominatim's one request a second, also between two people searching at the same time. */
    private static function politeWait(): void
    {
        $gap = (float) config('services.geocoder.gap', 1.1);
        $last = (float) Cache::get('geocode:last-request', 0);
        $wait = $gap - (microtime(true) - $last);
        if ($wait > 0 && $wait <= $gap) {
            usleep((int) ($wait * 1_000_000));
        }
        Cache::put('geocode:last-request', microtime(true), 60);
    }
}
