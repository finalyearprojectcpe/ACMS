<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->updateOrCreate(
            ['abbreviation' => 'FUTO'],
            [
                'name' => 'Federal University of Technology Owerri',
                'slug' => Str::slug('Federal University of Technology Owerri'),
            ],
        );

        foreach ($this->faculties() as $facultyData) {
            $faculty = Faculty::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'slug' => Str::slug($facultyData['name']),
                ],
                [
                    'name' => $facultyData['name'],
                    'abbreviation' => $facultyData['abbreviation'],
                ],
            );

            foreach ($facultyData['departments'] as $departmentData) {
                Department::query()->updateOrCreate(
                    [
                        'faculty_id' => $faculty->id,
                        'slug' => Str::slug($departmentData['name']),
                    ],
                    [
                        'name' => $departmentData['name'],
                        'abbreviation' => $departmentData['abbreviation'],
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array{name: string, abbreviation: string, departments: array<int, array{name: string, abbreviation: string}>}>
     */
    private function faculties(): array
    {
        return [
            [
                'name' => 'Agriculture and Agricultural Technology',
                'abbreviation' => 'SAAT',
                'departments' => [
                    ['name' => 'Agribusiness', 'abbreviation' => 'AGR'],
                    ['name' => 'Agricultural Economics', 'abbreviation' => 'AGE'],
                    ['name' => 'Agricultural Extension', 'abbreviation' => 'AGX'],
                    ['name' => 'Animal Science and Technology', 'abbreviation' => 'AST'],
                    ['name' => 'Crop Science and Technology', 'abbreviation' => 'CST'],
                    ['name' => 'Fisheries and Aquaculture Technology', 'abbreviation' => 'FAQ'],
                    ['name' => 'Forestry and Wildlife Technology', 'abbreviation' => 'FWT'],
                    ['name' => 'Soil Science and Technology', 'abbreviation' => 'SST'],
                ],
            ],
            [
                'name' => 'Basic Medical Science',
                'abbreviation' => 'SBMS',
                'departments' => [
                    ['name' => 'Human Anatomy', 'abbreviation' => 'HAT'],
                    ['name' => 'Human Physiology', 'abbreviation' => 'HPY'],
                ],
            ],
            [
                'name' => 'Biological Science',
                'abbreviation' => 'SOBS',
                'departments' => [
                    ['name' => 'Biochemistry', 'abbreviation' => 'BCH'],
                    ['name' => 'Biology', 'abbreviation' => 'BIO'],
                    ['name' => 'Biotechnology', 'abbreviation' => 'BTC'],
                    ['name' => 'Forensic Science', 'abbreviation' => 'FSC'],
                    ['name' => 'Microbiology', 'abbreviation' => 'MCB'],
                ],
            ],
            [
                'name' => 'Engineering and Engineering Technology',
                'abbreviation' => 'SEET',
                'departments' => [
                    ['name' => 'Agricultural and Bio Resources Engineering', 'abbreviation' => 'ABE'],
                    ['name' => 'Biomedical Engineering', 'abbreviation' => 'BME'],
                    ['name' => 'Chemical Engineering', 'abbreviation' => 'CHE'],
                    ['name' => 'Civil Engineering', 'abbreviation' => 'CIE'],
                    ['name' => 'Food Science and Technology', 'abbreviation' => 'FST'],
                    ['name' => 'Material and Metallurgical Engineering', 'abbreviation' => 'MME'],
                    ['name' => 'Mechanical Engineering', 'abbreviation' => 'MEE'],
                    ['name' => 'Petroleum Engineering', 'abbreviation' => 'PET'],
                    ['name' => 'Polymer and Textile Engineering', 'abbreviation' => 'PTE'],
                ],
            ],
            [
                'name' => 'Electrical Systems and Engineering Technology',
                'abbreviation' => 'SESET',
                'departments' => [
                    ['name' => 'Computer Engineering', 'abbreviation' => 'CPE'],
                    ['name' => 'Electrical (Power Systems) Engineering', 'abbreviation' => 'EPE'],
                    ['name' => 'Electrical and Electronic Engineering', 'abbreviation' => 'EEE'],
                    ['name' => 'Electronics Engineering', 'abbreviation' => 'ECE'],
                    ['name' => 'Mechatronics Engineering', 'abbreviation' => 'MCE'],
                    ['name' => 'Telecommunications Engineering', 'abbreviation' => 'TCE'],
                ],
            ],
            [
                'name' => 'Environmental Science',
                'abbreviation' => 'SOES',
                'departments' => [
                    ['name' => 'Architecture', 'abbreviation' => 'ARC'],
                    ['name' => 'Building Technology', 'abbreviation' => 'BLT'],
                    ['name' => 'Environmental Management', 'abbreviation' => 'ENM'],
                    ['name' => 'Environmental Management and Evaluation', 'abbreviation' => 'EME'],
                    ['name' => 'Quantity Surveying', 'abbreviation' => 'QSV'],
                    ['name' => 'Surveying and Geoinformatics', 'abbreviation' => 'SGI'],
                    ['name' => 'Urban and Regional Planning', 'abbreviation' => 'URP'],
                ],
            ],
            [
                'name' => 'Health Technology',
                'abbreviation' => 'SOHT',
                'departments' => [
                    ['name' => 'Dental Technology', 'abbreviation' => 'DNT'],
                    ['name' => 'Environmental Health Science', 'abbreviation' => 'EHS'],
                    ['name' => 'Optometry', 'abbreviation' => 'OPT'],
                    ['name' => 'Prosthetics and Orthotics', 'abbreviation' => 'PAO'],
                    ['name' => 'Public Health Technology', 'abbreviation' => 'PHT'],
                ],
            ],
            [
                'name' => 'Information and Communication Technology',
                'abbreviation' => 'SICT',
                'departments' => [
                    ['name' => 'Computer Science', 'abbreviation' => 'CSC'],
                    ['name' => 'Cyber Security', 'abbreviation' => 'CYS'],
                    ['name' => 'Information Technology', 'abbreviation' => 'IFT'],
                    ['name' => 'Software Engineering', 'abbreviation' => 'SWE'],
                ],
            ],
            [
                'name' => 'Logistics and Innovation Technology',
                'abbreviation' => 'SLIT',
                'departments' => [
                    ['name' => 'Entrepreneurship and Innovation', 'abbreviation' => 'ENI'],
                    ['name' => 'Logistics and Transport Technology', 'abbreviation' => 'LTT'],
                    ['name' => 'Maritime Technology and Logistics', 'abbreviation' => 'MTL'],
                    ['name' => 'Project Management Technology', 'abbreviation' => 'PMT'],
                    ['name' => 'Supply Chain Management', 'abbreviation' => 'SCM'],
                ],
            ],
            [
                'name' => 'Physical Science',
                'abbreviation' => 'SOPS',
                'departments' => [
                    ['name' => 'Chemistry', 'abbreviation' => 'CHM'],
                    ['name' => 'Geology', 'abbreviation' => 'GLY'],
                    ['name' => 'Mathematics', 'abbreviation' => 'MTH'],
                    ['name' => 'Physics', 'abbreviation' => 'PHY'],
                    ['name' => 'Science Laboratory Technology', 'abbreviation' => 'SLT'],
                    ['name' => 'Statistics', 'abbreviation' => 'STA'],
                ],
            ],
            [
                'name' => 'Postgraduate Studies',
                'abbreviation' => 'SPGS',
                'departments' => [
                    ['name' => 'Postgraduate School', 'abbreviation' => 'PGS'],
                ],
            ],
            [
                'name' => 'General Studies',
                'abbreviation' => 'DGS',
                'departments' => [
                    ['name' => 'General Studies', 'abbreviation' => 'GST'],
                ],
            ],
        ];
    }
}
