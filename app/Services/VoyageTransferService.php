<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageWebserviceStatus;
use App\Models\WebserviceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageTransferService
{
    public const TRANSMITTED_MESSAGE = 'No se puede transferir el viaje: registra un envío a Aduana/Webservices realizado o en curso.';

    public function canTransfer(User $user, Voyage $voyage): bool
    {
        $company = $user->getUserCompany();

        if (
            !$user->can('voyages.transfer')
            || !$company
            || !$company->active
            || (int) $company->id !== (int) $voyage->company_id
        ) {
            return false;
        }

        if ($user->hasRole('company-admin')) {
            return true;
        }

        /*
         * El permiso operativo "Transferir Cargas" (operators.can_transfer)
         * debe habilitar la operación real de transferencia del viaje.
         * Los operadores mantienen además su regla normal de alcance:
         * sólo pueden operar viajes creados por ellos dentro de una empresa
         * con rol Cargas.
         */
        if (
            $user->hasRole('user')
            && $user->isOperator()
            && $user->userable
            && $user->userable->active
            && $user->userable->canTransferBetweenCompanies()
            && $company->hasRole('Cargas')
            && (int) $voyage->created_by_user_id === (int) $user->id
        ) {
            return true;
        }

        return false;
    }

    public function hasTransmission(Voyage $voyage): bool
    {
        foreach (['argentina', 'paraguay'] as $country) {
            if ($voyage->getAttribute($country.'_sent_at')
                || $voyage->getAttribute($country.'_voyage_id')
                || in_array($voyage->getAttribute($country.'_status'), ['sending', 'sent', 'approved', 'rejected'], true)) {
                return true;
            }
        }

        // No filtrar por empresa: el historial permanece en la empresa emisora.
        $transactions = WebserviceTransaction::where(function ($query) use ($voyage) {
            $query->where('voyage_id', $voyage->id)
                ->orWhereIn('shipment_id', Shipment::select('id')->where('voyage_id', $voyage->id));
        });
        if ($transactions->where(function ($query) {
            $query->whereNotNull('sent_at')
                ->orWhereIn('status', ['sending', 'sent', 'success'])
                ->orWhere(function ($query) {
                    $query->whereNotNull('response_xml')->where('response_xml', '!=', '');
                })
                ->orWhere(function ($query) {
                    $query->whereNotNull('confirmation_number')->where('confirmation_number', '!=', '');
                });
        })->exists()) {
            return true;
        }

        // response_at/error por sí solos también pueden provenir de validación local.
        return VoyageWebserviceStatus::where('voyage_id', $voyage->id)
            ->where(function ($query) {
                $query->whereNotNull('first_sent_at')->orWhereNotNull('last_sent_at')
                    ->orWhereNotNull('approved_at')
                    ->orWhereIn('status', ['sending', 'sent', 'approved', 'rejected'])
                    ->orWhere(function ($query) {
                        $query->whereNotNull('confirmation_number')->where('confirmation_number', '!=', '');
                    })
                    ->orWhere(function ($query) {
                        $query->whereNotNull('external_voyage_number')->where('external_voyage_number', '!=', '');
                    });
            })->exists();
    }

    public function transfer(User $user, Voyage $voyage, int $destinationId): void
    {
        DB::transaction(function () use ($user, $voyage, $destinationId) {
            $current = Voyage::whereKey($voyage->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canTransfer($user, $current), 403, 'No tiene permisos para transferir este viaje.');

            $destination = Company::whereKey($destinationId)->lockForUpdate()->first();
            if (!$destination || !$destination->active || (int) $current->company_id === $destinationId) {
                throw ValidationException::withMessages([
                    'destination_company_id' => 'Seleccione otra empresa activa registrada.',
                ]);
            }
            if ($this->hasTransmission($current)) {
                throw ValidationException::withMessages(['transfer' => self::TRANSMITTED_MESSAGE]);
            }

            // La auditoría y el viaje deben usar la misma conexión transaccional.
            $connection = DB::connection();
            $auditConnection = config('audit.drivers.database.connection');
            if ($auditConnection && $auditConnection !== $connection->getName()) {
                throw new \RuntimeException('La auditoría de transferencia requiere la misma conexión de base de datos.');
            }
            $now = now();
            $prefix = config('audit.user.morph_prefix', 'user');
            $connection->table(config('audit.drivers.database.table', 'audits'))->insert([
                $prefix.'_type' => $user->getMorphClass(),
                $prefix.'_id' => $user->id,
                'event' => 'transferred',
                'auditable_type' => $current->getMorphClass(),
                'auditable_id' => $current->id,
                'old_values' => json_encode(['company_id' => (int) $current->company_id], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['company_id' => $destinationId], JSON_THROW_ON_ERROR),
                'tags' => 'voyage_transfer',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Los hijos conservan sus IDs y relaciones. No se modifica ningún historial WS.
            $current->company_id = $destinationId;
            $current->saveOrFail();
        });
    }
}
