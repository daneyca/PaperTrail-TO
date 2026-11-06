<?php

use App\Services\PersonWorkspaceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(fn () => app(PersonWorkspaceService::class)->linkInitialAccounts());
    }

    public function down(): void
    {
        // Intentionally retain identity links and history. Never restore retired credentials.
    }
};
