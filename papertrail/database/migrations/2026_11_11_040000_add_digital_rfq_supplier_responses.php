<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->boolean('supplier_response_enabled')->default(false);
            $table->foreignId('procurement_officer_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('rfq_supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('quotation_uuid')->unique();
            $table->foreignId('rfq_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('link_token');
            $table->string('supplier_name');
            $table->string('supplier_email')->nullable();
            $table->string('supplier_contact', 40)->nullable();
            $table->string('supplier_tin', 40)->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_position')->nullable();
            $table->unsignedInteger('delivery_days')->nullable();
            $table->string('philgeps_registration_number')->nullable();
            $table->string('philgeps_registration_type')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('issued_snapshot');
            $table->json('response_snapshot')->nullable();
            $table->char('response_hash', 64)->nullable();
            $table->longText('signature_data')->nullable();
            $table->text('consent_text')->nullable();
            $table->decimal('total_amount', 18, 2)->nullable();
            $table->text('supplier_remarks')->nullable();
            $table->text('review_remarks')->nullable();
            $table->string('signer_ip_address', 45)->nullable();
            $table->string('signer_user_agent', 512)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['rfq_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_supplier_quotations');
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_officer_user_id');
            $table->dropColumn('supplier_response_enabled');
        });
    }
};
