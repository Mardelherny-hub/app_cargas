<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Traits\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CertificateController extends Controller
{
    use UserHelper;

    /**
     * Mostrar vista principal de gestión de certificados.
     */
    public function index()
    {
        // 1. Verificar permisos básicos (solo company-admin)
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin puede gestionar certificados
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $user = $this->getCurrentUser();
        $company = $this->getUserCompany();

        // 3. Verificar que el usuario tenga una empresa asociada
        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 4. Verificar acceso específico a esta empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // Obtener estado actual del certificado
        $certificateStatus = $this->getCertificateStatus($company);

        // Verificar configuración de webservices
        $webserviceStatus = $this->getWebserviceStatus($company);

        // Obtener roles de empresa que requieren certificado
        $rolesRequiringCertificate = $this->getRolesRequiringCertificate($company);

        return view('company.certificates.index', compact(
            'company',
            'certificateStatus',
            'webserviceStatus',
            'rolesRequiringCertificate'
        ));
    }

    /**
     * Mostrar formulario de subida de certificado.
     */
    public function upload()
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        return view('company.certificates.upload', compact('company'));
    }

    /**
     * Procesar subida de certificado.
     */
    public function processUpload(Request $request)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Validación
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:2048',
            'password' => 'required|string|min:1',
            'alias' => 'nullable|string|max:255',
            'expires_at' => 'required|date|after:today',
        ], [
            'certificate.required' => 'Debe seleccionar un archivo de certificado.',
            'certificate.mimes' => 'El certificado debe ser un archivo .p12 o .pfx.',
            'certificate.max' => 'El archivo no puede ser mayor a 2MB.',
            'password.required' => 'La contraseña del certificado es obligatoria.',
            'expires_at.required' => 'La fecha de vencimiento es obligatoria.',
            'expires_at.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ]);

        try {
            // Eliminar certificado anterior si existe
            if ($company->certificate_path && Storage::exists($company->certificate_path)) {
                Storage::delete($company->certificate_path);
            }

            // Guardar nuevo certificado
            $path = $request->file('certificate')->store('certificates');

            // Actualizar datos de la empresa
            $company->update([
                'certificate_path' => $path,
                'certificate_password' => $request->password, // Se encripta automáticamente por el mutator
                'certificate_alias' => $request->alias,
                'certificate_expires_at' => $request->expires_at,
            ]);

            return redirect()->route('company.certificates.index')
                ->with('success', 'Certificado subido correctamente. Los webservices ya están operativos.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al subir el certificado: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles del certificado actual.
     */
    public function show($certificateId = null)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que la empresa tenga certificado
        if (!$company->certificate_path) {
            return redirect()->route('company.certificates.index')
                ->with('error', 'No hay certificado configurado para mostrar.');
        }

        $certificateStatus = $this->getCertificateStatus($company);
        $webserviceStatus = $this->getWebserviceStatus($company);

        return view('company.certificates.show', compact(
            'company',
            'certificateStatus',
            'webserviceStatus'
        ));
    }

    /**
     * Eliminar certificado actual.
     */
    public function destroy($certificateId = null)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que la empresa tenga certificado
        if (!$company->certificate_path) {
            return redirect()->route('company.certificates.index')
                ->with('error', 'No hay certificado para eliminar.');
        }

        try {
            // Usar el método del modelo para eliminar certificado
            $company->deleteCertificate();

            return redirect()->route('company.certificates.index')
                ->with('success', 'Certificado eliminado correctamente. Los webservices han sido deshabilitados.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar el certificado: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar formulario de renovación de certificado.
     */
    public function renew($certificateId = null)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que la empresa tenga certificado
        if (!$company->certificate_path) {
            return redirect()->route('company.certificates.index')
                ->with('error', 'No hay certificado para renovar.');
        }

        $certificateStatus = $this->getCertificateStatus($company);

        return view('company.certificates.renew', compact('company', 'certificateStatus'));
    }

    /**
     * Procesar renovación de certificado.
     */
    public function processRenew(Request $request, $certificateId = null)
    {
        // 1. Verificar permisos básicos
        if (!$this->canPerform('certificate_management')) {
            abort(403, 'No tiene permisos para gestionar certificados.');
        }

        // 2. Solo company-admin
        if (!$this->isCompanyAdmin()) {
            abort(403, 'Solo los administradores de empresa pueden gestionar certificados.');
        }

        $company = $this->getUserCompany();

        if (!$company) {
            return redirect()->route('company.dashboard')
                ->with('error', 'No se encontró la empresa asociada a su usuario.');
        }

        // 3. Verificar acceso a empresa
        if (!$this->canAccessCompany($company->id)) {
            abort(403, 'No tiene permisos para acceder a esta empresa.');
        }

        // 4. Verificar que la empresa tenga certificado
        if (!$company->certificate_path) {
            return redirect()->route('company.certificates.index')
                ->with('error', 'No hay certificado para renovar.');
        }

        // 5. Validación para renovación
        $request->validate([
            'certificate' => 'required|file|mimes:p12,pfx|max:2048',
            'password' => 'required|string|min:1',
            'alias' => 'nullable|string|max:255',
            'expires_at' => 'required|date|after:today',
        ], [
            'certificate.required' => 'Debe seleccionar el archivo del nuevo certificado.',
            'certificate.mimes' => 'El certificado debe ser un archivo .p12 o .pfx.',
            'certificate.max' => 'El archivo no puede ser mayor a 2MB.',
            'password.required' => 'La contraseña del nuevo certificado es obligatoria.',
            'expires_at.required' => 'La fecha de vencimiento es obligatoria.',
            'expires_at.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ]);

        try {
            // Eliminar certificado anterior
            if ($company->certificate_path && Storage::exists($company->certificate_path)) {
                Storage::delete($company->certificate_path);
            }

            // Guardar nuevo certificado
            $path = $request->file('certificate')->store('certificates');

            // Actualizar datos de la empresa
            $company->update([
                'certificate_path' => $path,
                'certificate_password' => $request->password,
                'certificate_alias' => $request->alias,
                'certificate_expires_at' => $request->expires_at,
            ]);

            return redirect()->route('company.certificates.index')
                ->with('success', 'Certificado renovado correctamente. Los webservices continúan operativos.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al renovar el certificado: ' . $e->getMessage());
        }
    }

    // ========================================
    // MÉTODOS HELPER PRIVADOS
    // ========================================

    /**
     * Obtener estado detallado del certificado.
     */
    private function getCertificateStatus(Company $company): array
    {
        $status = [
            'has_certificate' => !empty($company->certificate_path),
            'file_name' => $company->certificate_path ? basename($company->certificate_path) : null,
            'alias' => $company->certificate_alias,
            'expires_at' => $company->certificate_expires_at,
            'is_expired' => false,
            'expires_soon' => false,
            'status' => 'none',
            'days_remaining' => null,
            'uploaded_at' => $company->updated_at,
        ];

        if ($company->certificate_expires_at) {
            $expiresAt = Carbon::parse($company->certificate_expires_at);
            $now = Carbon::now();

            $status['is_expired'] = $expiresAt->isPast();
            $status['expires_soon'] = !$status['is_expired'] && $expiresAt->diffInDays($now) <= 30;
            $status['days_remaining'] = $status['is_expired'] ?
                -$now->diffInDays($expiresAt) :
                $expiresAt->diffInDays($now);

            if ($status['is_expired']) {
                $status['status'] = 'expired';
            } elseif ($status['expires_soon']) {
                $status['status'] = 'expiring';
            } else {
                $status['status'] = 'valid';
            }
        }

        return $status;
    }

    /**
     * Obtener estado de webservices relacionado con certificados.
     */
    private function getWebserviceStatus(Company $company): array
    {
        $certStatus = $this->getCertificateStatus($company);

        return [
            'active' => $company->ws_active ?? false,
            'environment' => $company->ws_environment ?? 'testing',
            'can_use_production' => $company->ws_active &&
                                   $company->ws_environment === 'production' &&
                                   $certStatus['status'] === 'valid',
            'requires_certificate' => true,
            'certificate_valid' => $certStatus['status'] === 'valid',
            'disabled_reason' => $this->getWebserviceDisabledReason($company, $certStatus),
        ];
    }

    /**
     * Obtener razón por la cual los webservices están deshabilitados.
     */
    private function getWebserviceDisabledReason(Company $company, array $certStatus): ?string
    {
        if (!$certStatus['has_certificate']) {
            return 'No hay certificado configurado';
        }

        if ($certStatus['is_expired']) {
            return 'Certificado vencido';
        }

        if (!$company->ws_active) {
            return 'Webservices desactivados por el administrador';
        }

        return null;
    }

    /**
     * Obtener roles de empresa que requieren certificado.
     */
    private function getRolesRequiringCertificate(Company $company): array
    {
        $roles = $company->getRoles();
        $rolesRequiringCert = [];

        foreach ($roles as $role) {
            if (in_array($role, ['Cargas', 'Desconsolidador', 'Transbordos'])) {
                $rolesRequiringCert[] = $role;
            }
        }

        return $rolesRequiringCert;
    }
}
