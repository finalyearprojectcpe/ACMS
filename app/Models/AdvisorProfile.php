<?php

namespace App\Models;

use Database\Factories\AdvisorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
])]
class AdvisorProfile extends Model
{
    /** @use HasFactory<AdvisorProfileFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudentSet, $this> */
    public function studentSets(): HasMany
    {
        return $this->hasMany(StudentSet::class);
    }

    /** @return HasMany<StudentProfile, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /** @return HasOne<PaymentAccount, $this> */
    public function paymentAccount(): HasOne
    {
        return $this->hasOne(PaymentAccount::class);
    }

    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }
}
