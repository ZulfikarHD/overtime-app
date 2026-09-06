<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property UserRole|string $role
 * @property string|null $npk
 * @property int|null $department_id
 * @property int|null $section_id
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 * @property-read Section|null $section
 * @property-read Employee|null $employee
 */
#[Fillable(['name', 'email', 'password', 'role', 'npk', 'department_id', 'section_id', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'npk',
        'department_id',
        'section_id',
        'is_active',
    ];

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'npk', 'npk');
    }

    /**
     * Check if the user is an Administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * Check if the user is a Manager.
     */
    public function isManager(): bool
    {
        return $this->hasRole(UserRole::Manager);
    }

    /**
     * Check if the user is a Team Leader.
     */
    public function isTeamLeader(): bool
    {
        return $this->hasRole(UserRole::TeamLeader);
    }

    /**
     * Check if the user is a standard User / Operator.
     */
    public function isUser(): bool
    {
        return $this->hasRole(UserRole::User);
    }

    /**
     * Check if the user has one of the specified roles.
     *
     * @param  UserRole|string|array<UserRole|string>  $roles
     */
    public function hasRole(UserRole|string|array $roles): bool
    {
        $roleValue = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;
        $rolesArray = is_array($roles) ? $roles : [$roles];

        foreach ($rolesArray as $checkRole) {
            $checkValue = $checkRole instanceof UserRole ? $checkRole->value : (string) $checkRole;
            if ($roleValue === $checkValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has access to a specific section's overtime data.
     */
    public function canAccessSection(int|Section $section): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $sectionModel = $section instanceof Section ? $section : Section::find($section);

        if (! $sectionModel) {
            return false;
        }

        if ($this->isManager()) {
            return $this->department_id !== null && $sectionModel->department_id === $this->department_id;
        }

        if ($this->isTeamLeader()) {
            return $this->section_id !== null && $sectionModel->id === $this->section_id;
        }

        return false;
    }

    /**
     * Determine if the user has access to a specific department's overtime data.
     */
    public function canAccessDepartment(int|Department $department): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $departmentId = $department instanceof Department ? $department->id : $department;

        if ($this->isManager()) {
            return $this->department_id !== null && $this->department_id === $departmentId;
        }

        return false;
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
