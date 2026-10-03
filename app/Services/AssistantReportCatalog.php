<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\StudentProfile;

class AssistantReportCatalog
{
    /** @return array<string, array{description: string, fields: array<string, string>}> */
    public function datasets(AdvisorProfile|StudentProfile $owner): array
    {
        $common = [
            'sets' => [
                'description' => 'One row per student set. A student can see only their own set.',
                'fields' => [
                    'set' => 'text: entry session and department',
                    'department' => 'text',
                    'entry_session' => 'text: YYYY/YYYY',
                    'current_level' => 'text or null',
                    'current_semester' => 'text or null',
                    'student_count' => 'number',
                    'invites_open' => 'boolean',
                ],
            ],
            'students' => [
                'description' => 'One row per student in the adviser roster, or the signed-in student only.',
                'fields' => [
                    'name' => 'text: official name',
                    'registration_number' => 'text',
                    'set' => 'text',
                    'status' => 'text: account status',
                    'unpaid_dues_count' => 'number: published dues not paid or waived',
                ],
            ],
            'registrations' => [
                'description' => 'One row per student course assignment for a semester.',
                'fields' => [
                    'student' => 'text',
                    'registration_number' => 'text',
                    'set' => 'text',
                    'session' => 'text: YYYY/YYYY',
                    'semester' => 'text: first or second',
                    'status' => 'text: draft or assigned',
                    'course_count' => 'number',
                    'total_units' => 'number',
                ],
            ],
            'results' => [
                'description' => 'One row per assigned course result. Students see only published, unlocked results.',
                'fields' => [
                    'student' => 'text',
                    'registration_number' => 'text',
                    'set' => 'text',
                    'session' => 'text: YYYY/YYYY',
                    'semester' => 'text: first or second',
                    'course_code' => 'text',
                    'course_title' => 'text',
                    'grade' => 'text or null: A through F',
                    'missing_script' => 'boolean',
                    'published' => 'boolean',
                    'elective' => 'boolean',
                    'assignment_origin' => 'text: normal or carryover',
                    'units' => 'number',
                ],
            ],
            'due_assignments' => [
                'description' => 'One row per due assigned to a student. Amount is the individual assigned amount.',
                'fields' => [
                    'student' => 'text',
                    'registration_number' => 'text',
                    'set' => 'text',
                    'due' => 'text: due title',
                    'session' => 'text: YYYY/YYYY',
                    'semester' => 'text or null: null means the whole session',
                    'status' => 'text: unpaid, paid, or waived',
                    'amount_kobo' => 'number: divide by 100 for NGN',
                    'paid_at' => 'date or null: ISO 8601',
                ],
            ],
            'payments' => [
                'description' => 'One row per payment attempt; filter status=success for completed payments.',
                'fields' => [
                    'student' => 'text',
                    'registration_number' => 'text',
                    'set' => 'text',
                    'due' => 'text',
                    'status' => 'text: initialized, success, failed, abandoned, or reversed',
                    'amount_kobo' => 'number: divide by 100 for NGN',
                    'paid_at' => 'date or null: ISO 8601',
                    'reference' => 'text: payment reference',
                ],
            ],
        ];

        if ($owner instanceof StudentProfile) {
            return $common;
        }

        $common['students']['fields'] += [
            'cgpa' => 'number or null: cumulative GPA from completed published periods',
            'latest_gpa' => 'number or null: GPA for the latest completed published semester',
            'standing' => 'text or null: classification derived from CGPA',
        ];
        $common['courses'] = [
            'description' => 'One row per course in a set catalog.',
            'fields' => [
                'set' => 'text', 'code' => 'text', 'title' => 'text',
                'units' => 'number', 'elective' => 'boolean',
            ],
        ];
        $common['offerings'] = [
            'description' => 'One row per course scheduled in a set semester.',
            'fields' => [
                'set' => 'text', 'session' => 'text: YYYY/YYYY',
                'semester' => 'text: first or second', 'code' => 'text',
                'title' => 'text', 'units' => 'number', 'elective' => 'boolean',
                'retake_only' => 'boolean', 'results_published' => 'boolean',
            ],
        ];
        $common['dues'] = [
            'description' => 'One row per due defined for a set, independent of student assignments.',
            'fields' => [
                'set' => 'text', 'title' => 'text', 'session' => 'text: YYYY/YYYY',
                'semester' => 'text or null: null means the whole session',
                'status' => 'text: draft, published, or archived',
                'amount_kobo' => 'number: divide by 100 for NGN',
            ],
        ];
        $common['join_requests'] = [
            'description' => 'One row per student request to join a set.',
            'fields' => [
                'student' => 'text', 'registration_number' => 'text',
                'set' => 'text', 'status' => 'text: pending, approved, or rejected',
                'submitted_at' => 'date: ISO 8601',
            ],
        ];

        return $common;
    }
}
