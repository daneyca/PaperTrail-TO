<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'approval_status')) {
                $table->string('approval_status')->nullable()->index()->after('bac_chair_confirmation_remarks');
            }
            if (!Schema::hasColumn('procurement_documents', 'approval_started_at')) {
                $table->timestamp('approval_started_at')->nullable()->after('approval_status');
            }
            if (!Schema::hasColumn('procurement_documents', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approval_started_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'approval_decision')) {
                $table->string('approval_decision')->nullable()->index()->after('approved_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'approval_remarks')) {
                $table->text('approval_remarks')->nullable()->after('approval_decision');
            }
            if (!Schema::hasColumn('procurement_documents', 'returned_by_approving_authority_at')) {
                $table->timestamp('returned_by_approving_authority_at')->nullable()->after('approval_remarks');
            }
        });

        if (!Schema::hasTable('document_approvals')) {
            Schema::create('document_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('approval_status')->default('pending')->index();
                $table->string('decision')->nullable()->index();
                $table->text('remarks')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $roleId = DB::table('roles')->where('code', 'approving_authority')->value('id');
            $permissionId = DB::table('permissions')->where('key', 'workflow.update_status')->value('id');

            if ($roleId && $permissionId && !DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->exists()) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};
