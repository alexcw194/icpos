<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use App\Services\LeadDiscovery\LeadDiscoveryAiClassifierService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadDiscoveryAiClassifierServiceTest extends TestCase
{
    public function test_heuristic_only_mode_skips_openai_and_returns_success(): void
    {
        Config::set('services.lead_discovery.analysis_mode', 'heuristic_only');
        Config::set('services.openai.key', 'test-key');

        Http::fake();

        $prospect = new Prospect([
            'name' => 'PT Mayatama Manunggal Sentosa',
            'primary_type' => 'point_of_interest',
            'raw_json' => [
                'description' => 'Perusahaan bergerak di bidang manufaktur kaca tempered glass dengan 51-200 karyawan.',
            ],
        ]);

        $service = new LeadDiscoveryAiClassifierService();
        $result = $service->classify($prospect, [
            'business_type' => 'general_manufacturing',
            'business_signals_json' => ['general_manufacturing' => ['manufacturing']],
        ]);

        $this->assertSame(ProspectAnalysis::AI_STATUS_SUCCESS, $result['ai_status']);
        $this->assertSame('heuristic', $result['ai_provider']);
        $this->assertSame('Safety Glass / Tempered Glass', $result['ai_sub_industry']);
        $this->assertSame('51-200', $result['ai_employee_range']);
        Http::assertNothingSent();
    }

    public function test_it_overrides_generic_manufacturing_with_tempered_glass_sub_industry(): void
    {
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-nano');
        Config::set('services.lead_discovery.analysis_mode', 'openai');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'industry_label' => 'Manufacturing',
                            'sub_industry' => 'General manufacturing',
                            'business_output' => null,
                            'employee_range' => null,
                            'hotel_star' => null,
                            'confidence' => 88,
                            'reasoning' => 'generic',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $prospect = new Prospect([
            'name' => 'PT Mayatama Manunggal Sentosa',
            'primary_type' => 'point_of_interest',
            'raw_json' => [
                'description' => 'Perusahaan bergerak di bidang manufaktur kaca pengaman termasuk tempered glass dan laminated glass. Jumlah karyawan 51 - 200 karyawan.',
            ],
        ]);

        $service = new LeadDiscoveryAiClassifierService();
        $result = $service->classify($prospect, [
            'business_type' => 'general_manufacturing',
            'business_signals_json' => ['general_manufacturing' => ['manufacturing']],
        ]);

        $this->assertSame(ProspectAnalysis::AI_STATUS_SUCCESS, $result['ai_status']);
        $this->assertSame('Manufacturing', $result['ai_industry_label']);
        $this->assertSame('Safety Glass / Tempered Glass', $result['ai_sub_industry']);
        $this->assertSame('51-200', $result['ai_employee_range']);
        $this->assertStringContainsStringIgnoringCase('tempered glass', (string) $result['ai_business_output']);
    }

    public function test_it_overrides_generic_manufacturing_with_plastic_sub_industry(): void
    {
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-nano');
        Config::set('services.lead_discovery.analysis_mode', 'openai');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'industry_label' => 'Manufacturing',
                            'sub_industry' => 'General manufacturing',
                            'business_output' => null,
                            'employee_range' => null,
                            'hotel_star' => null,
                            'confidence' => 77,
                            'reasoning' => 'generic',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $prospect = new Prospect([
            'name' => 'PT Maju Plastik Nusantara',
            'primary_type' => 'point_of_interest',
            'raw_json' => [
                'description' => 'Perusahaan manufaktur plastik dengan proses injection molding untuk komponen industri.',
            ],
        ]);

        $service = new LeadDiscoveryAiClassifierService();
        $result = $service->classify($prospect, [
            'business_type' => 'general_manufacturing',
            'business_signals_json' => ['general_manufacturing' => ['manufacturing']],
        ]);

        $this->assertSame(ProspectAnalysis::AI_STATUS_SUCCESS, $result['ai_status']);
        $this->assertSame('Manufacturing', $result['ai_industry_label']);
        $this->assertSame('Plastic Manufacturing', $result['ai_sub_industry']);
        $this->assertStringContainsStringIgnoringCase('plastic', (string) $result['ai_business_output']);
    }
}
