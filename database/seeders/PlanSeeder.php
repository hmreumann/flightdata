<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Basic plan for small flight schools and operators.',
            'max_users' => 5,
            'max_aircraft' => 10,
            'is_active' => true,
        ]);
    }
}
