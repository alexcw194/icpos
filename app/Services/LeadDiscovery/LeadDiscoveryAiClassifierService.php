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
        $mode = Str::lower(trim((string) config('services.lead_discovery.analysis_mode', 'heuristic_only')));
        if ($mode === '' || $mode === 'heuristic_only') {
            return $this->heuristicOnlyResult($prospect, $analysisResult);
        }

        if ($mode !== 'openai') {
            return $this->heuristicOnlyResult($prospect, $analysisResult);
        }

        $apiKey = trim((string) config('services.openai.key', ''));
        $model = trim((string) config('services.openai.model', 'gpt-4o-mini'));
        if ($apiKey === '') {
            return $this->heuristicOnlyResult($prospect, $analysisResult, 'openai key kosong, fallback ke heuristic');
        }

        $payload = $this->buildPayload($prospect, $analysisResult);
        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You classify Indonesian business leads. Return JSON only.',
                    'Always prefer the most specific sub-industry possible.',
                    'Never return generic sub-industry like "General manufacturing" when product/material clues exist.',
                    'If clues indicate a specific sector (glass/plastic/textile/chemical/metal/food/etc), output that specific sub-industry.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => implode("\n", [
                    'Classify this lead and return JSON with keys:',
                    'industry_label (string|null)',
                    'sub_industry (string|null)',
                    'business_output (string|null)',
                    'employee_range (string|null, format like "51-200")',
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
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (\Throwable $e) {
            return $this->failed($model, 'OpenAI request failed: ' . $e->getMessage());
        }

        if (!$response->ok()) {
            $apiMessage = (string) data_get($response->json(), 'error.message', '');
            return $this->failed($model, $this->buildHttpErrorMessage($response->status(), $apiMessage));
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
        $employeeRange = $this->sanitizeEmployeeRange(data_get($decoded, 'employee_range'));
        $hotelStar = $this->sanitizeHotelStar(data_get($decoded, 'hotel_star'));
        $confidence = $this->sanitizeConfidence(data_get($decoded, 'confidence'));
        [$industry, $subIndustry, $businessOutput] = $this->enforceSpecificSubIndustry(
            $industry,
            $subIndustry,
            $businessOutput,
            $payload
        );
        if ($employeeRange === null) {
            $employeeRange = $this->inferEmployeeRangeFromPayload($payload);
        }

        return [
            'ai_status' => ProspectAnalysis::AI_STATUS_SUCCESS,
            'ai_provider' => 'openai',
            'ai_model' => $model,
            'ai_industry_label' => $industry,
            'ai_sub_industry' => $subIndustry,
            'ai_business_output' => $businessOutput,
            'ai_employee_range' => $employeeRange,
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
            'editorial_summary' => $this->sanitizeNullableString((string) data_get($prospect->raw_json, 'editorial_summary.overview')),
            'description' => $this->sanitizeNullableString(
                (string) (data_get($prospect->raw_json, 'description')
                    ?: data_get($prospect->raw_json, 'business_status')
                    ?: '')
            ),
            'raw_text_excerpt' => $this->flattenRawJsonToText($prospect->raw_json),
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
     * @param array<string, mixed> $analysisResult
     * @return array<string, mixed>
     */
    private function heuristicOnlyResult(Prospect $prospect, array $analysisResult, ?string $note = null): array
    {
        $payload = $this->buildPayload($prospect, $analysisResult);
        $baseIndustry = $this->mapBusinessTypeToIndustry(
            (string) ($analysisResult['business_type'] ?? 'unknown')
        );

        [$industry, $subIndustry, $businessOutput] = $this->enforceSpecificSubIndustry(
            $baseIndustry,
            null,
            null,
            $payload
        );

        $employeeRange = $this->inferEmployeeRangeFromPayload($payload);
        $confidence = $subIndustry !== null ? 72.0 : 60.0;

        return [
            'ai_status' => ProspectAnalysis::AI_STATUS_SUCCESS,
            'ai_provider' => 'heuristic',
            'ai_model' => 'local-rules',
            'ai_industry_label' => $industry,
            'ai_sub_industry' => $subIndustry,
            'ai_business_output' => $businessOutput,
            'ai_employee_range' => $employeeRange,
            'ai_hotel_star' => null,
            'ai_confidence' => $confidence,
            'ai_payload_json' => [
                'mode' => 'heuristic_only',
                'source_business_type' => (string) ($analysisResult['business_type'] ?? 'unknown'),
                'note' => $note,
            ],
            'ai_error_message' => null,
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
            'ai_employee_range' => null,
            'ai_hotel_star' => null,
            'ai_confidence' => null,
            'ai_payload_json' => null,
            'ai_error_message' => Str::limit($message, 1000, ''),
        ];
    }

    private function buildHttpErrorMessage(int $status, string $apiMessage = ''): string
    {
        $apiMessage = trim($apiMessage);

        $base = match ($status) {
            401 => 'OpenAI API key tidak valid (401).',
            403 => 'Akses model OpenAI ditolak (403).',
            429 => 'Rate limit/quota OpenAI tercapai (429). Coba ulang beberapa saat lagi.',
            default => "OpenAI HTTP {$status}.",
        };

        if ($apiMessage === '') {
            return $base;
        }

        return $base . ' ' . $apiMessage;
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

    private function sanitizeEmployeeRange(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/(\d{1,4})\s*[-\x{2013}]\s*(\d{1,4})/u', $text, $matches)) {
            $from = (int) $matches[1];
            $to = (int) $matches[2];
            if ($from > 0 && $to >= $from) {
                return "{$from}-{$to}";
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function enforceSpecificSubIndustry(
        ?string $industry,
        ?string $subIndustry,
        ?string $businessOutput,
        array $payload
    ): array {
        $combined = Str::lower(trim(implode(' ', array_filter([
            (string) ($payload['name'] ?? ''),
            (string) ($payload['primary_type'] ?? ''),
            (string) ($payload['keyword'] ?? ''),
            (string) ($payload['keyword_category'] ?? ''),
            (string) ($payload['editorial_summary'] ?? ''),
            (string) ($payload['description'] ?? ''),
            (string) ($payload['raw_text_excerpt'] ?? ''),
            is_array($payload['heuristic_signals'] ?? null) ? json_encode($payload['heuristic_signals']) : '',
        ]))));

        $detected = $this->detectSpecificSubIndustry($combined);
        if ($detected === null) {
            return [$industry, $subIndustry, $businessOutput];
        }

        if ($this->isGenericSubIndustry($subIndustry)) {
            $industry = $detected['industry'];
            $subIndustry = $detected['sub_industry'];
            if ($businessOutput === null || trim($businessOutput) === '') {
                $businessOutput = $detected['business_output'];
            }
        }

        return [$industry, $subIndustry, $businessOutput];
    }

    private function isGenericSubIndustry(?string $subIndustry): bool
    {
        if ($subIndustry === null) {
            return true;
        }

        return in_array(Str::lower(trim($subIndustry)), [
            '',
            'general manufacturing',
            'manufacturing',
            'general_manufacturing',
            'unknown',
            'other',
            'others',
        ], true);
    }

    /**
     * @return array{industry: string, sub_industry: string, business_output: string}|null
     */
    private function detectSpecificSubIndustry(string $combined): ?array
    {
        $hasManufacturingSignal = Str::contains($combined, [
            'manufactur',
            'manufaktur',
            'pabrik',
            'factory',
            'plant',
            'industri',
            'producer',
            'produsen',
        ]);

        $hasGlassSignal = Str::contains($combined, ['glass', 'kaca']);
        $hasSafetyGlassSignal = Str::contains($combined, [
            'tempered glass',
            'laminated glass',
            'safety glass',
            'kaca pengaman',
            'kaca tempered',
            'kaca laminasi',
        ]);
        if ($hasSafetyGlassSignal) {
            return [
                'industry' => 'Manufacturing',
                'sub_industry' => 'Safety Glass / Tempered Glass',
                'business_output' => 'Tempered glass, laminated glass, and safety glass for industrial/building/automotive use.',
            ];
        }
        if ($hasGlassSignal && $hasManufacturingSignal) {
            return [
                'industry' => 'Manufacturing',
                'sub_industry' => 'Glass Manufacturing',
                'business_output' => 'Glass-based products for industrial/commercial/building applications.',
            ];
        }

        $rules = [
            [
                'tokens' => ['pabrik plastik', 'industri plastik', 'plastic manufacturing', 'plastic factory', 'injection molding', 'blow molding', 'polymer', 'plastik'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Plastic Manufacturing',
                'business_output' => 'Plastic products/components such as packaging, molded parts, or polymer-based materials.',
            ],
            [
                'tokens' => ['textile', 'tekstil', 'garment', 'garmen', 'apparel', 'kain', 'fabric'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Textile & Garment Manufacturing',
                'business_output' => 'Textile fabrics, garments, or apparel products.',
            ],
            [
                'tokens' => ['chemical', 'kimia', 'petrochemical', 'resin', 'adhesive', 'coating'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Chemical Manufacturing',
                'business_output' => 'Chemical-based materials such as resin, adhesives, or industrial compounds.',
            ],
            [
                'tokens' => ['metal fabrication', 'steel fabrication', 'machining', 'welding', 'foundry', 'metal works'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Metal Fabrication & Engineering',
                'business_output' => 'Metal fabricated parts, engineered components, or machined products.',
            ],
            [
                'tokens' => ['food processing', 'makanan', 'beverage', 'minuman', 'bakery', 'dairy', 'snack factory'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Food & Beverage Processing',
                'business_output' => 'Processed food or beverage products for consumer/industrial distribution.',
            ],
            [
                'tokens' => ['paper packaging', 'corrugated', 'carton box', 'pabrik karton', 'packaging'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Paper & Packaging Manufacturing',
                'business_output' => 'Paper-based packaging products such as corrugated boxes/cartons.',
            ],
            [
                'tokens' => ['furniture', 'woodworking', 'mebel', 'furnitur', 'plywood'],
                'requires_manufacturing' => true,
                'industry' => 'Manufacturing',
                'sub_industry' => 'Furniture & Wood Products',
                'business_output' => 'Furniture and wood-based products for residential/commercial use.',
            ],
        ];

        foreach ($rules as $rule) {
            $matched = Str::contains($combined, $rule['tokens']);
            if (!$matched) {
                continue;
            }
            if (($rule['requires_manufacturing'] ?? false) && !$hasManufacturingSignal) {
                continue;
            }

            return [
                'industry' => (string) $rule['industry'],
                'sub_industry' => (string) $rule['sub_industry'],
                'business_output' => (string) $rule['business_output'],
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function inferEmployeeRangeFromPayload(array $payload): ?string
    {
        $combined = implode(' ', array_filter([
            (string) ($payload['editorial_summary'] ?? ''),
            (string) ($payload['description'] ?? ''),
            (string) ($payload['raw_text_excerpt'] ?? ''),
        ]));

        if (preg_match('/(\d{1,4})\s*[-\x{2013}]\s*(\d{1,4})\s*(karyawan|pegawai|employees)?/iu', $combined, $matches)) {
            $from = (int) $matches[1];
            $to = (int) $matches[2];
            if ($from > 0 && $to >= $from) {
                return "{$from}-{$to}";
            }
        }

        return null;
    }

    private function flattenRawJsonToText(mixed $raw, int $limit = 6000): ?string
    {
        if (!is_array($raw)) {
            return null;
        }

        $chunks = [];
        $walker = function (mixed $value) use (&$walker, &$chunks): void {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    $walker($nested);
                }
                return;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $chunks[] = $trimmed;
                }
            }
        };

        $walker($raw);
        if (empty($chunks)) {
            return null;
        }

        return Str::limit(implode(' | ', $chunks), $limit, '');
    }

    private function mapBusinessTypeToIndustry(string $businessType): ?string
    {
        return match (trim($businessType)) {
            'food_processing',
            'textile_garment',
            'chemical',
            'metal_engineering',
            'general_manufacturing' => 'Manufacturing',
            'warehouse_logistics' => 'Logistics',
            'healthcare' => 'Healthcare',
            'hospitality' => 'Hospitality',
            'retail_commercial' => 'Retail',
            default => null,
        };
    }
}
