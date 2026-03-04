<?php

namespace App\Services\LeadDiscovery;

use Illuminate\Support\Str;

class ProspectResultFilter
{
    private const ALLOWED_TYPES = [
        'shopping_mall',
    ];

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

    private const TENANT_TOKENS = [
        ' level ',
        ' lt ',
        ' unit ',
        ' kiosk ',
        ' mall ',
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
        if ($this->containsAny($types, self::ALLOWED_TYPES)) {
            return ['ignore' => false, 'reason' => 'allowed_shopping_mall'];
        }

        $matchedRetailTypes = array_values(array_intersect($types, self::IGNORED_RETAIL_TYPES));
        if ($matchedRetailTypes === []) {
            return ['ignore' => false, 'reason' => 'allowed_default'];
        }

        $haystack = ' ' . Str::lower(trim(implode(' ', [
            (string) ($place['name'] ?? ''),
            (string) ($place['formatted_address'] ?? ''),
            (string) ($place['short_address'] ?? ''),
            (string) ($place['vicinity'] ?? ''),
        ]))) . ' ';

        foreach (self::TENANT_TOKENS as $token) {
            if (Str::contains($haystack, $token)) {
                return [
                    'ignore' => true,
                    'reason' => 'ignored_retail_tenant:' . implode(',', $matchedRetailTypes),
                ];
            }
        }

        return [
            'ignore' => true,
            'reason' => 'ignored_retail_type:' . implode(',', $matchedRetailTypes),
        ];
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
}
