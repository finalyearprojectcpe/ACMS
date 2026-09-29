<?php

namespace App\Models;

use App\StudentStatus;
use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'advisor_profile_id', 'student_set_id', 'registration_number', 'status'])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    public static function registrationNumberForRoute(string $registrationNumber): string
    {
        return str_replace('/', '~', str_replace('~', '~~', $registrationNumber));
    }

    public static function registrationNumberFromRoute(string $segment): string
    {
        return preg_replace_callback('/~~|~/', fn (array $match): string => $match[0] === '~~' ? '~' : '/', $segment);
    }

    public function routeRegistrationNumber(): string
    {
        return self::registrationNumberForRoute($this->registration_number);
    }

    /**
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<StudentSet, $this>
     */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
    }

    /**
     * @return HasMany<StudentDueAssignment, $this>
     */
    public function dueAssignments(): HasMany
    {
        return $this->hasMany(StudentDueAssignment::class);
    }

    /**
     * @return HasMany<DuePayment, $this>
     */
    public function duePayments(): HasMany
    {
        return $this->hasMany(DuePayment::class);
    }

    /**
     * @return HasMany<CourseRegistration, $this>
     */
    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
        ];
    }
}
