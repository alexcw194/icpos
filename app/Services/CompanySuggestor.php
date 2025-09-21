<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CompanySuggestor
{
    /**
     * Cari perusahaan via Google Places.
     * Mengambil name, address, website, phone (butuh 1x panggil details per result).
     */
    public function search(string $q, int $limit = 5): array
    {
        $key = config('services.google_places.key');
        if (!$key) {
            return [];
        }

        try {
            // 1) Text Search untuk dapatkan daftar place_id
            $resp = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                'query'  => $q,
                'key'    => $key,
                'region' => 'ID',               // bias Indonesia
                'type'   => 'establishment',
            ])->json();
        } catch (\Throwable $e) {
            return [];
        }

        $results = collect(data_get($resp, 'results', []))->take($limit);

        // 2) Ambil detail (website/phone) per place_id
        return $results->map(function ($r) use ($key) {
            $placeId = $r['place_id'] ?? null;
            $details = null;

            if ($placeId) {
                try {
                    $d = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                        'place_id' => $placeId,
                        'fields'   => 'name,formatted_address,website,international_phone_number,formatted_phone_number,types',
                        'key'      => $key,
                    ])->json();
                    $details = data_get($d, 'result', null);
                } catch (\Throwable $e) {
                    // abaikan error, tetap kembalikan minimal name/address dari textsearch
                }
            }

            $name    = $details['name'] ?? $r['name'] ?? '';
            $addr    = $details['formatted_address'] ?? ($r['formatted_address'] ?? null);
            $website = $details['website'] ?? null;
            $phone   = $details['international_phone_number'] ?? ($details['formatted_phone_number'] ?? null);

            return [
                'name'    => $name,
                'address' => $addr,
                'website' => $website,
                'domain'  => self::extractDomain($website),
                'phone'   => $phone,
                'place_id'=> $placeId,
                'types'   => $details['types'] ?? ($r['types'] ?? []),
            ];
        })->filter(fn ($x) => $x['name'])->values()->all();
    }

    public static function extractDomain(?string $url): ?string
    {
        if (!$url) return null;
        $h = parse_url($url, PHP_URL_HOST);
        if (!$h) return null;
        return preg_replace('/^www\./', '', strtolower($h));
    }
}
