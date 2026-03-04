<?php

namespace App\Services\LeadDiscovery;

use App\Models\Prospect;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ProspectNormalizer
{
    public function normalizePlaceToProspectPayload(array $place, ?int $keywordId, ?int $gridCellId): array
    {
        $addressComponents = Arr::get($place, 'address_components', []);
        $formattedAddress = (string) (Arr::get($place, 'formatted_address') ?: '');
        $shortAddress = (string) (Arr::get($place, 'vicinity') ?: '');
        $city = $this->extractAddressPart($addressComponents, ['administrative_area_level_2', 'locality'])
            ?: $this->guessCityFromAddress($formattedAddress);
        $province = $this->extractAddressPart($addressComponents, ['administrative_area_level_1'])
            ?: $this->guessProvinceFromAddress($formattedAddress);

        $lat = Arr::get($place, 'geometry.location.lat');
        $lng = Arr::get($place, 'geometry.location.lng');
        $now = Carbon::now();

        return [
            'source' => Prospect::SOURCE_GOOGLE_PLACES,
            'place_id' => (string) Arr::get($place, 'place_id'),
            'name' => (string) Arr::get($place, 'name', ''),
            'formatted_address' => $formattedAddress ?: null,
            'short_address' => $shortAddress ?: null,
            'city' => $city ?: null,
            'province' => $province ?: null,
            'country' => 'Indonesia',
            'lat' => is_numeric($lat) ? (float) $lat : null,
            'lng' => is_numeric($lng) ? (float) $lng : null,
            'phone' => Arr::get($place, 'international_phone_number') ?: Arr::get($place, 'formatted_phone_number'),
            'website' => Arr::get($place, 'website'),
            'google_maps_url' => Arr::get($place, 'url'),
            'primary_type' => (string) (Arr::get($place, 'types.0') ?: ''),
            'types_json' => Arr::get($place, 'types', []),
            'keyword_id' => $keywordId,
            'grid_cell_id' => $gridCellId,
            'discovered_at' => $now,
            'last_seen_at' => $now,
            'raw_json' => $place,
        ];
    }

    private function extractAddressPart(array $components, array $candidateTypes): ?string
    {
        foreach ($components as $component) {
            $types = Arr::get($component, 'types', []);
            if (!is_array($types)) {
                continue;
            }
            foreach ($candidateTypes as $type) {
                if (in_array($type, $types, true)) {
                    return (string) (Arr::get($component, 'long_name') ?: Arr::get($component, 'short_name') ?: '');
                }
            }
        }
        return null;
    }

    private function guessCityFromAddress(string $formattedAddress): ?string
    {
        if ($formattedAddress === '') {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $formattedAddress))));
        return $parts[count($parts) - 3] ?? $parts[count($parts) - 2] ?? null;
    }

    private function guessProvinceFromAddress(string $formattedAddress): ?string
    {
        if ($formattedAddress === '') {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $formattedAddress))));
        return $parts[count($parts) - 2] ?? null;
    }
}

