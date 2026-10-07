<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bac_resolutions')) {
            return;
        }

        Schema::table('bac_resolutions', function (Blueprint $table) {
            if (! Schema::hasColumn('bac_resolutions', 'document_html')) {
                $table->longText('document_html')->nullable()->after('remarks');
            }

            if (! Schema::hasColumn('bac_resolutions', 'document_text')) {
                $table->longText('document_text')->nullable()->after('document_html');
            }

            if (! Schema::hasColumn('bac_resolutions', 'document_version')) {
                $table->unsignedInteger('document_version')->default(1)->after('document_text');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bac_resolutions')) {
            return;
        }

        Schema::table('bac_resolutions', function (Blueprint $table) {
            foreach (['document_version', 'document_text', 'document_html'] as $column) {
                if (Schema::hasColumn('bac_resolutions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
