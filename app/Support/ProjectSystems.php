<?php

namespace App\Support;

class ProjectSystems
{
    public static function all(): array
    {
        return [
            'fire_protection_system' => 'Fire Protection System',
            'fire_alarm' => 'Fire Alarm',
            'fire_hydrant' => 'Fire Hydrant',
            'fire_sprinkler' => 'Fire Sprinkler',
            'kitchen_suppression' => 'Kitchen Suppression',
        ];
    }

    public static function allowedKeys(): array
    {
        return array_keys(self::all());
    }

    public static function atomicKeys(): array
    {
        return [
            'fire_alarm',
            'fire_hydrant',
            'fire_sprinkler',
            'kitchen_suppression',
        ];
    }

    public static function labelsFor(array $keys): array
    {
        $map = self::all();
        $labels = [];
        foreach ($keys as $key) {
            if (isset($map[$key])) {
                $labels[] = $map[$key];
            }
        }
        return $labels;
    }

    public static function normalizeSelection(array $keys): array
    {
        $allowed = self::all();
        $normalized = array_values(array_unique(array_filter($keys, function ($key) use ($allowed) {
            return isset($allowed[$key]);
        })));

        if (in_array('fire_protection_system', $normalized, true)) {
            $atomics = self::atomicKeys();
            $hasAtomic = array_intersect($normalized, $atomics);
            if (count($hasAtomic) === 0) {
                $normalized = array_values(array_unique(array_merge($normalized, $atomics)));
            }
        }

        return $normalized;
    }
}
