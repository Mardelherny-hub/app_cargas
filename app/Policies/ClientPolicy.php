<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Client;
use App\Models\ClientCompanyRelation;
use App\Traits\UserHelper;

/**
 * FASE 1 - MÓDULO EMPRESAS Y CLIENTES
 *
 * Policy para autorización de gestión de clientes
 * Implementa los criterios de aceptación:
 * - Solo empresas autorizadas pueden editar clientes
 * - Un cliente puede tener múltiples relaciones con empresas
 * - Control de acceso por empresa implementado
 */
class ClientPolicy
{
    use UserHelper;

    /**
     * Determine if the user can view any clients.
     */
    public function viewAny(User $user): bool
    {
        // Super admin ve todos los clientes
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin y users pueden ver clientes si tienen empresa
        if ($user->hasRole(['company-admin', 'user'])) {
            return $this->getUserCompany() !== null;
        }

        return false;
    }

    /**
     * Determine if the user can view the client.
     */
    public function view(User $user, Client $client): bool
    {
        // Super admin ve todos los clientes
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin y users pueden ver todos los clientes activos de la base compartida
        if ($user->hasRole(['company-admin', 'user'])) {
            $userCompany = $this->getUserCompany();
            if (!$userCompany) {
                return false;
            }
            // ✅ NUEVA LÓGICA: Base compartida, solo verificar que esté activo
            return $client->status === 'active' && $client->verified_at !== null;
        }

        return false;
    }

    /**
     * Determine if the user can create clients.
     */
    public function create(User $user): bool
    {
        // Super admin puede crear clientes
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin puede crear clientes para su empresa
        if ($user->hasRole('company-admin')) {
            return $this->getUserCompany() !== null;
        }

        // Users NO pueden crear clientes (solo company-admin)
        return false;
    }

    /**
     * Determine if the user can update the client.
     */
   /**
 * Determine if the user can update the client.
 * CORRECCIÓN: Adaptado para base compartida
 */
public function update(User $user, Client $client): bool
{
    // Super admin puede editar todos los clientes
    if ($user->hasRole('super-admin')) {
        return true;
    }

    // Company admin puede editar clientes activos de la base compartida
    if ($user->hasRole('company-admin')) {
        $userCompany = $this->getUserCompany();
        if (!$userCompany) {
            return false;
        }
        
        // En base compartida, cualquier company admin puede editar clientes activos
        return $client->status === 'active' && $client->verified_at !== null;
    }

    // Users NO pueden editar clientes
    return false;
}

/**
 * Determine if the user can delete the client.
 * CORRECCIÓN: Adaptado para base compartida
 */
public function delete(User $user, Client $client): bool
{
    // Super admin puede eliminar cualquier cliente
    if ($user->hasRole('super-admin')) {
        return true;
    }

    // Company admin puede eliminar solo si su empresa creó el cliente
    if ($user->hasRole('company-admin')) {
        $userCompany = $this->getUserCompany();
        if (!$userCompany) {
            return false;
        }
        
        return $client->created_by_company_id === $userCompany->id;
    }

    return false;
}

/**
 * Determine if the user can verify client tax_id.
 * CORRECCIÓN: Adaptado para base compartida
 */
public function verify(User $user, Client $client): bool
{
    // Super admin puede verificar cualquier cliente
    if ($user->hasRole('super-admin')) {
        return true;
    }

    // Company admin puede verificar clientes activos de la base compartida
    if ($user->hasRole('company-admin')) {
        $userCompany = $this->getUserCompany();
        if (!$userCompany) {
            return false;
        }
        
        // En base compartida, cualquier company admin puede verificar
        return $client->status === 'active';
    }

    return false;
}

/**
 * Determine if the user can use the client in operations.
 * CORRECCIÓN: Adaptado para base compartida
 */
public function use(User $user, Client $client): bool
{
    // Super admin puede usar cualquier cliente
    if ($user->hasRole('super-admin')) {
        return true;
    }

    // Company admin y users pueden usar todos los clientes activos verificados
    if ($user->hasRole(['company-admin', 'user'])) {
        $userCompany = $this->getUserCompany();
        if (!$userCompany) {
            return false;
        }
        
        // En base compartida, todos pueden usar clientes activos y verificados
        return $client->status === 'active' && $client->verified_at !== null;
    }

    return false;
}

/**
 * Determine if the user can transfer client to another company.
 * CORRECCIÓN: En base compartida, no hay transferencias
 */
public function transfer(User $user, Client $client): bool
{
    // En base compartida, solo super admin puede cambiar empresa creadora para auditoría
    return $user->hasRole('super-admin');
}

/**
 * MÉTODO OBSOLETO: Eliminar métodos relacionados con ClientCompanyRelation
 * Ya no se usan en base compartida
 */
// public function manageRelations() - ELIMINAR
// protected function hasCompanyClientRelation() - ELIMINAR  
// protected function canCompanyEditClient() - ELIMINAR
// protected function hasActiveCompanyClientRelation() - ELIMINAR
// public function getAccessibleClients() - ELIMINAR
// public function getEditableClients() - ELIMINAR

