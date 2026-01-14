<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contact_titles')) {
            Schema::create('contact_titles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contact_positions')) {
            Schema::create('contact_positions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'contact_title_id')) {
                $table->foreignId('contact_title_id')->nullable()->constrained('contact_titles')->nullOnDelete();
            }
            if (!Schema::hasColumn('contacts', 'contact_position_id')) {
                $table->foreignId('contact_position_id')->nullable()->constrained('contact_positions')->nullOnDelete();
            }
            if (!Schema::hasColumn('contacts', 'title_snapshot')) {
                $table->string('title_snapshot', 30)->nullable();
            }
            if (!Schema::hasColumn('contacts', 'position_snapshot')) {
                $table->string('position_snapshot', 120)->nullable();
            }
        });

        $defaultTitles = ['Bpk', 'Ibu', 'Mr', 'Mrs', 'Ms', 'Dr', 'Ir'];
        foreach ($defaultTitles as $idx => $name) {
            DB::table('contact_titles')->updateOrInsert(
                ['name' => $name],
                [
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $defaultPositions = ['Purchasing', 'Engineering', 'Finance', 'Owner', 'Project Manager'];
        foreach ($defaultPositions as $idx => $name) {
            DB::table('contact_positions')->updateOrInsert(
                ['name' => $name],
                [
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $titleMap = DB::table('contact_titles')->pluck('id', 'name')->toArray();
        $positionMap = DB::table('contact_positions')->pluck('id', 'name')->toArray();

        DB::table('contacts')
            ->select('id', 'title', 'position')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$titleMap, &$positionMap) {
                foreach ($rows as $row) {
                    $title = trim((string) ($row->title ?? ''));
                    $position = trim((string) ($row->position ?? ''));

                    $titleId = null;
                    $positionId = null;

                    if ($title !== '') {
                        $titleId = $titleMap[$title] ?? null;
                        if (!$titleId) {
                            $titleId = DB::table('contact_titles')->insertGetId([
                                'name' => $title,
                                'is_active' => true,
                                'sort_order' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $titleMap[$title] = $titleId;
                        }
                    }

                    if ($position !== '') {
                        $positionId = $positionMap[$position] ?? null;
                        if (!$positionId) {
                            $positionId = DB::table('contact_positions')->insertGetId([
                                'name' => $position,
                                'is_active' => true,
                                'sort_order' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $positionMap[$position] = $positionId;
                        }
                    }

                    if ($titleId || $positionId) {
                        DB::table('contacts')
                            ->where('id', $row->id)
                            ->update([
                                'contact_title_id' => $titleId,
                                'contact_position_id' => $positionId,
                                'title_snapshot' => $title !== '' ? $title : null,
                                'position_snapshot' => $position !== '' ? $position : null,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'contact_title_id')) {
                $table->dropForeign(['contact_title_id']);
                $table->dropColumn('contact_title_id');
            }
            if (Schema::hasColumn('contacts', 'contact_position_id')) {
                $table->dropForeign(['contact_position_id']);
                $table->dropColumn('contact_position_id');
            }
            if (Schema::hasColumn('contacts', 'title_snapshot')) {
                $table->dropColumn('title_snapshot');
            }
            if (Schema::hasColumn('contacts', 'position_snapshot')) {
                $table->dropColumn('position_snapshot');
            }
        });

        Schema::dropIfExists('contact_titles');
        Schema::dropIfExists('contact_positions');
    }
};
