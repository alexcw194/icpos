<?php

namespace App\Services\LeadDiscovery;

use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LeadDiscoveryAiClassifierService
{
    /**
     * @param array<string, mixed> $analysisResult
     * @return array<string, mixed>
     */
    public function classify(Prospect $prospect, array $analysisResult): array
    {
        $apiKey = trim((string) config('services.openai.key', ''));
        $model = trim((string) config('services.openai.model', 'gpt-4o-mini'));
        if ($apiKey === '') {
            return [
                'ai_status' => ProspectAnalysis::AI_STATUS_NOT_RUN,
                'ai_provider' => null,
                'ai_model' => null,
                'ai_industry_label' => null,
                'ai_sub_industry' => null,
                'ai_business_output' => null,
                'ai_hotel_star' => null,
                'ai_confidence' => null,
                'ai_payload_json' => null,
                'ai_error_message' => null,
            ];
        }

        $payload = $this->buildPayload($prospect, $analysisResult);
        $messages = [
            [
                'role' => 'system',
                'content' => 'You classify Indonesian business leads. Return JSON only.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n", [
                    'Classify this lead and return JSON with keys:',
                    'industry_label (string|null)',
                    'sub_industry (string|null)',
                    'business_output (string|null)',
                    'hotel_star (integer 1-5 or null)',
                    'confidence (number 0-100)',
                    'reasoning (short string)',
                    'Lead data:',
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]),
            ],
        ];

        try {
            $response = Http::timeout(25)
                ->retry(1, 600)
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (\Throwable $e) {
            return $this->failed($model, 'OpenAI request failed: ' . $e->getMessage());
        }

        if (!$response->ok()) {
            return $this->failed($model, 'OpenAI HTTP ' . $response->status());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        if ($content === '') {
            return $this->failed($model, 'OpenAI empty response content.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return $this->failed($model, 'OpenAI response is not valid JSON.');
        }

        $industry = $this->sanitizeNullableString(data_get($decoded, 'industry_label'));
        $subIndustry = $this->sanitizeNullableString(data_get($decoded, 'sub_industry'));
        $businessOutput = $this->sanitizeNullableString(data_get($decoded, 'business_output'));
        $hotelStar = $this->sanitizeHotelStar(data_get($decoded, 'hotel_star'));
        $confidence = $this->sanitizeConfidence(data_get($decoded, 'confidence'));

        return [
            'ai_status' => ProspectAnalysis::AI_STATUS_SUCCESS,
            'ai_provider' => 'openai',
            'ai_model' => $model,
            'ai_industry_label' => $industry,
            'ai_sub_industry' => $subIndustry,
            'ai_business_output' => $businessOutput,
            'ai_hotel_star' => $hotelStar,
            'ai_confidence' => $confidence,
            'ai_payload_json' => $decoded,
            'ai_error_message' => null,
        ];
    }

    /**
     * @param array<string, mixed> $analysisResult
     * @return array<string, mixed>
     */
    private function buildPayload(Prospect $prospect, array $analysisResult): array
    {
        return [
            'name' => $prospect->name,
            'primary_type' => $prospect->primary_type,
            'types' => $prospect->types_json,
            'keyword' => $prospect->keyword?->keyword,
            'keyword_category' => $prospect->keyword?->category_label,
            'address' => $prospect->formatted_address ?: $prospect->short_address,
            'city' => $prospect->city,
            'province' => $prospect->province,
            'website' => $prospect->website,
            'google_maps_url' => $prospect->google_maps_url,
            'heuristic_business_type' => data_get($analysisResult, 'business_type'),
            'heuristic_signals' => data_get($analysisResult, 'business_signals_json'),
            'emails' => data_get($analysisResult, 'emails_json'),
            'phones' => data_get($analysisResult, 'phones_json'),
            'linkedin_company_url' => data_get($analysisResult, 'linkedin_company_url'),
            'linkedin_people' => data_get($analysisResult, 'linkedin_people_json'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failed(string $model, string $message): array
    {
        return [
            'ai_status' => ProspectAnalysis::AI_STATUS_FAILED,
            'ai_provider' => 'openai',
            'ai_model' => $model,
            'ai_industry_label' => null,
            'ai_sub_industry' => null,
            'ai_business_output' => null,
            'ai_hotel_star' => null,
            'ai_confidence' => null,
            'ai_payload_json' => null,
            'ai_error_message' => Str::limit($message, 1000, ''),
        ];
    }

    private function sanitizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : Str::limit($value, 1000, '');
    }

    private function sanitizeHotelStar(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $star = (int) round((float) $value);
        if ($star < 1 || $star > 5) {
            return null;
        }

        return $star;
    }

    private function sanitizeConfidence(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        $confidence = (float) $value;
        if ($confidence < 0) {
            $confidence = 0;
        }
        if ($confidence > 100) {
            $confidence = 100;
        }

        return round($confidence, 2);
    }
}
