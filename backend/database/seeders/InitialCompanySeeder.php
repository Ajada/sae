<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['document' => '00000000000000'],
            [
                'uuid' => 'f8343059-f01d-4e6e-accd-f059429c1ee6',
                'name' => 'AD Tech',
                'legal_name' => 'AD Tech',
                'email' => 'admin@xflow.local',
                'phone' => null,
                'is_active' => true,
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'admin@xflow.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'current_company_id' => $company->id,
            ]
        );

        if (! $user->current_company_id) {
            $user->update([
                'current_company_id' => $company->id,
            ]);
        }

        $company->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'is_owner' => true,
                'joined_at' => now(),
            ],
        ]);
    }
}
