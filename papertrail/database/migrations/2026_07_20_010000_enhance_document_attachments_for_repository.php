<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_attachments')) {
            Schema::create('document_attachments', function (Blueprint $table) {
                $table->id();
                $table->uuid('attachment_uuid')->nullable()->unique();
                $table->string('attachable_type')->nullable();
                $table->unsignedBigInteger('attachable_id')->nullable();
                $table->foreignId('procurement_document_id')->nullable()->constrained('procurement_documents')->nullOnDelete();
                $table->string('document_type')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('tracking_number')->nullable();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->string('office_name')->nullable();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('original_name')->nullable();
                $table->string('original_filename')->nullable();
                $table->string('stored_filename')->nullable();
                $table->string('file_path')->nullable();
                $table->string('disk')->default('local');
                $table->string('mime_type')->nullable();
                $table->string('file_extension')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('file_hash')->nullable();
                $table->text('description')->nullable();
                $table->string('document_section')->nullable();
                $table->string('attachment_category')->nullable();
                $table->string('ocr_status')->default('pending');
                $table->longText('ocr_text')->nullable();
                $table->json('extracted_metadata')->nullable();
                $table->string('ai_analysis_status')->default('pending');
                $table->boolean('is_required')->default(false);
                $table->boolean('is_confidential')->default(false);
                $table->string('status')->default('active');
                $table->unsignedBigInteger('deleted_by_user_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        } else {
            $this->makeLegacyProcurementDocumentNullable();

            Schema::table('document_attachments', function (Blueprint $table) {
                if (! Schema::hasColumn('document_attachments', 'attachment_uuid')) {
                    $table->uuid('attachment_uuid')->nullable()->unique();
                }

                if (! Schema::hasColumn('document_attachments', 'attachable_type')) {
                    $table->string('attachable_type')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'attachable_id')) {
                    $table->unsignedBigInteger('attachable_id')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'document_type')) {
                    $table->string('document_type')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'document_id')) {
                    $table->unsignedBigInteger('document_id')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'tracking_number')) {
                    $table->string('tracking_number')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'office_id')) {
                    $table->unsignedBigInteger('office_id')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'office_name')) {
                    $table->string('office_name')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'original_filename')) {
                    $table->string('original_filename')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'stored_filename')) {
                    $table->string('stored_filename')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'disk')) {
                    $table->string('disk')->default('local');
                }

                if (! Schema::hasColumn('document_attachments', 'file_extension')) {
                    $table->string('file_extension')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'file_hash')) {
                    $table->string('file_hash')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'description')) {
                    $table->text('description')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'attachment_category')) {
                    $table->string('attachment_category')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'ocr_status')) {
                    $table->string('ocr_status')->default('pending');
                }

                if (! Schema::hasColumn('document_attachments', 'ocr_text')) {
                    $table->longText('ocr_text')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'extracted_metadata')) {
                    $table->json('extracted_metadata')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'ai_analysis_status')) {
                    $table->string('ai_analysis_status')->default('pending');
                }

                if (! Schema::hasColumn('document_attachments', 'is_required')) {
                    $table->boolean('is_required')->default(false);
                }

                if (! Schema::hasColumn('document_attachments', 'is_confidential')) {
                    $table->boolean('is_confidential')->default(false);
                }

                if (! Schema::hasColumn('document_attachments', 'status')) {
                    $table->string('status')->default('active');
                }

                if (! Schema::hasColumn('document_attachments', 'deleted_by_user_id')) {
                    $table->unsignedBigInteger('deleted_by_user_id')->nullable();
                }

                if (! Schema::hasColumn('document_attachments', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        $this->backfillAttachmentMetadata();
        $this->addIndex('doc_attach_attachable_idx', ['attachable_type', 'attachable_id']);
        $this->addIndex('doc_attach_doc_idx', ['document_type', 'document_id']);
        $this->addIndex('doc_attach_tracking_idx', ['tracking_number']);
        $this->addIndex('doc_attach_office_idx', ['office_id']);
        $this->addIndex('doc_attach_uploaded_idx', ['uploaded_by_user_id']);
        $this->addIndex('doc_attach_category_idx', ['attachment_category']);
        $this->addIndex('doc_attach_ocr_status_idx', ['ocr_status']);
        $this->addIndex('doc_attach_status_idx', ['status']);
        $this->addIndex('doc_attach_created_idx', ['created_at']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_attachments')) {
            return;
        }

        foreach ([
            'doc_attach_attachable_idx',
            'doc_attach_doc_idx',
            'doc_attach_tracking_idx',
            'doc_attach_office_idx',
            'doc_attach_uploaded_idx',
            'doc_attach_category_idx',
            'doc_attach_ocr_status_idx',
            'doc_attach_status_idx',
            'doc_attach_created_idx',
        ] as $index) {
            $this->dropIndex($index);
        }

        Schema::table('document_attachments', function (Blueprint $table) {
            foreach ([
                'attachment_uuid',
                'attachable_type',
                'attachable_id',
                'document_type',
                'document_id',
                'tracking_number',
                'office_id',
                'office_name',
                'original_filename',
                'stored_filename',
                'disk',
                'file_extension',
                'file_size',
                'file_hash',
                'description',
                'attachment_category',
                'ocr_status',
                'ocr_text',
                'extracted_metadata',
                'ai_analysis_status',
                'is_required',
                'is_confidential',
                'status',
                'deleted_by_user_id',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('document_attachments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function makeLegacyProcurementDocumentNullable(): void
    {
        if (! Schema::hasColumn('document_attachments', 'procurement_document_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE document_attachments MODIFY procurement_document_id BIGINT UNSIGNED NULL');
        }
    }

    private function backfillAttachmentMetadata(): void
    {
        if (! Schema::hasTable('document_attachments')) {
            return;
        }

        DB::table('document_attachments')
            ->whereNull('attachment_uuid')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($attachments) {
                foreach ($attachments as $attachment) {
                    DB::table('document_attachments')
                        ->where('id', $attachment->id)
                        ->update(['attachment_uuid' => (string) Illuminate\Support\Str::uuid()]);
                }
            });

        if (Schema::hasColumn('document_attachments', 'original_name')) {
            DB::table('document_attachments')
                ->whereNull('original_filename')
                ->update(['original_filename' => DB::raw('original_name')]);
        }

        if (Schema::hasColumn('document_attachments', 'size')) {
            DB::table('document_attachments')
                ->whereNull('file_size')
                ->update(['file_size' => DB::raw('size')]);
        }

        if (Schema::hasColumn('document_attachments', 'document_section')) {
            DB::table('document_attachments')
                ->whereNull('attachment_category')
                ->update(['attachment_category' => DB::raw('document_section')]);
        }

        DB::table('document_attachments')
            ->whereNull('disk')
            ->update(['disk' => 'local']);

        DB::table('document_attachments')
            ->whereNull('status')
            ->update(['status' => 'active']);
    }

    private function addIndex(string $name, array $columns): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        Schema::table('document_attachments', function (Blueprint $table) use ($name, $columns) {
            $table->index($columns, $name);
        });
    }

    private function dropIndex(string $name): void
    {
        if (! $this->indexExists($name)) {
            return;
        }

        Schema::table('document_attachments', function (Blueprint $table) use ($name) {
            $table->dropIndex($name);
        });
    }

    private function indexExists(string $name): bool
    {
        if (! Schema::hasTable('document_attachments') || DB::getDriverName() !== 'mysql') {
            return false;
        }

        return collect(DB::select('SHOW INDEX FROM `document_attachments` WHERE Key_name = ?', [$name]))->isNotEmpty();
    }
};
