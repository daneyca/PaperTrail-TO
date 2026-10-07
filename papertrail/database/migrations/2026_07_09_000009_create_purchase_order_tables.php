<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->foreignId('source_pr_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->string('po_number')->nullable()->unique();
                $table->unsignedSmallInteger('fiscal_year')->index();
                $table->string('supplier_name')->nullable();
                $table->text('supplier_address')->nullable();
                $table->string('supplier_contact')->nullable();
                $table->string('delivery_place')->nullable();
                $table->date('delivery_date')->nullable();
                $table->string('delivery_terms')->nullable();
                $table->string('payment_terms')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('status')->index();
                $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('returned_at');
                }
            });
        }

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('source_pr_item_id')->nullable()->constrained('purchase_request_items')->nullOnDelete();
                $table->string('item_no')->nullable();
                $table->text('item_description');
                $table->decimal('quantity', 14, 2)->default(0);
                $table->string('unit')->nullable();
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
