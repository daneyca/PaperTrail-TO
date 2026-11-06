<?php

namespace Database\Seeders;

use App\Services\PersonWorkspaceService;
use Illuminate\Database\Seeder;

class PersonWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        app(PersonWorkspaceService::class)->linkInitialAccounts();
    }
}
