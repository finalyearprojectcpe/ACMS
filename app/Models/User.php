<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\AccountStatus;
use App\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['school_id', 'name', 'email', 'profile_photo_path', 'password', 'access_code', 'access_code_used_at', 'activated_at', 'disabled_at', 'status', 'account_type'])]
#[Hidden(['password', 'access_code', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'status' => AccountStatus::class,
            'access_code_used_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * @return HasOne<StaffProfile, $this>
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function profilePhotoUrl(): ?string
    {
        if (! is_string($this->profile_photo_path) || $this->profile_photo_path === '') {
            return null;
        }

        return Storage::disk(self::profilePhotoDisk())->url($this->profile_photo_path);
    }

    public static function profilePhotoDisk(): string
    {
        $disk = config('filesystems.profile_photo_disk', 'public');

        return is_string($disk) && $disk !== '' ? $disk : 'public';
    }
}
