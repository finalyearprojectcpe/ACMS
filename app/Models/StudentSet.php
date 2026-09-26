<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\StudentSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'advisor_profile_id',
    'department_name',
    'department_slug',
    'entry_start_year',
    'entry_end_year',
    'invite_token',
    'student_signup_enabled',
    'current_academic_year_id',
])]
#[Hidden(['invite_token'])]
class StudentSet extends Model
{
    /** @use HasFactory<StudentSetFactory> */
    use HasFactory;

    use HasPublicId;

    /** @return BelongsTo<AdvisorProfile, $this> */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /** @return HasMany<AcademicSession, $this> */
    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    /** @return HasMany<StudentProfile, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function currentAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'current_academic_year_id');
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function label(): string
    {
        return "{$this->entry_start_year}/{$this->entry_end_year} {$this->department_name}";
    }

    /** @return array{departmentSlug: string, entrySession: string} */
    public function routeParameters(): array
    {
        return [
            'departmentSlug' => $this->department_slug,
            'entrySession' => "{$this->entry_start_year}{$this->entry_end_year}",
        ];
    }

    public function filterKey(): string
    {
        return "{$this->department_slug}-{$this->entry_start_year}{$this->entry_end_year}";
    }

    public function signupUrl(): string
    {
        return route('student-invitations.show', ['studentSet' => $this->invite_token]);
    }

    public function rotateInviteToken(): void
    {
        $this->forceFill(['invite_token' => Str::random(48)])->save();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_signup_enabled' => 'boolean',
            'entry_start_year' => 'integer',
            'entry_end_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StudentSet $studentSet): void {
            $studentSet->invite_token ??= Str::random(48);
        });
    }
}
