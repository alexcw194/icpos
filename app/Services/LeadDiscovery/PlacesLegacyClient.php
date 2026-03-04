<?php

namespace App\Services\LeadDiscovery;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PlacesLegacyClient
{
    public function nearbySearch(
        float $lat,
        float $lng,
        int $radiusM,
        string $keyword,
        ?string $pageToken = null
    ): array {
        $baseUrl = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
        $key = (string) config('services.google_places.key', '');
        $timeout = $this->settingInt('lead_discovery.request_timeout_sec', 20);
        $retryMax = $this->settingInt('lead_discovery.retry_max', 2);

        $query = $pageToken
            ? ['pagetoken' => $pageToken, 'key' => $key]
            : [
                'location' => $lat . ',' . $lng,
                'radius' => $radiusM,
                'keyword' => $keyword,
                'region' => (string) config('services.google_places.region', 'ID'),
                'language' => (string) config('services.google_places.lang', 'id'),
                'key' => $key,
            ];

        $response = Http::timeout($timeout)
            ->retry($retryMax, 400)
            ->get($baseUrl, $query);

        $json = $response->json();
        $status = strtoupper((string) Arr::get($json, 'status', 'UNKNOWN'));

        return [
            'http_code' => $response->status(),
            'status' => $status,
            'results' => Arr::get($json, 'results', []),
            'next_page_token' => Arr::get($json, 'next_page_token'),
            'request_url' => $this->buildRequestUrl($baseUrl, $query),
            'request_payload' => $query,
            'raw' => $json,
            'retryable' => in_array($status, ['OVER_QUERY_LIMIT', 'UNKNOWN_ERROR'], true),
        ];
    }

    public function textSearch(string $textQuery, ?string $pageToken = null): array
    {
        $baseUrl = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
        $key = (string) config('services.google_places.key', '');
        $timeout = $this->settingInt('lead_discovery.request_timeout_sec', 20);
        $retryMax = $this->settingInt('lead_discovery.retry_max', 2);

        $query = $pageToken
            ? ['pagetoken' => $pageToken, 'key' => $key]
            : [
                'query' => $textQuery,
                'region' => (string) config('services.google_places.region', 'ID'),
                'language' => (string) config('services.google_places.lang', 'id'),
                'key' => $key,
            ];

        $response = Http::timeout($timeout)
            ->retry($retryMax, 400)
            ->get($baseUrl, $query);
        $json = $response->json();
        $status = strtoupper((string) Arr::get($json, 'status', 'UNKNOWN'));

        return [
            'http_code' => $response->status(),
            'status' => $status,
            'results' => Arr::get($json, 'results', []),
            'next_page_token' => Arr::get($json, 'next_page_token'),
            'request_url' => $this->buildRequestUrl($baseUrl, $query),
            'request_payload' => $query,
            'raw' => $json,
            'retryable' => in_array($status, ['OVER_QUERY_LIMIT', 'UNKNOWN_ERROR'], true),
        ];
    }

    private function buildRequestUrl(string $baseUrl, array $query): string
    {
        $safe = $query;
        if (isset($safe['key'])) {
            $safe['key'] = Str::mask((string) $safe['key'], '*', 3);
        }

        return $baseUrl . '?' . http_build_query($safe);
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