    /**
     * Determine if the user can restore the client.
     */
    public function restore(User $user, Client $client): bool
    {
        // Solo super admin puede restaurar clientes
        return $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can permanently delete the client.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        // Solo super admin puede eliminar permanentemente
        return $user->hasRole('super-admin');
    }

    /**
     * Determine if the user can manage client relationships.
     */
    public function manageRelations(User $user, Client $client): bool
    {
        // Super admin puede gestionar todas las relaciones
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Company admin puede gestionar relaciones de su empresa
        if ($user->hasRole('company-admin')) {
            $userCompany = $this->getUserCompany();

            if (!$userCompany) {
                return false;
            }

            // Puede gestionar si su empresa tiene relación con el cliente
            return $this->hasCompanyClientRelation($userCompany->id, $client->id);
        }

        return false;
    }

    

    // ===============================================
    // MÉTODOS AUXILIARES PARA RELACIONES
    // ===============================================

    /**
     * Verificar si existe relación entre empresa y cliente.
     */
    protected function hasCompanyClientRelation(int $companyId, int $clientId): bool
    {
        return ClientCompanyRelation::where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->exists();
    }

    /**
     * Verificar si la empresa puede editar el cliente.
     */
    protected function canCompanyEditClient(int $companyId, int $clientId): bool
    {
        return ClientCompanyRelation::where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('can_edit', true)
            ->where('active', true)
            ->exists();
    }

    /**
     * Verificar si existe relación activa entre empresa y cliente.
     */
    protected function hasActiveCompanyClientRelation(int $companyId, int $clientId): bool
    {
        return ClientCompanyRelation::where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('active', true)
            ->exists();
    }

    /**
     * Obtener clientes accesibles para el usuario.
     */
    public function getAccessibleClients(User $user)
    {
        // Super admin ve todos los clientes
        if ($user->hasRole('super-admin')) {
            return Client::query();
        }

        // Company admin y users ven solo clientes de su empresa
        if ($user->hasRole(['company-admin', 'user'])) {
            $userCompany = $this->getUserCompany();

            if (!$userCompany) {
                return Client::whereRaw('1 = 0'); // Query vacío
            }

            return Client::whereHas('companyRelations', function ($query) use ($userCompany) {
                $query->where('company_id', $userCompany->id)
                      ->where('active', true);
            });
        }

        return Client::whereRaw('1 = 0'); // Query vacío por defecto
    }

    /**
     * Obtener clientes editables para el usuario.
     */
    public function getEditableClients(User $user)
    {
        // Super admin puede editar todos los clientes
        if ($user->hasRole('super-admin')) {
            return Client::query();
        }

        // Company admin puede editar clientes donde su empresa tiene permiso
        if ($user->hasRole('company-admin')) {
            $userCompany = $this->getUserCompany();

            if (!$userCompany) {
                return Client::whereRaw('1 = 0');
            }

            return Client::whereHas('companyRelations', function ($query) use ($userCompany) {
                $query->where('company_id', $userCompany->id)
                      ->where('can_edit', true)
                      ->where('active', true);
            });
        }

        return Client::whereRaw('1 = 0'); // Users no pueden editar
    }
}
