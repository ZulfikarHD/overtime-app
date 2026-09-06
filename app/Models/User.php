<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OvertimeSubmission, $this>
     */
    public function overtimeSubmissions(): HasMany
    {
        return $this->hasMany(OvertimeSubmission::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<OvertimeItem, $this>
     */
    public function reviewedOvertimeItems(): HasMany
    {
        return $this->hasMany(OvertimeItem::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<SpklDocument, $this>
     */
    public function attachedSpklDocuments(): HasMany
    {
        return $this->hasMany(SpklDocument::class, 'attached_by_user_id');
    }

    /**
     * @return HasMany<OvertimeItemAudit, $this>
     */
    public function overtimeItemAudits(): HasMany
    {
        return $this->hasMany(OvertimeItemAudit::class, 'actor_user_id');
    }

    /**
     * @return HasMany<MlAnomalyLog, $this>
     */
    public function dismissedAnomalyLogs(): HasMany
    {
        return $this->hasMany(MlAnomalyLog::class, 'dismissed_by_user_id');
    }
}
