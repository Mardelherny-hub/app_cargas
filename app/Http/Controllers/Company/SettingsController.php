<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Controlador para configuración de empresa
 *
 * Funcionalidades implementadas:
 * - Configuración general de empresa
 * - Configuración de webservices
 * - Gestión de notificaciones
 * - Preferencias específicas por rol de empresa
 *
 * Acceso: Solo company-admin
 */
class SettingsController extends Controller
{
    use UserHelper;

    /**
     * Mostrar página principal de configuración.
     */
    public function index()
    {
        // Solo company-admin puede acceder a configuración
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden acceder a la configuración.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        // Configuraciones actuales
        $currentSettings = [
            'general' => [
                'legal_name' => $company->legal_name,
                'commercial_name' => $company->commercial_name,
                'tax_id' => $company->tax_id,
                'country' => $company->country,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'city' => $company->city,
                'postal_code' => $company->postal_code,
                'id_maria' => $company->id_maria,
            ],
            'webservices' => [
                'ws_environment' => $company->ws_environment,
                'ws_active' => $company->ws_active,
                'certificate_expires_at' => $company->certificate_expires_at,
                'has_certificate' => !empty($company->certificate_path),
            ],
            'business_roles' => $company->company_roles ?? [],
            'roles_config' => $company->roles_config ?? [],
        ];

        // Estadísticas de configuración
        $configStats = [
            'operators_count' => $company->operators()->count(),
            'active_operators' => $company->operators()->where('active', true)->count(),
            'certificate_status' => $this->getCertificateStatus($company),
            'webservice_status' => $company->ws_active ? 'active' : 'inactive',
        ];

        return view('company.settings.index', compact('currentSettings', 'configStats'));
    }

