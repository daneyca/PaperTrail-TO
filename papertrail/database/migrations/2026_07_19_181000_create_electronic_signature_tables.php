<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('electronic_signatures')) {
            Schema::create('electronic_signatures', function (Blueprint $table) {
                $table->id();
                $table->uuid('signature_uuid')->nullable()->unique();
                $table->string('signature_code')->nullable()->unique();
                $table->string('document_type')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('document_label')->nullable();
                $table->string('tracking_number')->nullable();
                $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('signer_user_identifier')->nullable();
                $table->string('signer_name')->nullable();
                $table->string('signer_role')->nullable();
                $table->unsignedBigInteger('signer_office_id')->nullable();
                $table->string('signer_office_name')->nullable();
                $table->string('signer_position')->nullable();
                $table->string('signature_action')->nullable();
                $table->string('signature_status')->default('pending');
                $table->text('consent_text')->nullable();
                $table->string('signature_image_path')->nullable();
                $table->string('typed_signature_name')->nullable();
                $table->timestamp('password_confirmed_at')->nullable();
                $table->string('signing_code_hash')->nullable();
                $table->timestamp('signing_code_sent_at')->nullable();
                $table->timestamp('signing_code_expires_at')->nullable();
                $table->timestamp('signing_code_verified_at')->nullable();
                $table->unsignedTinyInteger('signing_attempts')->default(0);
                $table->string('document_hash_before')->nullable();
                $table->string('signed_snapshot_hash')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['document_type', 'document_id'], 'electronic_signatures_document_idx');
                $table->index('signer_user_id', 'electronic_signatures_signer_idx');
                $table->index('signature_status', 'electronic_signatures_status_idx');
                $table->index('signature_code', 'electronic_signatures_code_idx');
                $table->index('signed_at', 'electronic_signatures_signed_at_idx');
                $table->index('created_at', 'electronic_signatures_created_at_idx');
            });
        }

        if (! Schema::hasTable('signed_document_snapshots')) {
            Schema::create('signed_document_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('electronic_signature_id')->nullable()->constrained('electronic_signatures')->nullOnDelete();
                $table->string('document_type')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('document_label')->nullable();
                $table->string('tracking_number')->nullable();
                $table->longText('html_snapshot')->nullable();
                $table->longText('text_snapshot')->nullable();
                $table->string('snapshot_hash')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['document_type', 'document_id'], 'signed_snapshots_document_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_document_snapshots');
        Schema::dropIfExists('electronic_signatures');
    }
};
