<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_documents')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE procurement_documents MODIFY tracking_number VARCHAR(255) NULL');
            }

            Schema::table('procurement_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('procurement_documents', 'prepared_by_user_id')) {
                    $table->foreignId('prepared_by_user_id')->nullable()->after('submitted_by_user_id')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('procurement_documents', 'returned_at')) {
                    $table->timestamp('returned_at')->nullable()->after('submitted_at');
                }

                if (!Schema::hasColumn('procurement_documents', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('returned_at');
                }
            });
        }

        if (!Schema::hasTable('ppmp_items')) {
            Schema::create('ppmp_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->string('item_no')->nullable();
                $table->text('general_description');
                $table->decimal('quantity', 14, 2)->default(0);
                $table->string('unit')->nullable();
                $table->decimal('estimated_unit_cost', 14, 2)->default(0);
                $table->decimal('estimated_total_cost', 14, 2)->default(0);
                $table->string('procurement_mode')->nullable();
                $table->string('schedule_quarter')->nullable();
                $table->string('category')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['procurement_document_id', 'item_no']);
            });
        }

        if (!Schema::hasTable('document_attachments')) {
            Schema::create('document_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('original_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('document_section')->nullable();
                $table->timestamps();

                $table->index(['procurement_document_id', 'document_section'], 'doc_attach_document_section_idx');
            });
        } elseif (!$this->indexExists('document_attachments', 'doc_attach_document_section_idx')) {
            Schema::table('document_attachments', function (Blueprint $table) {
                $table->index(['procurement_document_id', 'document_section'], 'doc_attach_document_section_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
        Schema::dropIfExists('ppmp_items');

        if (Schema::hasTable('procurement_documents')) {
            Schema::table('procurement_documents', function (Blueprint $table) {
                if (Schema::hasColumn('procurement_documents', 'accepted_at')) {
                    $table->dropColumn('accepted_at');
                }

                if (Schema::hasColumn('procurement_documents', 'returned_at')) {
                    $table->dropColumn('returned_at');
                }

                if (Schema::hasColumn('procurement_documents', 'prepared_by_user_id')) {
                    $table->dropConstrainedForeignId('prepared_by_user_id');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
