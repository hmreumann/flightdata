<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if(tenancy()->initialized){
            $this->call([
                TenantSeeder::class,
            ]);
        }

        if(! tenancy()->initialized){
            $this->call([
                UserSeeder::class,
                PlanSeeder::class,
            ]);

            $plan = Plan::where('slug', 'starter')->first();

            $tenant = Tenant::create([
                'id' => 'test',
                'name' => 'Test Aviation',
                'plan_id' => $plan?->id,
            ]);

            $tenant->domains()->create([
                'domain' => 'test',
            ]);

            $tenant->run(function () {
                User::factory()->create([
                    'name' => 'Test Admin',
                    'email' => 'admin@test.com',
                ]);
            });
        }
    }
}
