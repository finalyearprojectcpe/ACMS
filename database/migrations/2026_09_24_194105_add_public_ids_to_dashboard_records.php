<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->ulid('public_id')->nullable()->unique();
            });

            DB::table($tableName)->select('id')->orderBy('id')->chunkById(500, function ($rows) use ($tableName): void {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update(['public_id' => strtolower((string) Str::ulid())]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables()) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('public_id');
            });
        }
    }

    /** @return list<string> */
    private function tables(): array
    {
        return [
            'users',
            'student_sets',
            'academic_sessions',
            'academic_session_semesters',
            'course_offerings',
            'assistant_conversations',
            'dues',
            'student_due_assignments',
            'due_payments',
        ];
    }
};
