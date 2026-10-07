<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_template_imports') || ! Schema::hasColumn('document_template_imports', 'import_status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE document_template_imports MODIFY import_status VARCHAR(40) NOT NULL DEFAULT 'manual_review'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_template_imports') || ! Schema::hasColumn('document_template_imports', 'import_status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE document_template_imports MODIFY import_status VARCHAR(40) NOT NULL DEFAULT 'uploaded'");
        }
    }
};
