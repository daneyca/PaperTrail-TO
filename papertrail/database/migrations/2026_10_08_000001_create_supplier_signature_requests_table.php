<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_signature_requests')) {
            return;
        }

        Schema::create('supplier_signature_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('public_token', 96)->unique();
            $table->string('document_type')->nullable();
            $table->string('document_title');
            $table->string('tracking_number')->nullable();
            $table->string('supplier_name');
            $table->string('supplier_email')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_position')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('instructions')->nullable();
            $table->string('source_document_path')->nullable();
            $table->string('source_document_name')->nullable();
            $table->string('source_document_mime')->nullable();
            $table->unsignedBigInteger('source_document_size')->nullable();
            $table->string('source_document_hash', 64)->nullable();
            $table->string('source_document_disk')->default('local');
            $table->string('signed_document_path')->nullable();
            $table->string('signed_document_name')->nullable();
            $table->string('signed_document_mime')->nullable();
            $table->unsignedBigInteger('signed_document_size')->nullable();
            $table->string('signed_document_hash', 64)->nullable();
            $table->string('signed_document_disk')->default('local');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('signer_ip_address', 45)->nullable();
            $table->text('signer_user_agent')->nullable();
            $table->text('supplier_remarks')->nullable();
            $table->text('bac_remarks')->nullable();
            $table->text('consent_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'tracking_number'], 'supplier_signatures_document_idx');
            $table->index('supplier_name', 'supplier_signatures_supplier_idx');
            $table->index('requested_by_user_id', 'supplier_signatures_requester_idx');
            $table->index('created_at', 'supplier_signatures_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_signature_requests');
    }
};
