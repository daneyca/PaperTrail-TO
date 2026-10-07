<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signature_requests')) {
            Schema::create('signature_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('request_uuid')->nullable()->unique();
                $table->string('document_type')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('document_label')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('signatory_slot')->nullable();
                $table->string('signatory_label')->nullable();
                $table->unsignedInteger('signing_order')->default(1);
                $table->string('signing_mode')->default('parallel');
                $table->boolean('is_required')->default(true);
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('requested_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requested_to_role')->nullable();
                $table->unsignedBigInteger('requested_to_office_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('notification_sent_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->text('remarks')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['document_type', 'document_id'], 'signature_requests_document_idx');
                $table->index('requested_to_user_id', 'signature_requests_user_idx');
                $table->index('requested_to_role', 'signature_requests_role_idx');
                $table->index('requested_to_office_id', 'signature_requests_office_idx');
                $table->index('status', 'signature_requests_status_idx');
                $table->index('signatory_slot', 'signature_requests_slot_idx');
                $table->index('signing_order', 'signature_requests_order_idx');
                $table->index('created_at', 'signature_requests_created_idx');
            });
        }

        if (Schema::hasTable('electronic_signatures')) {
            Schema::table('electronic_signatures', function (Blueprint $table) {
                if (! Schema::hasColumn('electronic_signatures', 'signature_request_id')) {
                    $table->foreignId('signature_request_id')->nullable()->after('id')->constrained('signature_requests')->nullOnDelete();
                }

                if (! Schema::hasColumn('electronic_signatures', 'signatory_slot')) {
                    $table->string('signatory_slot')->nullable()->after('signature_action');
                }

                if (! Schema::hasColumn('electronic_signatures', 'signatory_label')) {
                    $table->string('signatory_label')->nullable()->after('signatory_slot');
                }
            });
        }

        if (Schema::hasTable('bac_resolutions') && ! Schema::hasColumn('bac_resolutions', 'signature_status')) {
            Schema::table('bac_resolutions', function (Blueprint $table) {
                $table->string('signature_status')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bac_resolutions') && Schema::hasColumn('bac_resolutions', 'signature_status')) {
            Schema::table('bac_resolutions', function (Blueprint $table) {
                $table->dropColumn('signature_status');
            });
        }

        if (Schema::hasTable('electronic_signatures')) {
            Schema::table('electronic_signatures', function (Blueprint $table) {
                foreach (['signatory_label', 'signatory_slot', 'signature_request_id'] as $column) {
                    if (Schema::hasColumn('electronic_signatures', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('signature_requests');
    }
};
