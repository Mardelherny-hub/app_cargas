<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Client;

/**
 * FASE 4 - INTEGRACIÓN CON EMPRESAS | MÓDULO CLIENTES
 *
 * Middleware para control de acceso a clientes
 * Implementa los criterios de aceptación:
 * - Usuarios ven solo clientes de su empresa
 * - Permisos de edición respetan relaciones empresa-cliente
 * - Super admin puede acceder a todos los clientes
 */
class ClientAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Tipo de acceso requerido: 'view', 'edit', 'create', 'delete', 'verify', 'transfer'
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin puede acceder a todo
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Verificar que el usuario tiene acceso básico a clientes
        if (!$user->hasRole(['company-admin', 'user'])) {
            abort(403, 'No tiene permisos para acceder a la gestión de clientes.');
        }

        // Si es una ruta específica de cliente, verificar acceso individual
        $client = $this->getClientFromRequest($request);
        if ($client) {
            if (!$this->canAccessClient($user, $client, $permission)) {
                abort(403, $this->getAccessDeniedMessage($permission));
            }
            return $next($request);
        }

        // Para rutas generales de clientes (como index), verificar acceso general
        if ($this->userHasClientAccess($user)) {
            return $next($request);
        }

        // Si el usuario no tiene acceso a ningún cliente
        abort(403, 'No tiene permisos para acceder a la gestión de clientes. Contacte al administrador.');
    }

    /**
     * Verificar si el usuario puede acceder a un cliente específico.
     */
    private function canAccessClient($user, Client $client, string $permission): bool
    {
        switch ($permission) {
            case 'view':
            case 'use':
                return $user->canUseClient($client);

            case 'edit':
                return $user->canEditClient($client);

            case 'create':
                // Pueden crear clientes los company-admin y super-admin
                return $user->hasRole(['super-admin', 'company-admin']);

            case 'delete':
                // Solo super admin o company admin que creó el cliente puede eliminar
                if ($user->hasRole('super-admin')) {
                    return true;
                }

                if ($user->hasRole('company-admin')) {
                    $userCompany = $this->getUserCompany();
                    return $userCompany && $client->created_by_company_id === $userCompany->id;
                }

                return false;

            case 'verify':
                // Pueden verificar quienes pueden editar
                return $user->canEditClient($client);

            case 'transfer':
                // Solo super admin puede transferir clientes entre empresas
                return $user->hasRole('super-admin');

            default:
                return false;
        }
    }

    /**
     * Verificar si el usuario tiene acceso general a clientes.
     */
    private function userHasClientAccess($user): bool
    {
        // Company admin debe tener empresa asociada
        if ($user->hasRole('company-admin')) {
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                return $user->userable->active;
            }
            return false;
        }

        // Users (operadores) deben tener empresa asociada válida
        if ($user->hasRole('user')) {
            if ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $operator = $user->userable;

                // Verificar que el operador está activo
                if (!$operator->active) {
                    return false;
                }

                // Verificar que tiene empresa válida
                if (!$operator->company_id) {
                    return false;
                }

                // Verificar que la empresa existe y está activa
                $company = $operator->company;
                return $company && $company->active;
            }

            // Si es usuario directo de empresa
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                return $user->userable->active;
            }

            return false;
        }

        return false;
    }

    /**
     * Obtener el cliente desde la request.
     */
    private function getClientFromRequest($request): ?Client
    {
        // Intentar obtener cliente desde diferentes parámetros de ruta
        $clientParams = ['client', 'clientId', 'client_id'];

        foreach ($clientParams as $param) {
            $clientId = $request->route($param);
            if ($clientId) {
                // Si es instancia de modelo, devolverla directamente
                if ($clientId instanceof Client) {
                    return $clientId;
                }

                // Si es ID, buscar el cliente
                if (is_numeric($clientId)) {
                    return Client::find($clientId);
                }
            }
        }

        return null;
    }

    /**
     * Obtener mensaje de acceso denegado según el tipo de permiso.
     */
    private function getAccessDeniedMessage(string $permission): string
    {
        switch ($permission) {
            case 'view':
            case 'use':
                return 'No tiene permisos para ver este cliente.';

            case 'edit':
                return 'No tiene permisos para editar este cliente.';

            case 'create':
                return 'No tiene permisos para crear clientes.';

            case 'delete':
                return 'No tiene permisos para eliminar este cliente.';

            case 'verify':
                return 'No tiene permisos para verificar este cliente.';

            case 'transfer':
                return 'Solo Super Admin puede transferir clientes entre empresas.';

            default:
                return 'No tiene permisos para realizar esta acción sobre el cliente.';
        }
    }
}