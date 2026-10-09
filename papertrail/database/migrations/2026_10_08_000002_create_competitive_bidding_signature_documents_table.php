<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'competitive_bidding_signature_documents';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            $this->completeExistingTable();

            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->uuid('document_uuid')->unique();
            $table->string('template_key')->index();
            $table->string('document_title');
            $table->string('tracking_number')->nullable();
            $table->longText('document_html')->nullable();
            $table->json('signatories')->nullable();
            $table->string('status')->default('pending_signatures')->index();
            $table->foreignId('prepared_by_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['template_key', 'tracking_number'], 'cb_signature_template_tracking_idx');
            $table->index('prepared_by_user_id', 'cb_signature_preparer_idx');
            $table->index('created_at', 'cb_signature_created_idx');
            $table->foreign('prepared_by_user_id', 'cb_sig_docs_preparer_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function completeExistingTable(): void
    {
        if (! $this->indexExists('competitive_bidding_signature_documents_document_uuid_unique')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique('document_uuid');
            });
        }

        if (! $this->indexExists('competitive_bidding_signature_documents_template_key_index')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('template_key');
            });
        }

        if (! $this->indexExists('competitive_bidding_signature_documents_status_index')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('status');
            });
        }

        if (! $this->indexExists('cb_signature_template_tracking_idx')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['template_key', 'tracking_number'], 'cb_signature_template_tracking_idx');
            });
        }

        if (! $this->indexExists('cb_signature_preparer_idx')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('prepared_by_user_id', 'cb_signature_preparer_idx');
            });
        }

        if (! $this->indexExists('cb_signature_created_idx')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('created_at', 'cb_signature_created_idx');
            });
        }

        if (! $this->foreignKeyExists('cb_sig_docs_preparer_fk')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('prepared_by_user_id', 'cb_sig_docs_preparer_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLE)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
