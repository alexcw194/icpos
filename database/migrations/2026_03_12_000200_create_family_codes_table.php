<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->timestamps();
        });

        if (!Schema::hasTable('items')) {
            return;
        }

        $now = now();
        $seen = [];
        $payload = [];

        $codes = DB::table('items')
            ->whereNotNull('family_code')
            ->orderBy('family_code')
            ->pluck('family_code');

        foreach ($codes as $rawCode) {
            $code = trim((string) $rawCode);
            if ($code === '') {
                continue;
            }

            $normalized = Str::lower($code);
            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $payload[] = [
                'code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($payload)) {
            DB::table('family_codes')->insertOrIgnore($payload);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_codes');
    }
};

