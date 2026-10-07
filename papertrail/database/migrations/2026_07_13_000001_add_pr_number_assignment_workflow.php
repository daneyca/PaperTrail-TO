<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_documents') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE procurement_documents MODIFY tracking_number VARCHAR(255) NULL');
        }

        Schema::table('procurement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_documents', 'pr_number_status')) {
                $table->string('pr_number_status')->nullable()->index()->after('pr_status');
            }

            if (! Schema::hasColumn('procurement_documents', 'pr_no_requested_at')) {
                $table->timestamp('pr_no_requested_at')->nullable()->after('pr_number_status');
            }

            if (! Schema::hasColumn('procurement_documents', 'pr_no_assigned_by_user_id')) {
                $table->foreignId('pr_no_assigned_by_user_id')->nullable()->after('pr_no_requested_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('procurement_documents', 'pr_no_assigned_at')) {
                $table->timestamp('pr_no_assigned_at')->nullable()->after('pr_no_assigned_by_user_id');
            }

            if (! Schema::hasColumn('procurement_documents', 'pr_number_remarks')) {
                $table->text('pr_number_remarks')->nullable()->after('pr_no_assigned_at');
            }
        });

        if (! Schema::hasTable('pr_number_sequences')) {
            Schema::create('pr_number_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('fiscal_year')->index();
                $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
                $table->string('prefix')->nullable()->index();
                $table->unsignedInteger('last_sequence')->default(0);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_number_sequences');

        Schema::table('procurement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_documents', 'pr_number_remarks')) {
                $table->dropColumn('pr_number_remarks');
            }

            if (Schema::hasColumn('procurement_documents', 'pr_no_assigned_at')) {
                $table->dropColumn('pr_no_assigned_at');
            }

            if (Schema::hasColumn('procurement_documents', 'pr_no_assigned_by_user_id')) {
                $table->dropConstrainedForeignId('pr_no_assigned_by_user_id');
            }

            if (Schema::hasColumn('procurement_documents', 'pr_no_requested_at')) {
                $table->dropColumn('pr_no_requested_at');
            }

            if (Schema::hasColumn('procurement_documents', 'pr_number_status')) {
                $table->dropColumn('pr_number_status');
            }
        });
    }
};
