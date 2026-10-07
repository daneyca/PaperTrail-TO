<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annual_procurement_plans')) {
            Schema::create('annual_procurement_plans', function (Blueprint $table) {
                $table->id();
                $table->string('app_number')->nullable()->unique();
                $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
                $table->string('title')->nullable();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('office_name')->nullable();
                $table->string('status')->default('draft')->index();
                $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->text('return_reason')->nullable();
                $table->decimal('total_estimated_budget', 14, 2)->default(0);
                $table->decimal('total_personal_outlay', 14, 2)->default(0);
                $table->decimal('total_mooe', 14, 2)->default(0);
                $table->decimal('total_co', 14, 2)->default(0);
                $table->longText('document_html')->nullable();
                $table->longText('document_text')->nullable();
                $table->json('items_json')->nullable();
                $table->json('signatories_json')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('annual_procurement_plan_items')) {
            Schema::create('annual_procurement_plan_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('annual_procurement_plan_id')
                    ->constrained('annual_procurement_plans')
                    ->cascadeOnDelete();
                $table->string('pap_code')->nullable()->index();
                $table->text('procurement_program_project')->nullable();
                $table->string('pmo_end_user')->nullable()->index();
                $table->string('mode_of_procurement')->nullable()->index();
                $table->string('ads_post_ib_rei')->nullable();
                $table->string('sub_open_bids')->nullable();
                $table->string('notice_of_award')->nullable();
                $table->string('contract_signing')->nullable();
                $table->string('source_of_funds')->nullable();
                $table->decimal('estimated_total', 14, 2)->nullable();
                $table->decimal('personal_outlay', 14, 2)->nullable();
                $table->decimal('mooe', 14, 2)->nullable();
                $table->decimal('co', 14, 2)->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('annual_procurement_plan_id', 'app_items_plan_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_procurement_plan_items');
        Schema::dropIfExists('annual_procurement_plans');
    }
};
