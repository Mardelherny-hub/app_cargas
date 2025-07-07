<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Operator extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
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
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * Relación con la empresa (para operadores externos).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación polimórfica inversa con User.
     * Un operador puede tener un usuario asociado.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Scope para operadores activos.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para operadores internos.
     */
    public function scopeInternal($query)
    {
        return $query->where('type', 'internal');
    }

    /**
     * Scope para operadores externos.
     */
    public function scopeExternal($query)
    {
        return $query->where('type', 'external');
    }

    /**
     * Scope para operadores por empresa.
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para operadores con permisos de importación.
     */
    public function scopeCanImport($query)
    {
        return $query->where('can_import', true);
    }

    /**
     * Scope para operadores con permisos de exportación.
     */
    public function scopeCanExport($query)
    {
        return $query->where('can_export', true);
    }

    /**
     * Scope para operadores con permisos de transferencia.
     */
    public function scopeCanTransfer($query)
    {
        return $query->where('can_transfer', true);
    }

    /**
     * Accessor para obtener el nombre completo.
     */
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Accessor para obtener las iniciales.
     */
    public function getInitialsAttribute()
    {
        return substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1);
    }

    /**
     * Accessor para verificar si es operador interno.
     */
    public function getIsInternalAttribute()
    {
        return $this->type === 'internal';
    }

    /**
     * Accessor para verificar si es operador externo.
     */
    public function getIsExternalAttribute()
    {
        return $this->type === 'external';
    }

    /**
     * Accessor para verificar si tiene empresa asociada.
     */
    public function getHasCompanyAttribute()
    {
        return !empty($this->company_id);
    }

    /**
     * Accessor para obtener el tipo de operador en español.
     */
    public function getTypeNameAttribute()
    {
        return $this->type === 'internal' ? 'Interno' : 'Externo';
    }

    /**
     * Verificar si el operador tiene un permiso especial.
     */
    public function hasSpecialPermission($permission)
    {
        return in_array($permission, $this->special_permissions ?? []);
    }

    /**
     * Agregar un permiso especial.
     */
    public function addSpecialPermission($permission)
    {
        $permissions = $this->special_permissions ?? [];

        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->special_permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Remover un permiso especial.
     */
    public function removeSpecialPermission($permission)
    {
        $permissions = $this->special_permissions ?? [];

        if (($key = array_search($permission, $permissions)) !== false) {
            unset($permissions[$key]);
            $this->special_permissions = array_values($permissions);
            $this->save();
        }
    }

    /**
     * Obtener todos los permisos del operador.
     */
    public function getAllPermissions()
    {
        $permissions = [];

        if ($this->can_import) {
            $permissions[] = 'import';
        }

        if ($this->can_export) {
            $permissions[] = 'export';
        }

        if ($this->can_transfer) {
            $permissions[] = 'transfer';
        }

        return array_merge($permissions, $this->special_permissions ?? []);
    }

    /**
     * Verificar si el operador tiene todos los permisos básicos.
     */
    public function hasAllBasicPermissions()
    {
        return $this->can_import && $this->can_export && $this->can_transfer;
    }

    /**
     * Verificar si el operador puede acceder a una empresa específica.
     */
    public function canAccessCompany($companyId)
    {
        // Operadores internos pueden acceder a cualquier empresa
        if ($this->is_internal) {
            return true;
        }

        // Operadores externos solo pueden acceder a su propia empresa
        return $this->company_id == $companyId;
    }

    /**
     * Actualizar último acceso.
     */
    public function updateLastAccess()
    {
        $this->update(['last_access' => now()]);
    }

    /**
     * Obtener estadísticas básicas del operador.
     */
    public function getStats()
    {
        return [
            'total_permissions' => count($this->getAllPermissions()),
            'has_company' => $this->has_company,
            'is_active' => $this->active,
            'last_access' => $this->last_access,
            'days_since_last_access' => $this->last_access ? now()->diffInDays($this->last_access) : null,
        ];
    }

    /**
     * Verificar si el operador ha accedido recientemente.
     */
    public function hasRecentAccess($days = 30)
    {
        return $this->last_access && $this->last_access->diffInDays(now()) <= $days;
    }

    /**
     * Crear un resumen del operador.
     */
    public function getSummary()
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'type' => $this->type_name,
            'company' => $this->company?->business_name,
            'permissions' => $this->getAllPermissions(),
            'active' => $this->active,
            'last_access' => $this->last_access?->format('d/m/Y H:i'),
        ];
    }
}
