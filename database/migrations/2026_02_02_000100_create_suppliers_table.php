<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 32)->nullable();
            $t->string('email', 150)->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Backfill minimal suppliers from referenced customers (if any)
        if (Schema::hasTable('customers') && Schema::hasTable('purchase_orders')) {
            $rows = DB::table('purchase_orders')
                ->select('supplier_id')
                ->whereNotNull('supplier_id')
                ->distinct()
                ->pluck('supplier_id')
                ->all();

            if (!empty($rows)) {
                $customers = DB::table('customers')
                    ->whereIn('id', $rows)
                    ->get(['id','name','phone','email','address']);

                foreach ($customers as $c) {
                    $exists = DB::table('suppliers')->where('id', $c->id)->exists();
                    if ($exists) {
                        continue;
                    }
                    DB::table('suppliers')->insert([
                        'id' => $c->id,
                        'name' => $c->name,
                        'phone' => $c->phone,
                        'email' => $c->email,
                        'address' => $c->address,
                        'notes' => null,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $maxId = DB::table('suppliers')->max('id');
                if ($maxId) {
                    DB::statement('ALTER TABLE suppliers AUTO_INCREMENT = '.((int) $maxId + 1));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
