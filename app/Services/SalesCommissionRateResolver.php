<?php

namespace App\Services;

use App\Models\SalesCommissionRule;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class SalesCommissionRateResolver
{
    private ?array $brandRules = null;
    private ?array $familyRules = null;

    public function resolve(object $row): array
    {
        $projectOverride = $this->resolveProjectOverride($row);
        if ($projectOverride !== null) {
            return $projectOverride;
        }

        $brandId = (int) ($row->brand_id ?? 0);
        if ($brandId > 0 && array_key_exists($brandId, $this->brandRules())) {
            $rate = (float) $this->brandRules()[$brandId]->rate_percent;

            return [
                'rate_percent' => $rate,
                'project_scope' => null,
                'rate_source' => 'brand',
                'rate_label' => 'Brand: '.($row->brand_name ?: ('#'.$brandId)),
                'is_unresolved' => false,
            ];
        }

        if (strtolower(trim((string) ($row->brand_name ?? ''))) === 'rosenbauer') {
            return [
                'rate_percent' => 3.0,
                'project_scope' => null,
                'rate_source' => 'brand',
                'rate_label' => 'Brand: Rosenbauer',
                'is_unresolved' => false,
            ];
        }

        $familyCode = strtoupper(trim((string) ($row->family_code ?? '')));
        if ($familyCode !== '' && array_key_exists($familyCode, $this->familyRules())) {
            $rate = (float) $this->familyRules()[$familyCode]->rate_percent;

            return [
                'rate_percent' => $rate,
                'project_scope' => null,
                'rate_source' => 'family',
                'rate_label' => 'Family: '.$familyCode,
                'is_unresolved' => false,
            ];
        }

        return [
            'rate_percent' => $this->defaultRate(),
            'project_scope' => null,
            'rate_source' => 'default',
            'rate_label' => 'Global default',
            'is_unresolved' => false,
        ];
    }

    private function resolveProjectOverride(object $row): ?array
    {
        $poType = strtolower(trim((string) ($row->po_type ?? 'goods')));
        $familyCode = strtoupper(trim((string) ($row->family_code ?? '')));
        $systems = collect($row->project_systems ?? [])
            ->map(fn ($system) => strtolower(trim((string) $system)))
            ->filter()
            ->values();

        if ($poType === 'maintenance') {
            return [
                'rate_percent' => $this->projectRate('maintenance', 5.0),
                'project_scope' => 'maintenance',
                'rate_source' => 'project_override',
                'rate_label' => 'Project: Maintenance',
                'is_unresolved' => false,
            ];
        }

        if ($poType !== 'project') {
            return null;
        }

        if ($systems->isEmpty()) {
            return null;
        }

        if ($familyCode === 'HYDRANT') {
            if ($systems->contains('fire_hydrant')) {
                return [
                    'rate_percent' => $this->projectRate('fire_hydrant', 1.5),
                    'project_scope' => 'fire_hydrant',
                    'rate_source' => 'project_override',
                    'rate_label' => 'Project: Fire Hydrant',
                    'is_unresolved' => false,
                ];
            }

            return [
                'rate_percent' => $this->defaultRate(),
                'project_scope' => null,
                'rate_source' => 'default',
                'rate_label' => 'Fallback global default (project unresolved)',
                'is_unresolved' => true,
            ];
        }

        if ($systems->contains('fire_alarm')) {
            return [
                'rate_percent' => $this->projectRate('fire_alarm', 5.0),
                'project_scope' => 'fire_alarm',
                'rate_source' => 'project_override',
                'rate_label' => 'Project: Fire Alarm',
                'is_unresolved' => false,
            ];
        }

        if ($systems->contains('maintenance')) {
            return [
                'rate_percent' => $this->projectRate('maintenance', 5.0),
                'project_scope' => 'maintenance',
                'rate_source' => 'project_override',
                'rate_label' => 'Project: Maintenance',
                'is_unresolved' => false,
            ];
        }

        return null;
    }

    private function brandRules(): array
    {
        if ($this->brandRules !== null) {
            return $this->brandRules;
        }

        if (!Schema::hasTable('sales_commission_rules')) {
            $this->brandRules = [];

            return $this->brandRules;
        }

        $this->brandRules = SalesCommissionRule::query()
            ->where('scope_type', 'brand')
            ->where('is_active', true)
            ->get()
            ->keyBy('brand_id')
            ->all();

        return $this->brandRules;
    }

    private function familyRules(): array
    {
        if ($this->familyRules !== null) {
            return $this->familyRules;
        }

        if (!Schema::hasTable('sales_commission_rules')) {
            $this->familyRules = [];

            return $this->familyRules;
        }

        $this->familyRules = SalesCommissionRule::query()
            ->where('scope_type', 'family')
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (SalesCommissionRule $rule) => strtoupper((string) $rule->family_code))
            ->all();

        return $this->familyRules;
    }

    private function defaultRate(): float
    {
        return $this->settingRate('sales.commission.default_rate_percent', 5.0);
    }

    private function projectRate(string $scope, float $fallback): float
    {
        return $this->settingRate("sales.commission.project.{$scope}_rate_percent", $fallback);
    }

    private function settingRate(string $key, float $fallback): float
    {
        if (!Schema::hasTable('settings')) {
            return $fallback;
        }

        return max(0, (float) Setting::get($key, $fallback));
    }
}
