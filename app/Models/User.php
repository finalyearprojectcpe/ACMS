<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\AccountStatus;
use App\AccountType;
use App\Honorific;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['title', 'name', 'email', 'profile_photo_path', 'password', 'disabled_at', 'status', 'account_type'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'title' => Honorific::class,
            'status' => AccountStatus::class,
            'disabled_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * @return HasOne<AdvisorProfile, $this>
     */
    public function advisorProfile(): HasOne
    {
        return $this->hasOne(AdvisorProfile::class);
    }

    public function isAdviser(): bool
    {
        return $this->account_type === AccountType::Adviser
            && $this->advisorProfile !== null;
    }

    public function isStudent(): bool
    {
        return $this->account_type === AccountType::Student
            && $this->studentProfile !== null;
    }

    public function displayName(): string
    {
        return trim(implode(' ', array_filter([
            $this->title?->value,
            $this->name,
        ])));
    }

    public function profilePhotoUrl(): ?string
    {
        if (! is_string($this->profile_photo_path) || $this->profile_photo_path === '') {
            return null;
        }

        return route('profile-photos.show', ['user' => $this]);
    }

    public static function profilePhotoDisk(): string
    {
        $disk = config('filesystems.profile_photo_disk', 'public');

        return is_string($disk) && $disk !== '' ? $disk : 'public';
    }
}
