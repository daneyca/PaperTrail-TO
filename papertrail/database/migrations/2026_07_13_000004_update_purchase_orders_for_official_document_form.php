<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'source_bac_resolution_id')) {
                    $table->foreignId('source_bac_resolution_id')->nullable()->after('source_pr_document_id')->constrained('bac_resolutions')->nullOnDelete();
                }

                if (!Schema::hasColumn('purchase_orders', 'source_abstract_id')) {
                    $table->unsignedBigInteger('source_abstract_id')->nullable()->after('source_bac_resolution_id');
                }

                if (!Schema::hasColumn('purchase_orders', 'po_date')) {
                    $table->date('po_date')->nullable()->after('po_number');
                }

                if (!Schema::hasColumn('purchase_orders', 'mode_of_procurement')) {
                    $table->string('mode_of_procurement')->nullable()->after('supplier_contact');
                }

                if (!Schema::hasColumn('purchase_orders', 'place_of_delivery')) {
                    $table->string('place_of_delivery')->nullable()->after('mode_of_procurement');
                }

                if (!Schema::hasColumn('purchase_orders', 'date_of_delivery')) {
                    $table->string('date_of_delivery')->nullable()->after('place_of_delivery');
                }

                if (!Schema::hasColumn('purchase_orders', 'delivery_term')) {
                    $table->string('delivery_term')->nullable()->after('date_of_delivery');
                }

                if (!Schema::hasColumn('purchase_orders', 'payment_term')) {
                    $table->string('payment_term')->nullable()->after('delivery_term');
                }

                if (!Schema::hasColumn('purchase_orders', 'total_amount_words')) {
                    $table->string('total_amount_words', 500)->nullable()->after('total_amount');
                }

                if (!Schema::hasColumn('purchase_orders', 'penalty_clause')) {
                    $table->text('penalty_clause')->nullable()->after('total_amount_words');
                }

                if (!Schema::hasColumn('purchase_orders', 'authorized_official_name')) {
                    $table->string('authorized_official_name')->nullable()->after('penalty_clause');
                }

                if (!Schema::hasColumn('purchase_orders', 'authorized_official_designation')) {
                    $table->string('authorized_official_designation')->nullable()->after('authorized_official_name');
                }

                if (!Schema::hasColumn('purchase_orders', 'supplier_representative_name')) {
                    $table->string('supplier_representative_name')->nullable()->after('authorized_official_designation');
                }

                if (!Schema::hasColumn('purchase_orders', 'supplier_representative_designation')) {
                    $table->string('supplier_representative_designation')->nullable()->after('supplier_representative_name');
                }

                if (!Schema::hasColumn('purchase_orders', 'supplier_conforme_date')) {
                    $table->string('supplier_conforme_date')->nullable()->after('supplier_representative_designation');
                }

                if (!Schema::hasColumn('purchase_orders', 'fund_available_text')) {
                    $table->string('fund_available_text')->nullable()->after('supplier_conforme_date');
                }

                if (!Schema::hasColumn('purchase_orders', 'alobs_number')) {
                    $table->string('alobs_number')->nullable()->after('fund_available_text');
                }

                if (!Schema::hasColumn('purchase_orders', 'alobs_amount')) {
                    $table->decimal('alobs_amount', 14, 2)->nullable()->after('alobs_number');
                }

                if (!Schema::hasColumn('purchase_orders', 'accountant_name')) {
                    $table->string('accountant_name')->nullable()->after('alobs_amount');
                }

                if (!Schema::hasColumn('purchase_orders', 'accountant_designation')) {
                    $table->string('accountant_designation')->nullable()->after('accountant_name');
                }

                if (!Schema::hasColumn('purchase_orders', 'submitted_by_user_id')) {
                    $table->foreignId('submitted_by_user_id')->nullable()->after('prepared_by_user_id')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('purchase_orders', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');
                }

                if (!Schema::hasColumn('purchase_orders', 'document_html')) {
                    $table->longText('document_html')->nullable()->after('remarks');
                }

                if (!Schema::hasColumn('purchase_orders', 'document_text')) {
                    $table->longText('document_text')->nullable()->after('document_html');
                }

                if (!Schema::hasColumn('purchase_orders', 'items_json')) {
                    $table->json('items_json')->nullable()->after('document_text');
                }

                if (!Schema::hasColumn('purchase_orders', 'document_version')) {
                    $table->unsignedInteger('document_version')->default(1)->after('items_json');
                }
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_order_items', 'description')) {
                    $table->text('description')->nullable()->after('item_description');
                }

                if (!Schema::hasColumn('purchase_order_items', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('total_cost');
                }
            });
        }
    }
};
