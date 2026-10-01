<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AgentOwnerSeeder extends Seeder
{
    public function run(): void
    {
        $agents = User::where('role', 'agent')->get();
        $owners = User::where('role', 'owner')->get();

        foreach ($agents as $agent) {
            $randomOwners = $owners->random(2);

            foreach ($randomOwners as $owner) {
                DB::table('agent_owner')->insert([
                    'agent_id' => $agent->id,
                    'owner_id' => $owner->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
