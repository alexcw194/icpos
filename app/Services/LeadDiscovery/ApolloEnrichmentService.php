<?php

namespace App\Services\LeadDiscovery;

use App\Models\Prospect;
use App\Models\ProspectApolloEnrichment;
use Illuminate\Support\Str;

class ApolloEnrichmentService
{
    public function __construct(
        private readonly ApolloClient $apolloClient
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function enrich(Prospect $prospect): array
    {
        $seedWebsite = $this->normalizeWebsite((string) ($prospect->website ?? ''));
        $seedDomain = $this->extractDomain($seedWebsite);
        $matchedBy = ProspectApolloEnrichment::MATCHED_BY_NONE;

        $organization = null;
        $enrichPayload = [
            'domain' => $seedDomain,
        ];

        if ($seedDomain !== null) {
            $enrichResult = $this->apolloClient->organizationEnrich($enrichPayload);
            $organization = $this->pickOrganization($enrichResult);
            if ($organization !== null) {
                $matchedBy = ProspectApolloEnrichment::MATCHED_BY_DOMAIN;
            }
        } else {
            $enrichResult = [];
        }

        $searchResult = [];
        if ($organization === null) {
            $locationTokens = array_values(array_filter([
                $prospect->city,
                $prospect->province,
                $prospect->country,
            ], function ($value) {
                return $value !== null && $value !== '';
            }));

            $searchPayload = array_filter([
                'q_organization_name' => $prospect->name,
                'organization_locations' => $locationTokens,
                'page' => 1,
                'per_page' => 5,
            ], function ($value) {
                return $value !== null && $value !== '' && $value !== [];
            });

            $searchResult = $this->apolloClient->organizationSearch($searchPayload);
            $organization = $this->pickOrganization($searchResult);
            if ($organization !== null) {
                $matchedBy = ProspectApolloEnrichment::MATCHED_BY_NAME_LOCATION;
            }
        }

        $orgId = (string) ($organization['id'] ?? $organization['organization_id'] ?? '');
        $peopleResult = [];
        $people = [];
        if ($orgId !== '') {
            $peopleResult = $this->apolloClient->organizationTopPeople([
                'organization_id' => $orgId,
                'page' => 1,
                'per_page' => 5,
            ]);
            $people = $this->normalizePeople($peopleResult);
        }

        $normalized = $this->normalizeOrganization($organization);

        return [
            'seed_website' => $seedWebsite,
            'seed_domain' => $seedDomain,
            'matched_by' => $matchedBy,
            'apollo_org_id' => $normalized['org_id'],
            'apollo_org_name' => $normalized['org_name'],
            'apollo_domain' => $normalized['domain'],
            'apollo_website_url' => $normalized['website_url'],
            'apollo_linkedin_url' => $normalized['linkedin_url'],
            'apollo_industry' => $normalized['industry'],
            'apollo_sub_industry' => $normalized['sub_industry'],
            'apollo_business_output' => $normalized['business_output'],
            'apollo_employee_range' => $normalized['employee_range'],
            'apollo_city' => $normalized['city'],
            'apollo_state' => $normalized['state'],
            'apollo_country' => $normalized['country'],
            'apollo_people_json' => $people,
            'apollo_payload_json' => [
                'organization_enrich' => $enrichResult,
                'organization_search' => $searchResult,
                'top_people' => $peopleResult,
            ],
        ];
    }

    public function mergeProspectFillEmpty(Prospect $prospect, ProspectApolloEnrichment $enrichment): void
    {
        $changed = false;

        if ($this->isBlank($prospect->website) && !$this->isBlank($enrichment->apollo_website_url)) {
            $prospect->website = $this->normalizeWebsite((string) $enrichment->apollo_website_url);
            $changed = true;
        }
        if ($this->isBlank($prospect->city) && !$this->isBlank($enrichment->apollo_city)) {
            $prospect->city = trim((string) $enrichment->apollo_city);
            $changed = true;
        }
        if ($this->isBlank($prospect->province) && !$this->isBlank($enrichment->apollo_state)) {
            $prospect->province = trim((string) $enrichment->apollo_state);
            $changed = true;
        }
        if ($this->isBlank($prospect->country) && !$this->isBlank($enrichment->apollo_country)) {
            $prospect->country = trim((string) $enrichment->apollo_country);
            $changed = true;
        }

        if ($changed) {
            $prospect->save();
        }
    }

    /**
     * @param array<string, mixed>|null $organization
     * @return array{
     *   org_id: ?string,
     *   org_name: ?string,
     *   domain: ?string,
     *   website_url: ?string,
     *   linkedin_url: ?string,
     *   industry: ?string,
     *   sub_industry: ?string,
     *   business_output: ?string,
     *   employee_range: ?string,
     *   city: ?string,
     *   state: ?string,
     *   country: ?string
     * }
     */
    private function normalizeOrganization(?array $organization): array
    {
        if (!is_array($organization)) {
            return [
                'org_id' => null,
                'org_name' => null,
                'domain' => null,
                'website_url' => null,
                'linkedin_url' => null,
                'industry' => null,
                'sub_industry' => null,
                'business_output' => null,
                'employee_range' => null,
                'city' => null,
                'state' => null,
                'country' => null,
            ];
        }

        $employees = $this->normalizeEmployeeRange(
            data_get($organization, 'estimated_num_employees')
                ?? data_get($organization, 'employee_count')
                ?? data_get($organization, 'employees_count')
                ?? data_get($organization, 'employee_range')
        );

        $businessOutput = $this->normalizeText(
            (string) (data_get($organization, 'short_description')
                ?? data_get($organization, 'description')
                ?? '')
        );

        return [
            'org_id' => $this->normalizeText((string) (data_get($organization, 'id') ?? data_get($organization, 'organization_id') ?? '')),
            'org_name' => $this->normalizeText((string) (data_get($organization, 'name') ?? '')),
            'domain' => $this->normalizeText((string) (data_get($organization, 'primary_domain') ?? data_get($organization, 'domain') ?? '')),
            'website_url' => $this->normalizeWebsite((string) (data_get($organization, 'website_url') ?? data_get($organization, 'website') ?? '')),
            'linkedin_url' => $this->normalizeText((string) (data_get($organization, 'linkedin_url') ?? data_get($organization, 'linkedin') ?? '')),
            'industry' => $this->normalizeText((string) (data_get($organization, 'industry') ?? '')),
            'sub_industry' => $this->normalizeText((string) (data_get($organization, 'sub_industry') ?? data_get($organization, 'industry') ?? '')),
            'business_output' => $businessOutput,
            'employee_range' => $employees,
            'city' => $this->normalizeText((string) (data_get($organization, 'city') ?? '')),
            'state' => $this->normalizeText((string) (data_get($organization, 'state') ?? data_get($organization, 'province') ?? '')),
            'country' => $this->normalizeText((string) (data_get($organization, 'country') ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    private function pickOrganization(array $result): ?array
    {
        $candidates = [
            data_get($result, 'organization'),
            data_get($result, 'account'),
            data_get($result, 'data.organization'),
            data_get($result, 'data.account'),
            data_get($result, 'organizations.0'),
            data_get($result, 'accounts.0'),
            data_get($result, 'data.organizations.0'),
            data_get($result, 'data.accounts.0'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && !empty($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<int, array<string, string|null>>
     */
    private function normalizePeople(array $result): array
    {
        $list = data_get($result, 'people');
        if (!is_array($list)) {
            $list = data_get($result, 'data.people');
        }
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $person) {
            if (!is_array($person)) {
                continue;
            }

            $name = $this->normalizeText((string) (data_get($person, 'name') ?? data_get($person, 'full_name') ?? ''));
            $title = $this->normalizeText((string) (data_get($person, 'title') ?? data_get($person, 'headline') ?? ''));
            $linkedin = $this->normalizeText((string) (data_get($person, 'linkedin_url') ?? ''));
            $email = $this->normalizeText((string) (data_get($person, 'email') ?? ''));
            $phone = $this->normalizeText((string) (data_get($person, 'phone') ?? data_get($person, 'phone_number') ?? ''));

            if ($name === null && $title === null && $linkedin === null) {
                continue;
            }

            $out[] = [
                'name' => $name,
                'title' => $title,
                'linkedin_url' => $linkedin,
                'email' => $email,
                'phone' => $phone,
            ];
        }

        return array_slice($out, 0, 5);
    }

    private function normalizeWebsite(string $website): ?string
    {
        $website = trim($website);
        if ($website === '') {
            return null;
        }
        if (!Str::startsWith(Str::lower($website), ['http://', 'https://'])) {
            $website = 'https://' . ltrim($website, '/');
        }

        $parts = parse_url($website);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        $normalized = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }
        $normalized .= $path;
        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }

        return rtrim($normalized, '/');
    }

    private function extractDomain(?string $website): ?string
    {
        if ($website === null) {
            return null;
        }
        $host = (string) (parse_url($website, PHP_URL_HOST) ?? '');
        $host = Str::lower(trim($host));

        return $host === '' ? null : $host;
    }

    private function normalizeText(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return Str::limit($value, 2000, '');
    }

    private function normalizeEmployeeRange(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (int) $value;
            if ($number <= 0) {
                return null;
            }
            if ($number <= 10) return '1-10';
            if ($number <= 50) return '11-50';
            if ($number <= 200) return '51-200';
            if ($number <= 500) return '201-500';
            if ($number <= 1000) return '501-1000';
            return '1001+';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/(\d{1,5})\s*[-\x{2013}]\s*(\d{1,5})/u', $text, $matches)) {
            $from = (int) $matches[1];
            $to = (int) $matches[2];
            if ($from > 0 && $to >= $from) {
                return "{$from}-{$to}";
            }
        }

        return Str::limit($text, 40, '');
    }

    private function isBlank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }
}
