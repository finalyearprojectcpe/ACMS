<?php

namespace Database\Seeders;

use App\Actions\ProvisionSchoolAction;
use Illuminate\Database\Seeder;

class SchoolAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProvisionSchoolAction $provisionSchool): void
    {
        $provisionSchool->execute([
            'name' => 'Federal University of Technology Owerri',
            'abbreviation' => 'FUTO',
            'admin_name' => 'Okoro Chinyere',
            'admin_email' => 'schooladmin@futo.edu',
            'admin_password' => 'password',
            'seed_academic_sessions' => true,
        ]);
    }
}
