<?php

namespace App\Services;

class RefillSizeParser
{
    public function detectKg(?string $itemName): ?float
    {
        $name = trim((string) $itemName);
        if ($name === '') {
            return null;
        }

        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*kg\b/i', $name, $matches)) {
            return null;
        }

        $normalized = str_replace(',', '.', $matches[1]);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
