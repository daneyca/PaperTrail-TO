<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('source_rfq_supplier_quotation_id')->nullable()->constrained('rfq_supplier_quotations')->nullOnDelete();
            $table->string('supplier_email')->nullable();
            $table->string('supplier_tin', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_rfq_supplier_quotation_id');
            $table->dropColumn(['supplier_email', 'supplier_tin']);
        });
    }
};
