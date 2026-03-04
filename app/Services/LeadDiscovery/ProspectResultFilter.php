<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Str;

class ProspectResultFilter
{
    private const ALLOWED_TYPES = ['shopping_mall'];

    private const IGNORED_RETAIL_TYPES = [
        'store',
        'clothing_store',
        'shoe_store',
        'department_store',
        'electronics_store',
        'convenience_store',
        'supermarket',
        'home_goods_store',
        'furniture_store',
        'pharmacy',
    ];

    private const MALL_CONTEXT_TOKENS = [
        ' mall ',
        ' plaza ',
        ' galeria ',
        ' galleria ',
        ' shopping center ',
        ' shopping centre ',
        ' trade center ',
        ' trade centre ',
        ' square ',
    ];

    private const TENANT_SIGNAL_TOKENS = [
        ' level ',
        ' lt ',
        ' lantai ',
        ' unit ',
        ' kiosk ',
        ' tenant ',
        ' boutique ',
        ' store ',
        ' shop ',
        ' parking ',
        ' parkir ',
        ' restoran ',
        ' restaurant ',
        ' resto ',
        ' cafe ',
        ' sushi ',
        ' food court ',
    ];

    private const IGNORED_MALL_CONTEXT_TYPES = [
        'parking',
        'point_of_interest',
        'restaurant',
        'cafe',
        'meal_takeaway',
        'meal_delivery',
    ];

    public function shouldIgnore(array $place): bool
    {
        return $this->evaluate($place)['ignore'];
    }

    public function reason(array $place): string
    {
        return $this->evaluate($place)['reason'];
    }

    /**
     * @return array{ignore: bool, reason: string}
     */
    private function evaluate(array $place): array
    {
        $types = $this->extractTypes($place);
        $matchedRetailTypes = array_values(array_intersect($types, self::IGNORED_RETAIL_TYPES));
        $haystack = ' ' . Str::lower(trim(implode(' ', [
            (string) ($place['name'] ?? ''),
            (string) ($place['formatted_address'] ?? ''),
            (string) ($place['short_address'] ?? ''),
            (string) ($place['vicinity'] ?? ''),
        ]))) . ' ';

        $isMallContext = $this->containsAny($types, self::ALLOWED_TYPES)
            || $this->containsAnyToken($haystack, self::MALL_CONTEXT_TOKENS);
        $hasTenantSignal = $this->containsAnyToken($haystack, self::TENANT_SIGNAL_TOKENS);

        if ($isMallContext && $hasTenantSignal) {
            return ['ignore' => true, 'reason' => 'ignored_mall_tenant_signal'];
        }

        $matchedMallContextTypes = array_values(array_intersect($types, self::IGNORED_MALL_CONTEXT_TYPES));
        if ($isMallContext && $matchedMallContextTypes !== []) {
            return ['ignore' => true, 'reason' => 'ignored_mall_facility_type:' . implode(',', $matchedMallContextTypes)];
        }

        if ($this->containsAny($types, self::ALLOWED_TYPES)) {
            return ['ignore' => false, 'reason' => 'allowed_shopping_mall'];
        }

        if ($matchedRetailTypes !== []) {
            return ['ignore' => true, 'reason' => 'ignored_retail_type:' . implode(',', $matchedRetailTypes)];
        }

        return ['ignore' => false, 'reason' => 'allowed_default'];
    }

    /**
     * @return list<string>
     */
    private function extractTypes(array $place): array
    {
        $types = $place['types'] ?? [];
        if (!is_array($types)) {
            $types = [];
        }

        $primaryType = (string) ($place['primary_type'] ?? '');
        if ($primaryType !== '') {
            $types[] = $primaryType;
        }

        $firstType = (string) ($types[0] ?? '');
        if ($firstType !== '') {
            $types[] = $firstType;
        }

        $normalized = [];
        foreach ($types as $type) {
            if (!is_string($type)) {
                continue;
            }
            $type = trim(Str::lower($type));
            if ($type === '') {
                continue;
            }
            $normalized[$type] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param list<string> $types
     * @param list<string> $needles
     */
    private function containsAny(array $types, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private function containsAnyToken(string $haystack, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (Str::contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }
}
