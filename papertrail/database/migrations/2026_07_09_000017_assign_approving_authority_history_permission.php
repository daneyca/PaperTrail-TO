<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('code', 'approving_authority')->first();
        $permission = Permission::where('key', 'workflow.view_history')->first();

        if ($role && $permission) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        $role = Role::where('code', 'approving_authority')->first();
        $permission = Permission::where('key', 'workflow.view_history')->first();

        if ($role && $permission) {
            $role->permissions()->detach($permission->id);
        }
    }
};
