<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class User extends Authenticatable implements Auditable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use AuditableTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'userable_type',
        'userable_id',
        'last_access',
        'active',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_access' => 'datetime',
        'active' => 'boolean',
        'password' => 'hashed',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Polymorphic relationship - can be Company or Operator
     */
    public function userable()
    {
        return $this->morphTo();
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Check if user is company admin
     */
    public function isCompanyAdmin(): bool
    {
        return $this->hasRole('company-admin');
    }

    /**
     * Check if user is internal operator
     */
    public function isInternalOperator(): bool
    {
        return $this->hasRole('internal-operator');
    }

    /**
     * Check if user is external operator
     */
    public function isExternalOperator(): bool
    {
        return $this->hasRole('external-operator');
    }

    /**
     * Get the company associated with the user
     */
    public function getCompanyAttribute()
    {
        if ($this->userable_type === 'App\Models\Company') {
            return $this->userable;
        }

        if ($this->userable_type === 'App\Models\Operator' && $this->userable->company) {
            return $this->userable->company;
        }

        return null;
    }

    /**
     * Check if user belongs to a specific company
     */
    public function belongsToCompany($companyId): bool
    {
        $company = $this->company;
        return $company && $company->id == $companyId;
    }

    /**
     * Update last access timestamp
     */
    public function updateLastAccess()
    {
        $this->update(['last_access' => now()]);
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for users from a specific company
     */
    public function scopeFromCompany($query, $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('userable_type', 'App\Models\Company')
            ->where('userable_id', $companyId)
            ->orWhereHas('userable', function ($subQuery) use ($companyId) {
                $subQuery->where('company_id', $companyId);
            });
        });
    }

    /**
     * Get full name for display
     */
    public function getFullNameAttribute(): string
    {
        if ($this->userable_type === 'App\Models\Operator') {
            return trim($this->userable->first_name . ' ' . $this->userable->last_name);
        }

        if ($this->userable_type === 'App\Models\Company') {
            return $this->userable->business_name;
        }

        return $this->name;
    }

    /**
     * Get user type description
     */
    public function getUserTypeAttribute(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Administrator';
        }

        if ($this->isCompanyAdmin()) {
            return 'Company Administrator';
        }

        if ($this->isInternalOperator()) {
            return 'Internal Operator';
        }

        if ($this->isExternalOperator()) {
            return 'External Operator';
        }

        return 'User';
    }

    /**
     * Audit configuration
     */
    protected $auditInclude = [
        'email',
        'userable_type',
        'userable_id',
        'active',
    ];

    public function getDescriptionForEvent(string $eventName): string
    {
        return "User {$this->email} was {$eventName}";
    }
}
