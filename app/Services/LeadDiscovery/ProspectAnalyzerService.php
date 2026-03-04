<?php

namespace App\Services\LeadDiscovery;

use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProspectAnalyzerService
{
    private const MAX_PAGES = 3;
    private const HTTP_TIMEOUT_SEC = 10;
    private const MAX_TEXT_LEN = 120000;

    /**
     * @return array<string, mixed>
     */
    public function analyze(Prospect $prospect): array
    {
        $prospect->loadMissing('keyword:id,keyword');

        $websiteUrl = $this->normalizeWebsiteUrl((string) ($prospect->website ?? ''));
        $websitePresent = $websiteUrl !== null;
        $websiteReachable = false;
        $websiteHttpStatus = null;
        $crawled = [];
        $fullText = '';
        $emails = [];
        $phones = [];
        $linkedinCompany = null;
        $linkedinPeople = [];

        if ($websiteUrl !== null && !$this->isBlockedForSsrf($websiteUrl)) {
            $pages = [$websiteUrl];
            $visited = [];
            $i = 0;

            while ($i < count($pages) && count($crawled) < self::MAX_PAGES) {
                $url = $pages[$i];
                $i++;
                if (isset($visited[$url])) {
                    continue;
                }
                $visited[$url] = true;

                [$statusCode, $body] = $this->fetchUrl($url);
                $crawled[] = [
                    'url' => $url,
                    'status' => $statusCode,
                ];

                if ($websiteHttpStatus === null && $statusCode !== null) {
                    $websiteHttpStatus = (int) $statusCode;
                }

                if ($statusCode !== null && $statusCode >= 200 && $statusCode < 400) {
                    $websiteReachable = true;
                }
                if (!$body) {
                    continue;
                }

                $emails = $this->mergeUnique($emails, $this->extractEmails($body));
                $phones = $this->mergeUnique($phones, $this->extractPhones($body));
                [$foundCompanyUrl, $foundPeople] = $this->extractLinkedinLinks($body, $url);
                if (!$linkedinCompany && $foundCompanyUrl) {
                    $linkedinCompany = $foundCompanyUrl;
                }
                $linkedinPeople = $this->mergeUnique($linkedinPeople, $foundPeople);

                $fullText .= ' ' . mb_substr($this->htmlToText($body), 0, self::MAX_TEXT_LEN);
                if (mb_strlen($fullText) > self::MAX_TEXT_LEN) {
                    $fullText = mb_substr($fullText, 0, self::MAX_TEXT_LEN);
                }

                $candidateLinks = $this->extractCandidateInternalLinks($body, $url);
                foreach ($candidateLinks as $candidate) {
                    if (count($pages) >= self::MAX_PAGES) {
                        break;
                    }
                    if (!isset($visited[$candidate]) && !in_array($candidate, $pages, true)) {
                        $pages[] = $candidate;
                    }
                }
            }
        }

        [$businessType, $businessSignals] = $this->classifyBusinessType($prospect, $fullText);
        $addressClarity = $this->detectAddressClarity($prospect);

        $checklist = [
            'website_present' => $websitePresent,
            'website_reachable' => $websiteReachable,
            'email_found' => count($emails) > 0,
            'linkedin_company_found' => $linkedinCompany !== null,
            'linkedin_people_found' => count($linkedinPeople) > 0,
            'business_type_identified' => $businessType !== 'unknown',
            'address_clear' => $addressClarity === ProspectAnalysis::ADDRESS_CLEAR,
        ];
        $score = 0;
        $score += $checklist['website_present'] ? 10 : 0;
        $score += $checklist['website_reachable'] ? 20 : 0;
        $score += $checklist['email_found'] ? 15 : 0;
        $score += $checklist['linkedin_company_found'] ? 10 : 0;
        $score += $checklist['linkedin_people_found'] ? 10 : 0;
        $score += $checklist['business_type_identified'] ? 20 : 0;
        if ($addressClarity === ProspectAnalysis::ADDRESS_CLEAR) {
            $score += 15;
        } elseif ($addressClarity === ProspectAnalysis::ADDRESS_PARTIAL) {
            $score += 8;
        }

        return [
            'website_url' => $websiteUrl,
            'website_http_status' => $websiteHttpStatus,
            'website_reachable' => $websiteReachable,
            'pages_crawled' => count($crawled),
            'crawled_urls_json' => $crawled,
            'emails_json' => array_values($emails),
            'phones_json' => array_values($phones),
            'linkedin_company_url' => $linkedinCompany,
            'linkedin_people_json' => array_values($linkedinPeople),
            'business_type' => $businessType,
            'business_signals_json' => $businessSignals,
            'address_clarity' => $addressClarity,
            'checklist_json' => $checklist,
            'score' => max(0, min(100, $score)),
        ];
    }

    private function normalizeWebsiteUrl(string $website): ?string
    {
        $website = trim($website);
        if ($website === '') {
            return null;
        }
        if (!Str::startsWith(Str::lower($website), ['http://', 'https://'])) {
            $website = 'https://' . ltrim($website, '/');
        }

        $parts = parse_url($website);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $normalized = $scheme . '://' . Str::lower($host);
        if (isset($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }
        $normalized .= $path;
        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    private function isBlockedForSsrf(string $url): bool
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        if ($host === '') {
            return true;
        }

        $blockedHosts = ['localhost', '127.0.0.1', '::1'];
        if (in_array(Str::lower($host), $blockedHosts, true)) {
            return true;
        }
        if (Str::endsWith(Str::lower($host), ['.local', '.internal', '.localhost'])) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPrivateOrReservedIp($host);
        }

        $ips = @gethostbynamel($host);
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if ($this->isPrivateOrReservedIp($ip)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function fetchUrl(string $url): array
    {
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SEC)
                ->retry(1, 300)
                ->withHeaders([
                    'User-Agent' => 'ICPOS-LeadDiscoveryAnalyzer/1.0',
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                ])
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (\Throwable $e) {
            return [null, null];
        }

        $body = null;
        $contentType = Str::lower((string) $response->header('Content-Type', ''));
        if (Str::contains($contentType, ['text/html', 'application/xhtml+xml']) || $contentType === '') {
            $body = (string) $response->body();
        }

        return [$response->status(), $body];
    }

    /**
     * @return array<int, string>
     */
    private function extractEmails(string $html): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $html, $matches);
        $emails = [];
        foreach (($matches[0] ?? []) as $email) {
            $email = Str::lower(trim((string) $email));
            if ($email !== '') {
                $emails[$email] = $email;
            }
        }

        return array_values($emails);
    }

    /**
     * @return array<int, string>
     */
    private function extractPhones(string $html): array
    {
        preg_match_all('/(?:\+?\d[\d\-\s\(\)]{7,}\d)/', strip_tags($html), $matches);
        $phones = [];
        foreach (($matches[0] ?? []) as $phone) {
            $normalized = preg_replace('/\s+/', ' ', trim((string) $phone));
            if ($normalized !== '') {
                $phones[$normalized] = $normalized;
            }
        }

        return array_values($phones);
    }

    /**
     * @return array{0: ?string, 1: array<int, string>}
     */
    private function extractLinkedinLinks(string $html, string $baseUrl): array
    {
        $links = $this->extractLinks($html, $baseUrl);
        $company = null;
        $people = [];

        foreach ($links as $link) {
            $lower = Str::lower($link);
            if (!Str::contains($lower, 'linkedin.com/')) {
                continue;
            }
            if (Str::contains($lower, 'linkedin.com/company/')) {
                if ($company === null) {
                    $company = $this->normalizeExternalUrl($link);
                }
                continue;
            }
            if (Str::contains($lower, ['linkedin.com/in/', 'linkedin.com/pub/'])) {
                $normalized = $this->normalizeExternalUrl($link);
                if ($normalized !== null) {
                    $people[$normalized] = $normalized;
                }
            }
        }

        return [$company, array_values($people)];
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidateInternalLinks(string $html, string $baseUrl): array
    {
        $baseHost = (string) (parse_url($baseUrl, PHP_URL_HOST) ?? '');
        if ($baseHost === '') {
            return [];
        }

        $keywords = ['/about', '/contact', '/company', '/profile', '/tentang', '/kontak'];
        $candidates = [];
        foreach ($this->extractLinks($html, $baseUrl) as $link) {
            $parts = parse_url($link);
            $host = (string) ($parts['host'] ?? '');
            $path = Str::lower((string) ($parts['path'] ?? '/'));
            if ($host === '' || Str::lower($host) !== Str::lower($baseHost)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (Str::contains($path, $keyword)) {
                    $normalized = $this->normalizeInternalUrl($link);
                    if ($normalized !== null) {
                        $candidates[$normalized] = $normalized;
                    }
                    break;
                }
            }
        }

        return array_values($candidates);
    }

    /**
     * @return array<int, string>
     */
    private function extractLinks(string $html, string $baseUrl): array
    {
        preg_match_all('/<a[^>]+href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);
        $out = [];
        foreach (($matches[1] ?? []) as $href) {
            $absolute = $this->resolveUrl((string) $href, $baseUrl);
            if ($absolute !== null) {
                $out[$absolute] = $absolute;
            }
        }

        return array_values($out);
    }

    private function resolveUrl(string $href, string $baseUrl): ?string
    {
        $href = html_entity_decode(trim($href));
        if ($href === '' || Str::startsWith(Str::lower($href), ['mailto:', 'tel:', 'javascript:', '#'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            $scheme = (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https');
            return $this->normalizeExternalUrl($scheme . ':' . $href);
        }

        if (Str::startsWith(Str::lower($href), ['http://', 'https://'])) {
            return $this->normalizeExternalUrl($href);
        }

        $baseParts = parse_url($baseUrl);
        $scheme = (string) ($baseParts['scheme'] ?? 'https');
        $host = (string) ($baseParts['host'] ?? '');
        if ($host === '') {
            return null;
        }
        $port = isset($baseParts['port']) ? ':' . (int) $baseParts['port'] : '';

        if (Str::startsWith($href, '/')) {
            return $this->normalizeExternalUrl($scheme . '://' . $host . $port . $href);
        }

        $basePath = (string) ($baseParts['path'] ?? '/');
        $baseDir = Str::endsWith($basePath, '/') ? $basePath : dirname($basePath) . '/';
        if ($baseDir === '\\' || $baseDir === '.') {
            $baseDir = '/';
        }

        return $this->normalizeExternalUrl($scheme . '://' . $host . $port . rtrim($baseDir, '/') . '/' . ltrim($href, '/'));
    }

    private function normalizeExternalUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        $normalized = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }
        $normalized .= $path;

        return rtrim($normalized, '/');
    }

    private function normalizeInternalUrl(string $url): ?string
    {
        return $this->normalizeExternalUrl($url);
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    /**
     * @param array<int, string> $base
     * @param array<int, string> $append
     * @return array<int, string>
     */
    private function mergeUnique(array $base, array $append): array
    {
        $merged = [];
        foreach (array_merge($base, $append) as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $merged[$value] = $value;
        }

        return array_values($merged);
    }

    /**
     * @return array{0: string, 1: array<string, array<int, string>>}
     */
    private function classifyBusinessType(Prospect $prospect, string $crawlText): array
    {
        $haystack = Str::lower(trim(implode(' ', [
            (string) $prospect->name,
            (string) $prospect->primary_type,
            (string) ($prospect->keyword?->keyword ?? ''),
            mb_substr($crawlText, 0, self::MAX_TEXT_LEN),
        ])));

        $map = [
            'food_processing' => ['food', 'beverage', 'bakery', 'dairy', 'makanan', 'minuman', 'flour', 'tepung', 'sugar', 'gula'],
            'textile_garment' => ['textile', 'garment', 'apparel', 'fabric', 'tekstil', 'kain'],
            'chemical' => ['chemical', 'kimia', 'fertilizer', 'pupuk', 'petrochemical'],
            'metal_engineering' => ['metal', 'steel', 'welding', 'machining', 'engineer', 'fabrication'],
            'warehouse_logistics' => ['warehouse', 'logistics', 'gudang', 'distribution', 'fulfillment'],
            'healthcare' => ['hospital', 'clinic', 'medical', 'health', 'klinik', 'rumah sakit'],
            'hospitality' => ['hotel', 'resort', 'villa', 'hospitality'],
            'retail_commercial' => ['retail', 'store', 'shop', 'mall', 'boutique', 'supermarket'],
            'general_manufacturing' => ['factory', 'manufacturing', 'industry', 'industri', 'pabrik', 'plant'],
        ];

        $scores = [];
        $signals = [];
        foreach ($map as $category => $tokens) {
            $scores[$category] = 0;
            foreach ($tokens as $token) {
                if (Str::contains($haystack, Str::lower($token))) {
                    $scores[$category]++;
                    $signals[$category][] = $token;
                }
            }
        }

        arsort($scores);
        $topCategory = array_key_first($scores);
        $topScore = $topCategory ? (int) ($scores[$topCategory] ?? 0) : 0;
        if (!$topCategory || $topScore === 0) {
            return ['unknown', []];
        }

        return [$topCategory, $signals];
    }

    private function detectAddressClarity(Prospect $prospect): string
    {
        $address = trim((string) ($prospect->formatted_address ?: $prospect->short_address ?: ''));
        $city = trim((string) ($prospect->city ?? ''));
        $province = trim((string) ($prospect->province ?? ''));

        $hasArea = $city !== '' || $province !== '';
        $hasStreetIndicator = (bool) preg_match('/\b(jl\.?|jalan|street|st\.?|road|rd\.?|no\.?|blok|block)\b/i', $address);

        if ($hasStreetIndicator && $hasArea) {
            return ProspectAnalysis::ADDRESS_CLEAR;
        }
        if ($address !== '' || $hasArea) {
            return ProspectAnalysis::ADDRESS_PARTIAL;
        }

        return ProspectAnalysis::ADDRESS_MISSING;
    }
}
