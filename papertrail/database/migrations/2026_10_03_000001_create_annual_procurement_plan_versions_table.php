<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('annual_procurement_plan_versions')) {
            Schema::create('annual_procurement_plan_versions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('annual_procurement_plan_id');
                $table->unsignedInteger('version_no');
                $table->string('app_number')->nullable()->index();
                $table->string('app_no')->nullable();
                $table->string('document_reference_number')->nullable()->index();
                $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
                $table->string('plan_type')->nullable();
                $table->string('update_version_no')->nullable();
                $table->string('title')->nullable();
                $table->string('status')->nullable()->index();
                $table->unsignedBigInteger('prepared_by_user_id')->nullable();
                $table->unsignedBigInteger('submitted_by_user_id')->nullable();
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->text('return_reason')->nullable();
                $table->decimal('total_epa_budget', 15, 2)->default(0);
                $table->decimal('total_cse_budget', 15, 2)->default(0);
                $table->decimal('total_estimated_budget', 15, 2)->default(0);
                $table->decimal('total_personal_outlay', 15, 2)->default(0);
                $table->decimal('total_mooe', 15, 2)->default(0);
                $table->decimal('total_co', 15, 2)->default(0);
                $table->longText('document_html')->nullable();
                $table->longText('document_text')->nullable();
                $table->json('items_json')->nullable();
                $table->json('signatories_json')->nullable();
                $table->json('metadata')->nullable();
                $table->text('remarks')->nullable();
                $table->text('change_summary')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['annual_procurement_plan_id', 'version_no'], 'app_version_unique');
                $table->foreign('annual_procurement_plan_id', 'app_ver_app_fk')->references('id')->on('annual_procurement_plans')->cascadeOnDelete();
                $table->foreign('prepared_by_user_id', 'app_ver_prepared_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('submitted_by_user_id', 'app_ver_submitted_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('approved_by_user_id', 'app_ver_approved_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by_user_id', 'app_ver_created_by_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('annual_procurement_plan_items') && ! Schema::hasColumn('annual_procurement_plan_items', 'app_version')) {
            Schema::table('annual_procurement_plan_items', function (Blueprint $table): void {
                $table->unsignedInteger('app_version')->default(1)->after('annual_procurement_plan_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('annual_procurement_plan_items') && Schema::hasColumn('annual_procurement_plan_items', 'app_version')) {
            Schema::table('annual_procurement_plan_items', function (Blueprint $table): void {
                $table->dropColumn('app_version');
            });
        }

        Schema::dropIfExists('annual_procurement_plan_versions');
    }
};
