<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 60)->index();
                $table->string('template_name');
                $table->string('file_path')->nullable();
                $table->string('file_format', 20);
                $table->string('version', 60)->nullable();
                $table->integer('fiscal_year')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['document_type', 'is_active']);
            });
        }

        if (! Schema::hasTable('document_template_imports')) {
            Schema::create('document_template_imports', function (Blueprint $table) {
                $table->id();
                $table->string('document_type', 60)->index();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('original_name');
                $table->string('stored_name')->nullable();
                $table->string('file_path');
                $table->string('file_format', 20);
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('import_status', 40)->default('manual_review')->index();
                $table->unsignedBigInteger('imported_document_id')->nullable();
                $table->string('imported_document_type')->nullable();
                $table->json('validation_errors')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['document_type', 'import_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_imports');
        Schema::dropIfExists('document_templates');
    }
};
