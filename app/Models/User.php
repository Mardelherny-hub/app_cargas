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
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->hasRole('user');
    }

    /**
     * Check if user is an operator (based on userable_type)
     */
    public function isOperator(): bool
    {
        return $this->userable_type === 'App\Models\Operator';
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

        if ($this->isUser()) {
            // Si es un operador, especificarlo
            if ($this->isOperator()) {
                return 'Operator';
            }
            return 'User';
        }

        return 'User';
    }

    /**
     * Get company roles for this user
     */
    public function getCompanyRoles(): array
    {
        $company = $this->company;
        return $company ? ($company->company_roles ?? []) : [];
    }

    /**
     * Get operator permissions for this user
     */
    public function getOperatorPermissions(): array
    {
        if (!$this->isOperator() || !$this->userable) {
            return [
                'can_import' => false,
                'can_export' => false,
                'can_transfer' => false,
            ];
        }

        return [
            'can_import' => $this->userable->can_import ?? false,
            'can_export' => $this->userable->can_export ?? false,
            'can_transfer' => $this->userable->can_transfer ?? false,
        ];
    }

    /**
     * Check if user can perform a specific action
     */
    public function canPerform(string $action): bool
    {
        // Super admin can do everything
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Company admin can do everything in their company
        if ($this->isCompanyAdmin()) {
            return true;
        }

        // Regular users have limited permissions
        if ($this->isUser()) {
            $companyRoles = $this->getCompanyRoles();
            $operatorPermissions = $this->getOperatorPermissions();

            switch ($action) {
                case 'import':
                    return $operatorPermissions['can_import'];
                case 'export':
                    return $operatorPermissions['can_export'];
                case 'transfer':
                    return $operatorPermissions['can_transfer'];
                case 'view_cargas':
                    return in_array('Cargas', $companyRoles);
                case 'view_deconsolidation':
                    return in_array('Desconsolidador', $companyRoles);
                case 'view_transfers':
                    return in_array('Transbordos', $companyRoles);
                case 'view_reports':
                    return true; // All users can view reports
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Get user information for display
     */
    public function getUserInfo(): array
    {
        $company = $this->company;

        $info = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'type' => $this->getUserTypeAttribute(),
            'active' => $this->active,
            'last_access' => $this->last_access,
            'roles' => $this->roles->pluck('name')->toArray(),
        ];

        // Company information
        if ($company) {
            $info['company'] = [
                'id' => $company->id,
                'name' => $company->business_name,
                'roles' => $company->company_roles ?? [],
                'active' => $company->active,
            ];
        }

        // Operator information
        if ($this->isOperator() && $this->userable) {
            $info['operator'] = [
                'id' => $this->userable->id,
                'full_name' => $this->userable->first_name . ' ' . $this->userable->last_name,
                'can_import' => $this->userable->can_import ?? false,
                'can_export' => $this->userable->can_export ?? false,
                'can_transfer' => $this->userable->can_transfer ?? false,
                'active' => $this->userable->active,
            ];
        }

        return $info;
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
