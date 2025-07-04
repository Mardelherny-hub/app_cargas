<?php
// app/Models/Operator.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Operator extends Model implements Auditable
{
    use HasFactory;
    use AuditableTrait;

    protected $fillable = [
        'first_name',
        'last_name',
        'document_number',
        'phone',
        'position',
        'company_id',
        'type',
        'special_permissions',
        'can_import',
        'can_export',
        'can_transfer',
        'active',
        'created_date',
        'last_access',
    ];

    protected $casts = [
        'special_permissions' => 'array',
        'can_import' => 'boolean',
        'can_export' => 'boolean',
        'can_transfer' => 'boolean',
        'active' => 'boolean',
        'created_date' => 'datetime',
        'last_access' => 'datetime',
    ];

    /**
     * Inverse polymorphic relationship with User
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Company that the operator belongs to (only for external operators)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if operator is internal
     */
    public function isInternal(): bool
    {
        return $this->type === 'internal';
    }

    /**
     * Check if operator is external
     */
    public function isExternal(): bool
    {
        return $this->type === 'external';
    }

    /**
     * Check if operator has a special permission
     */
    public function hasSpecialPermission($permission): bool
    {
        $permissions = $this->special_permissions ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Add a special permission
     */
    public function addSpecialPermission($permission): void
    {
        $permissions = $this->special_permissions ?? [];

        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['special_permissions' => $permissions]);
        }
    }

    /**
     * Remove a special permission
     */
    public function removeSpecialPermission($permission): void
    {
        $permissions = $this->special_permissions ?? [];
        $permissions = array_diff($permissions, [$permission]);

        $this->update(['special_permissions' => array_values($permissions)]);
    }

    /**
     * Check if operator can perform transfers between companies
     */
    public function canTransfer(): bool
    {
        return $this->can_transfer && $this->active;
    }

    /**
     * Check if operator can import data
     */
    public function canImport(): bool
    {
        return $this->can_import && $this->active;
    }

    /**
     * Check if operator can export data
     */
    public function canExport(): bool
    {
        return $this->can_export && $this->active;
    }

    /**
     * Scope for active operators
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for internal operators
     */
    public function scopeInternal($query)
    {
        return $query->where('type', 'internal');
    }

    /**
     * Scope for external operators
     */
    public function scopeExternal($query)
    {
        return $query->where('type', 'external');
    }

    /**
     * Scope for operators from a specific company
     */
    public function scopeFromCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Accessor for full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Accessor for formatted type
     */
    public function getTypeFormattedAttribute(): string
    {
        return match($this->type) {
            'internal' => 'Internal Operator',
            'external' => 'External Operator',
            default => 'Operator'
        };
    }

    /**
     * Accessor for company or "Internal"
     */
    public function getCompanyOrInternalAttribute(): string
    {
        if ($this->isInternal()) {
            return 'Internal';
        }

        return $this->company?->display_name ?? 'No company';
    }

    /**
     * Accessor for permissions summary
     */
    public function getPermissionsSummaryAttribute(): array
    {
        $permissions = [];

        if ($this->can_import) $permissions[] = 'import';
        if ($this->can_export) $permissions[] = 'export';
        if ($this->can_transfer) $permissions[] = 'transfer';

        return array_merge($permissions, $this->special_permissions ?? []);
    }

    /**
     * Audit configuration
     */
    protected $auditInclude = [
        'first_name',
        'last_name',
        'document_number',
        'type',
        'company_id',
        'can_import',
        'can_export',
        'can_transfer',
        'active',
    ];

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Operator {$this->full_name} ({$this->type}) was {$eventName}";
    }
}
