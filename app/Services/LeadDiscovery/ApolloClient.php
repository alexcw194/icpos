<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ApolloClient
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function organizationEnrich(array $payload): array
    {
        // Apollo current docs: GET /api/v1/organizations/enrich
        try {
            return $this->get('/api/v1/organizations/enrich', $payload);
        } catch (\RuntimeException $e) {
            // Legacy compatibility: some setups still accept POST.
            if (!str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        return $this->post('/api/v1/organizations/enrich', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function organizationSearch(array $payload): array
    {
        // Apollo current docs: POST /api/v1/mixed_companies/search
        try {
            return $this->post('/api/v1/mixed_companies/search', $payload);
        } catch (\RuntimeException $e) {
            // Legacy compatibility.
            if (!str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        return $this->post('/api/v1/organizations/search', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function organizationTopPeople(array $payload): array
    {
        // Apollo current docs: POST /api/v1/mixed_people/api_search
        // Bridge old payload (organization_id) into supported filters.
        $organizationId = trim((string) ($payload['organization_id'] ?? ''));
        $page = (int) ($payload['page'] ?? 1);
        $perPage = (int) ($payload['per_page'] ?? 5);

        $searchPayload = [
            'page' => $page > 0 ? $page : 1,
            'per_page' => $perPage > 0 ? $perPage : 5,
        ];

        if ($organizationId !== '') {
            // Keep both keys for compatibility across Apollo API revisions.
            $searchPayload['q_organization_ids'] = [$organizationId];
            $searchPayload['organization_ids'] = [$organizationId];
        }

        try {
            return $this->post('/api/v1/mixed_people/api_search', $searchPayload);
        } catch (\RuntimeException $e) {
            if (!str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        return $this->post('/api/v1/mixed_people/organization_top_people', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function get(string $path, array $payload): array
    {
        $apiKey = trim((string) config('services.apollo.key', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Apollo API key belum diisi.');
        }

        $baseUrl = rtrim((string) config('services.apollo.base_url', 'https://api.apollo.io'), '/');
        $url = $baseUrl . $path;

        try {
            $query = $payload;
            if (!array_key_exists('api_key', $query)) {
                $query['api_key'] = $apiKey;
            }

            $response = Http::timeout(20)
                ->retry(1, 500)
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                ])
                ->get($url, $query);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Apollo request gagal: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->ok()) {
            throw new \RuntimeException($this->buildApiErrorMessage($response, 'GET', $path));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        return $json;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $apiKey = trim((string) config('services.apollo.key', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Apollo API key belum diisi.');
        }

        $baseUrl = rtrim((string) config('services.apollo.base_url', 'https://api.apollo.io'), '/');
        $url = $baseUrl . $path;
        $body = $payload;
        if (!array_key_exists('api_key', $body)) {
            $body['api_key'] = $apiKey;
        }

        try {
            $response = Http::timeout(20)
                ->retry(1, 500)
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                ])
                ->post($url, $body);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Apollo request gagal: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->ok()) {
            throw new \RuntimeException($this->buildApiErrorMessage($response, 'POST', $path));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        return $json;
    }

    private function buildApiErrorMessage(Response $response, string $method, string $path): string
    {
        $status = $response->status();
        $message = (string) data_get($response->json(), 'error.message', '');
        if ($message === '') {
            $message = (string) data_get($response->json(), 'message', '');
        }

        $base = match ($status) {
            401 => 'Apollo API key tidak valid (401).',
            403 => 'Akses Apollo ditolak (403).',
            429 => 'Apollo rate limit/quota tercapai (429).',
            default => "Apollo HTTP {$status} ({$method} {$path}).",
        };

        if ($message === '') {
            return $base;
        }

        return $base . ' ' . $message;
    }
}
