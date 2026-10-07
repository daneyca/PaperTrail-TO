<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_documents', 'routed_by_user_id')) {
                $table->foreignId('routed_by_user_id')->nullable()->after('bac_secretariat_remarks')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'routed_at')) {
                $table->timestamp('routed_at')->nullable()->after('routed_by_user_id');
            }
            if (!Schema::hasColumn('procurement_documents', 'route_destination_role')) {
                $table->string('route_destination_role')->nullable()->index()->after('routed_at');
            }
            if (!Schema::hasColumn('procurement_documents', 'route_destination_office_id')) {
                $table->foreignId('route_destination_office_id')->nullable()->after('route_destination_role')->constrained('offices')->nullOnDelete();
            }
            if (!Schema::hasColumn('procurement_documents', 'route_remarks')) {
                $table->text('route_remarks')->nullable()->after('route_destination_office_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_documents', 'route_remarks')) {
                $table->dropColumn('route_remarks');
            }
            if (Schema::hasColumn('procurement_documents', 'route_destination_office_id')) {
                $table->dropConstrainedForeignId('route_destination_office_id');
            }
            if (Schema::hasColumn('procurement_documents', 'route_destination_role')) {
                $table->dropColumn('route_destination_role');
            }
            if (Schema::hasColumn('procurement_documents', 'routed_at')) {
                $table->dropColumn('routed_at');
            }
            if (Schema::hasColumn('procurement_documents', 'routed_by_user_id')) {
                $table->dropConstrainedForeignId('routed_by_user_id');
            }
        });
    }
};
