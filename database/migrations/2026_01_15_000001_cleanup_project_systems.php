<?php

use App\Support\ProjectSystems;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            return;
        }

        $allowed = ProjectSystems::allowedKeys();

        DB::table('projects')
            ->select('id', 'systems_json')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($allowed) {
                foreach ($rows as $row) {
                    $systems = $row->systems_json;
                    if (is_string($systems)) {
                        $decoded = json_decode($systems, true);
                        $systems = is_array($decoded) ? $decoded : [];
                    } elseif (!is_array($systems)) {
                        $systems = [];
                    }

                    $filtered = array_values(array_unique(array_filter($systems, function ($key) use ($allowed) {
                        return in_array($key, $allowed, true);
                    })));

                    if ($filtered !== $systems) {
                        DB::table('projects')
                            ->where('id', $row->id)
                            ->update(['systems_json' => json_encode($filtered)]);
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};
