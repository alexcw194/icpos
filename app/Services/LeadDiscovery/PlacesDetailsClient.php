<?php

namespace App\Services\LeadDiscovery;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class PlacesDetailsClient
{
    public function details(string $placeId, array $fields = []): array
    {
        $key = (string) config('services.google_places.key', '');
        $timeout = $this->settingInt('lead_discovery.request_timeout_sec', 20);
        $retryMax = $this->settingInt('lead_discovery.retry_max', 2);

        if ($fields === []) {
            $fields = [
                'name',
                'formatted_address',
                'geometry/location',
                'international_phone_number',
                'website',
                'url',
                'type',
            ];
        }

        $response = Http::timeout($timeout)
            ->retry($retryMax, 400)
            ->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => implode(',', $fields),
                'key' => $key,
            ]);

        $json = $response->json();

        return [
            'http_code' => $response->status(),
            'status' => strtoupper((string) Arr::get($json, 'status', 'UNKNOWN')),
            'result' => Arr::get($json, 'result'),
            'raw' => $json,
        ];
    }

    private function settingInt(string $key, int $default): int
    {
        try {
            return (int) Setting::get($key, $default);
        } catch (Throwable $e) {
            return $default;
        }
    }
}
