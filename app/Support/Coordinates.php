<?php

namespace App\Support;

use DomainException;

/**
 * Google coordinates of a NAP box or a client's home. People paste what Google Maps gives them ("9.6541, 124.1234" from the
 * right-click menu, or the address of the map), so both are understood.
 */
final class Coordinates
{
    /** The Philippines: a swapped pair (longitude first) or a typing slip falls outside and is refused. */
    private const LATITUDE = [4.0, 22.0];
    private const LONGITUDE = [116.0, 128.0];

    /**
     * "9.6541, 124.1234", "9.6541 124.1234" or a Google Maps address ("...@9.6541,124.1234,17z", "...?q=9.6541,124.1234",
     * "...!3d9.6541!4d124.1234") as [latitude, longitude]. Throws a DomainException with a message to show the person.
     *
     * @return array{0: float, 1: float}
     */
    public static function parse(string $text, string $what = 'location'): array
    {
        $text = trim(urldecode($text));
        if ($text === '') {
            throw new DomainException("Enter the Google coordinates of the {$what}, for example 9.6541, 124.1234.");
        }

        $number = '(-?\d{1,3}(?:\.\d+)?)';
        $patterns = [
            '/!3d' . $number . '!4d' . $number . '/',
            '/@' . $number . ',' . $number . '/',
            '/[?&](?:q|query|ll|destination)=' . $number . '[,+ ]\s*' . $number . '/',
            '/^\(?\s*' . $number . '\s*[,;\s]\s*' . $number . '\s*\)?$/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $latitude = (float) $match[1];
                $longitude = (float) $match[2];
                if ($latitude < self::LATITUDE[0] || $latitude > self::LATITUDE[1] || $longitude < self::LONGITUDE[0] || $longitude > self::LONGITUDE[1]) {
                    throw new DomainException("The coordinates of the {$what} are outside the Philippines. Copy them from Google Maps as latitude, longitude, for example 9.6541, 124.1234.");
                }

                return [round($latitude, 7), round($longitude, 7)];
            }
        }

        throw new DomainException("Could not read the coordinates of the {$what}. Copy them from Google Maps as latitude, longitude, for example 9.6541, 124.1234.");
    }

    /** "9.654100, 124.123400" for a form field, or an empty text when the place has none. */
    public static function format(mixed $latitude, mixed $longitude): string
    {
        if ($latitude === null || $latitude === '' || $longitude === null || $longitude === '') {
            return '';
        }

        return number_format((float) $latitude, 6, '.', '') . ', ' . number_format((float) $longitude, 6, '.', '');
    }

    /** The Google Maps page of the place, or null when it has no coordinates. */
    public static function mapUrl(mixed $latitude, mixed $longitude): ?string
    {
        if ($latitude === null || $latitude === '' || $longitude === null || $longitude === '') {
            return null;
        }

        return 'https://www.google.com/maps?q=' . number_format((float) $latitude, 6, '.', '') . ',' . number_format((float) $longitude, 6, '.', '');
    }
}
