<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitive_bidding_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('project_title');
            $table->decimal('approved_budget', 15, 2)->nullable();
            $table->date('bid_opening_date')->nullable();
            $table->string('status')->default('open')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('competitive_bidding_suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competitive_bidding_record_id');
            $table->string('supplier_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('review_status')->default('under_review')->index();
            $table->text('decision_remarks')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('competitive_bidding_record_id', 'cb_suppliers_record_fk')
                ->references('id')->on('competitive_bidding_records')->cascadeOnDelete();
            $table->unique(['competitive_bidding_record_id', 'supplier_name'], 'cb_record_supplier_unique');
        });

        Schema::create('competitive_bidding_supporting_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competitive_bidding_record_id');
            $table->unsignedBigInteger('competitive_bidding_supplier_id')->nullable();
            $table->string('guide_number', 12);
            $table->string('requirement_key');
            $table->unsignedSmallInteger('display_order');
            $table->string('section');
            $table->string('phase')->default('bidding');
            $table->string('requirement_name');
            $table->string('document_source');
            $table->boolean('is_required')->default(true);
            $table->string('status')->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('checked_by_user_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->foreign('competitive_bidding_record_id', 'cb_items_record_fk')
                ->references('id')->on('competitive_bidding_records')->cascadeOnDelete();
            $table->foreign('competitive_bidding_supplier_id', 'cb_items_supplier_fk')
                ->references('id')->on('competitive_bidding_suppliers')->cascadeOnDelete();
            $table->foreign('checked_by_user_id', 'cb_items_checked_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(
                ['competitive_bidding_record_id', 'competitive_bidding_supplier_id', 'requirement_key'],
                'cb_supporting_scope_requirement_unique'
            );
            $table->index(
                ['competitive_bidding_record_id', 'competitive_bidding_supplier_id'],
                'cb_supporting_scope_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitive_bidding_supporting_items');
        Schema::dropIfExists('competitive_bidding_suppliers');
        Schema::dropIfExists('competitive_bidding_records');
    }
};