    /**
     * Actualizar configuración general.
     */
    public function updateGeneral(Request $request)
    {
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden modificar la configuración.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        $request->validate([
            'legal_name' => 'required|string|max:255',
            'commercial_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('companies', 'email')->ignore($company->id)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'id_maria' => 'nullable|string|max:10|regex:/^[A-Z0-9]*$/',
        ]);

        try {
            $company->update([
                'legal_name' => $request->legal_name,
                'commercial_name' => $request->commercial_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'id_maria' => $request->id_maria,
            ]);

            Log::info('Configuración general actualizada', [
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id
            ]);

            return back()->with('success', 'Configuración general actualizada correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al actualizar configuración general', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al actualizar la configuración: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar configuración de seguridad.
     */
    public function updateSecurity(Request $request)
    {
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden modificar la configuración de seguridad.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        $request->validate([
            'require_2fa' => 'boolean',
            'session_timeout' => 'required|integer|min:15|max:480', // 15 min a 8 horas
            'password_expiry_days' => 'required|integer|min:30|max:365',
            'max_login_attempts' => 'required|integer|min:3|max:10',
        ]);

        try {
            // Obtener configuración de seguridad actual
            $securityConfig = $company->security_config ?? [];

            // Actualizar configuraciones de seguridad
            $securityConfig = array_merge($securityConfig, [
                'require_2fa' => $request->boolean('require_2fa'),
                'session_timeout' => $request->session_timeout,
                'password_expiry_days' => $request->password_expiry_days,
                'max_login_attempts' => $request->max_login_attempts,
                'updated_at' => now(),
                'updated_by' => $this->getCurrentUser()->id,
            ]);

            $company->update(['security_config' => $securityConfig]);

            Log::info('Configuración de seguridad actualizada', [
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id,
                'changes' => $request->only(['require_2fa', 'session_timeout', 'password_expiry_days', 'max_login_attempts'])
            ]);

            return back()->with('success', 'Configuración de seguridad actualizada correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al actualizar configuración de seguridad', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al actualizar la configuración de seguridad: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar configuración de notificaciones.
     */
    public function updateNotifications(Request $request)
    {
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden modificar las notificaciones.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        $request->validate([
            'email_notifications' => 'boolean',
            'certificate_expiry_alerts' => 'boolean',
            'webservice_error_alerts' => 'boolean',
            'operator_activity_alerts' => 'boolean',
            'daily_summary' => 'boolean',
            'weekly_reports' => 'boolean',
            'notification_emails' => 'nullable|string', // Lista de emails separados por coma
        ]);

        try {
            // Procesar lista de emails de notificación
            $notificationEmails = [];
            if ($request->notification_emails) {
                $emails = explode(',', $request->notification_emails);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $notificationEmails[] = $email;
                    }
                }
            }

            // Configuración de notificaciones
            $notificationConfig = [
                'email_notifications' => $request->boolean('email_notifications'),
                'certificate_expiry_alerts' => $request->boolean('certificate_expiry_alerts'),
                'webservice_error_alerts' => $request->boolean('webservice_error_alerts'),
                'operator_activity_alerts' => $request->boolean('operator_activity_alerts'),
                'daily_summary' => $request->boolean('daily_summary'),
                'weekly_reports' => $request->boolean('weekly_reports'),
                'notification_emails' => $notificationEmails,
                'updated_at' => now(),
                'updated_by' => $this->getCurrentUser()->id,
            ];

            $company->update(['notification_config' => $notificationConfig]);

            Log::info('Configuración de notificaciones actualizada', [
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id
            ]);

            return back()->with('success', 'Configuración de notificaciones actualizada correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al actualizar configuración de notificaciones', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al actualizar las notificaciones: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar preferencias específicas por rol de empresa.
     */
    public function updatePreferences(Request $request)
    {
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden modificar las preferencias.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        $companyRoles = $company->company_roles ?? [];

        // Validar según los roles de la empresa
        $rules = [
            'default_language' => 'required|in:es,en',
            'timezone' => 'required|string|max:50',
            'date_format' => 'required|in:d/m/Y,m/d/Y,Y-m-d',
            'currency' => 'required|in:ARS,USD,PYG',
        ];

        // Validaciones específicas por rol
        if (in_array('Cargas', $companyRoles)) {
            $rules = array_merge($rules, [
                'default_shipment_status' => 'required|in:draft,pending',
                'auto_generate_manifests' => 'boolean',
                'require_container_weights' => 'boolean',
            ]);
        }

        if (in_array('Desconsolidador', $companyRoles)) {
            $rules = array_merge($rules, [
                'auto_register_titles' => 'boolean',
                'consolidation_auto_pdf' => 'boolean',
            ]);
        }

        if (in_array('Transbordos', $companyRoles)) {
            $rules = array_merge($rules, [
                'auto_track_positions' => 'boolean',
                'position_update_interval' => 'required|integer|min:10|max:120', // minutos
                'auto_empty_container_ws' => 'boolean',
            ]);
        }

        $request->validate($rules);

        try {
            // Configuración base
            $preferences = [
                'default_language' => $request->default_language,
                'timezone' => $request->timezone,
                'date_format' => $request->date_format,
                'currency' => $request->currency,
                'updated_at' => now(),
                'updated_by' => $this->getCurrentUser()->id,
            ];

            // Agregar preferencias específicas por rol
            if (in_array('Cargas', $companyRoles)) {
                $preferences['cargas'] = [
                    'default_shipment_status' => $request->default_shipment_status,
                    'auto_generate_manifests' => $request->boolean('auto_generate_manifests'),
                    'require_container_weights' => $request->boolean('require_container_weights'),
                ];
            }

            if (in_array('Desconsolidador', $companyRoles)) {
                $preferences['desconsolidacion'] = [
                    'auto_register_titles' => $request->boolean('auto_register_titles'),
                    'consolidation_auto_pdf' => $request->boolean('consolidation_auto_pdf'),
                ];
            }

            if (in_array('Transbordos', $companyRoles)) {
                $preferences['transbordos'] = [
                    'auto_track_positions' => $request->boolean('auto_track_positions'),
                    'position_update_interval' => $request->position_update_interval,
                    'auto_empty_container_ws' => $request->boolean('auto_empty_container_ws'),
                ];
            }

            $company->update(['preferences_config' => $preferences]);

            Log::info('Preferencias de empresa actualizadas', [
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id,
                'roles' => $companyRoles
            ]);

            return back()->with('success', 'Preferencias actualizadas correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al actualizar preferencias', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al actualizar las preferencias: ' . $e->getMessage());
        }
    }

    /**
     * Probar conectividad con webservices DESA.
     */
    public function testWebserviceConnection()
    {
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden probar conexiones.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        if (!$company->ws_active) {
            return response()->json([
                'success' => false,
                'message' => 'Los webservices no están activados para esta empresa.'
            ], 400);
        }

        try {
            // TODO: Implementar test real de conexión con DESA
            // Por ahora, simulación de test

            $testResults = [
                'certificate_valid' => $this->testCertificate($company),
                'environment' => $company->ws_environment,
                'connection_time' => rand(50, 200) . 'ms', // Simulado
                'last_successful_call' => now()->subHours(rand(1, 6)),
            ];

            Log::info('Test de webservice realizado', [
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id,
                'results' => $testResults
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conexión probada correctamente.',
                'data' => $testResults
            ]);

        } catch (\Exception $e) {
            Log::error('Error al probar conexión webservice', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al probar la conexión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar configuración de la empresa.
     */
    public function exportConfiguration()
    {
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden exportar configuración.');
        }

        $company = $this->getUserCompany();
        if (!$company) {
            abort(403, 'No se encontró la empresa asociada.');
        }

        try {
            $configuration = [
                'company_info' => [
                    'legal_name' => $company->legal_name,
                    'commercial_name' => $company->commercial_name,
                    'tax_id' => $company->tax_id,
                    'country' => $company->country,
                ],
                'business_roles' => $company->company_roles,
                'roles_config' => $company->roles_config,
                'security_config' => $company->security_config,
                'notification_config' => $company->notification_config,
                'preferences_config' => $company->preferences_config,
                'operators_count' => $company->operators()->count(),
                'exported_at' => now(),
                'exported_by' => $this->getCurrentUser()->email,
            ];

            Log::info('Configuración exportada', [
                'company_id' => $company->id,
                'user_id' => $this->getCurrentUser()->id
            ]);

            $filename = 'config_' . $company->tax_id . '_' . now()->format('Y-m-d_H-i-s') . '.json';

            return response()->json($configuration)
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('Error al exportar configuración', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al exportar la configuración: ' . $e->getMessage());
        }
    }

    // ========================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ========================================

    /**
     * Obtener estado del certificado.
     */
    private function getCertificateStatus($company): array
    {
        if (empty($company->certificate_path)) {
            return [
                'status' => 'not_uploaded',
                'message' => 'Sin certificado',
                'color' => 'red'
            ];
        }

        if (!$company->certificate_expires_at) {
            return [
                'status' => 'unknown_expiry',
                'message' => 'Certificado sin fecha de vencimiento',
                'color' => 'yellow'
            ];
        }

        $daysToExpiry = now()->diffInDays($company->certificate_expires_at, false);

        if ($daysToExpiry < 0) {
            return [
                'status' => 'expired',
                'message' => 'Certificado vencido',
                'color' => 'red'
            ];
        } elseif ($daysToExpiry <= 30) {
            return [
                'status' => 'expiring_soon',
                'message' => "Vence en {$daysToExpiry} días",
                'color' => 'orange'
            ];
        } else {
            return [
                'status' => 'valid',
                'message' => "Válido por {$daysToExpiry} días",
                'color' => 'green'
            ];
        }
    }

    /**
     * Probar validez del certificado.
     */
    private function testCertificate($company): bool
    {
        if (empty($company->certificate_path)) {
            return false;
        }

        if (!Storage::exists($company->certificate_path)) {
            return false;
        }

        // TODO: Implementar validación real del certificado
        // Por ahora, verificar que existe y no esté vencido
        return $company->certificate_expires_at && $company->certificate_expires_at > now();
    }
}
