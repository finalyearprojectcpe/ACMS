<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SchoolAdminSeeder::class,
            FacultySeeder::class,
            AcademicStructureSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
