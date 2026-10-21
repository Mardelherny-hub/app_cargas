<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expediente ATA-DESC propio de la empresa desconsolidadora.
 *
 * No representa el viaje de transporte ni necesita una embarcación:
 * conserva únicamente las referencias externas que recibe el operador.
 */
class DeconsolidationOperation extends Model
{
    protected $table = 'deconsolidation_operations';

    protected $fillable = [
        'company_id',
        'argentina_voyage_id',
        'master_loading_port_code',
        'master_bill_number',
        'arrival_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'arrival_date' => 'date',
    ];

    /**
     * Empresa titular del expediente; no es la transportista del viaje.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Conocimientos hijos existentes, sin crear Shipments artificiales.
     */
    public function billsOfLading(): HasMany
    {
        return $this->hasMany(BillOfLading::class, 'deconsolidation_operation_id');
    }

    /**
     * Aplicar en toda entrada HTTP para impedir acceder a expedientes
     * de otra empresa, aunque se conozca su identificador interno.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
