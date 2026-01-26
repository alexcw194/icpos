<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('bq_line_template_lines');
        Schema::dropIfExists('bq_line_templates');
    }

    public function down(): void
    {
        // Intentionally left empty: template feature removed.
    }
};
