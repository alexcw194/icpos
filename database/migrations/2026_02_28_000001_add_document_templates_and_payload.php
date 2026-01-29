<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name', 190);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('document_template_fields')) {
            Schema::create('document_template_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
                $table->string('field_key', 64);
                $table->string('label', 190);
                $table->string('field_type', 32);
                $table->boolean('required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('options')->nullable();
                $table->timestamps();
                $table->unique(['document_template_id', 'field_key'], 'doc_tpl_fields_unique');
            });
        }

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'document_template_id')) {
                $table->foreignId('document_template_id')
                    ->nullable()
                    ->constrained('document_templates')
                    ->nullOnDelete()
                    ->after('sequence');
            }
            if (!Schema::hasColumn('documents', 'payload_json')) {
                $table->json('payload_json')->nullable()->after('body_html');
            }
        });

        $templateId = DB::table('document_templates')
            ->where('code', 'ICP_BAST_STANDARD')
            ->value('id');

        if (!$templateId) {
            $templateId = DB::table('document_templates')->insertGetId([
                'code' => 'ICP_BAST_STANDARD',
                'name' => 'BAST - Serah Terima Pekerjaan (ICP)',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingFields = DB::table('document_template_fields')
            ->where('document_template_id', $templateId)
            ->count();

        if ($existingFields === 0) {
            $fields = [
                ['field_key' => 'nomor_ba', 'label' => 'Nomor BA', 'field_type' => 'text', 'required' => 1, 'sort_order' => 1],
                ['field_key' => 'tanggal_ba', 'label' => 'Tanggal BA', 'field_type' => 'date', 'required' => 1, 'sort_order' => 2],
                ['field_key' => 'kota', 'label' => 'Kota', 'field_type' => 'text', 'required' => 1, 'sort_order' => 3],
                ['field_key' => 'nama_customer', 'label' => 'Nama Customer', 'field_type' => 'text', 'required' => 1, 'sort_order' => 4],
                ['field_key' => 'lokasi_pekerjaan', 'label' => 'Lokasi Pekerjaan', 'field_type' => 'textarea', 'required' => 1, 'sort_order' => 5],
                ['field_key' => 'nama_pekerjaan', 'label' => 'Nama Pekerjaan', 'field_type' => 'text', 'required' => 1, 'sort_order' => 6],
                ['field_key' => 'jenis_kontrak', 'label' => 'Jenis Kontrak', 'field_type' => 'select', 'required' => 1, 'sort_order' => 7,
                    'options' => json_encode(['SPK', 'PO', 'SO', 'BQ', 'Lainnya'])],
                ['field_key' => 'nomor_kontrak', 'label' => 'Nomor Kontrak', 'field_type' => 'text', 'required' => 1, 'sort_order' => 8],
                ['field_key' => 'tanggal_mulai', 'label' => 'Tanggal Mulai', 'field_type' => 'date', 'required' => 1, 'sort_order' => 9],
                ['field_key' => 'status_pekerjaan', 'label' => 'Status Pekerjaan', 'field_type' => 'text', 'required' => 1, 'sort_order' => 10],
                ['field_key' => 'tanggal_progress', 'label' => 'Tanggal Progress', 'field_type' => 'date', 'required' => 1, 'sort_order' => 11],
                ['field_key' => 'work_points', 'label' => 'Ruang Lingkup & Catatan', 'field_type' => 'repeater_text', 'required' => 1, 'sort_order' => 12],
                ['field_key' => 'icp_signers', 'label' => 'ICP Signers', 'field_type' => 'repeater_signers', 'required' => 1, 'sort_order' => 13],
                ['field_key' => 'customer_signers', 'label' => 'Customer Signers', 'field_type' => 'repeater_signers', 'required' => 1, 'sort_order' => 14],
            ];

            $now = now();
            $rows = array_map(function ($field) use ($templateId, $now) {
                return [
                    'document_template_id' => $templateId,
                    'field_key' => $field['field_key'],
                    'label' => $field['label'],
                    'field_type' => $field['field_type'],
                    'required' => $field['required'],
                    'sort_order' => $field['sort_order'],
                    'options' => $field['options'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $fields);

            DB::table('document_template_fields')->insert($rows);
        }
    }

    public function down(): void
    {
        // keep data intact
    }
};
