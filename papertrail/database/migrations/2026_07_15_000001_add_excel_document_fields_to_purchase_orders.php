<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'document_html')) {
                $table->longText('document_html')->nullable()->after('remarks');
            }

            if (! Schema::hasColumn('purchase_orders', 'document_text')) {
                $table->longText('document_text')->nullable()->after('document_html');
            }

            if (! Schema::hasColumn('purchase_orders', 'items_json')) {
                $table->json('items_json')->nullable()->after('document_text');
            }

            if (! Schema::hasColumn('purchase_orders', 'document_version')) {
                $table->unsignedInteger('document_version')->default(1)->after('items_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['document_version', 'items_json', 'document_text', 'document_html'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
