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
        return $this->post('/api/v1/organizations/enrich', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function organizationSearch(array $payload): array
    {
        return $this->post('/api/v1/organizations/search', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function organizationTopPeople(array $payload): array
    {
        return $this->post('/api/v1/mixed_people/organization_top_people', $payload);
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

        try {
            $response = Http::timeout(20)
                ->retry(1, 500)
                ->acceptJson()
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Apollo request gagal: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->ok()) {
            throw new \RuntimeException($this->buildApiErrorMessage($response));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        return $json;
    }

    private function buildApiErrorMessage(Response $response): string
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
            default => "Apollo HTTP {$status}.",
        };

        if ($message === '') {
            return $base;
        }

        return $base . ' ' . $message;
    }
}
