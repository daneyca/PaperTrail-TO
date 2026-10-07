<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('offices', 'is_requesting_office')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->boolean('is_requesting_office')->default(false)->after('status');
            });
        }

        DB::table('offices')
            ->whereIn('type', [Office::TYPE_END_USER, Office::TYPE_LEGISLATIVE])
            ->update(['is_requesting_office' => true]);

        DB::table('offices')
            ->where(function ($query) {
                $query->where('code', 'BAC')
                    ->orWhere('name', 'like', '%Bids and Awards%');
            })
            ->update(['is_requesting_office' => false]);

        DB::table('users')
            ->where('role', User::ROLE_HEAD_OFFICE)
            ->where(function ($query) {
                $query->where('user_id', 'BAC-001')
                    ->orWhereIn('office_id', function ($subquery) {
                        $subquery->select('id')
                            ->from('offices')
                            ->where('code', 'BAC')
                            ->orWhere('name', 'like', '%Bids and Awards%');
                    });
            })
            ->update(['status' => User::STATUS_INACTIVE]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('offices', 'is_requesting_office')) {
            Schema::table('offices', function (Blueprint $table) {
                $table->dropColumn('is_requesting_office');
            });
        }
    }
};
