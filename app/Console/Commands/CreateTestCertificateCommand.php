<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * COMANDO PARA CREAR CERTIFICADO DE TESTING
 * 
 * Genera un certificado .p12 temporal para probar la funcionalidad
 * de subida y gestión de certificados sin necesidad de certificados reales.
 * 
 * UBICACIÓN: app/Console/Commands/CreateTestCertificateCommand.php
 * 
 * USO:
 * php artisan certificates:create-test
 * php artisan certificates:create-test --company=1
 * php artisan certificates:create-test --password=test123
 * 
 * FUNCIONALIDADES:
 * - Genera certificado .p12 temporal válido
 * - Configura automáticamente en la empresa
 * - Permite probar toda la funcionalidad
 * - Solo para ambiente de desarrollo/testing
 */
class CreateTestCertificateCommand extends Command
{
    /**
     * Signature del comando
     */
    protected $signature = 'certificates:create-test 
                           {--company= : ID de empresa específica}
                           {--password=test123 : Contraseña del certificado de testing}
                           {--alias=TEST_CERT : Alias del certificado}
                           {--days=365 : Días de validez del certificado}
                           {--force : Sobrescribir certificado existente}';

    /**
     * Descripción del comando
     */
    protected $description = 'Crear certificado de testing para probar funcionalidad de webservices';

    /**
     * Ejecutar el comando
     */
    public function handle(): int
    {
        // Verificar que estemos en ambiente de desarrollo
        if (app()->environment('production')) {
            $this->error('❌ Este comando solo puede ejecutarse en ambiente de desarrollo');
            return Command::FAILURE;
        }

        $this->displayHeader();

        try {
            // 1. Obtener o seleccionar empresa
            $company = $this->getTargetCompany();
            if (!$company) {
                return Command::FAILURE;
            }

            // 2. Verificar certificado existente
            if ($company->certificate_path && !$this->option('force')) {
                $this->warn("⚠️  La empresa ya tiene un certificado configurado");
                $this->info("   Archivo actual: " . basename($company->certificate_path));
                
                if (!$this->confirm('¿Desea reemplazarlo?')) {
                    $this->info('Operación cancelada');
                    return Command::SUCCESS;
                }
            }

            // 3. Generar certificado de testing
            $this->info('🔧 Generando certificado de testing...');
            $certificateData = $this->generateTestCertificate();

            // 4. Guardar certificado en storage
            $this->info('💾 Guardando certificado...');
            $storagePath = $this->saveCertificateToStorage($certificateData, $company);

            // 5. Configurar en la empresa
            $this->info('⚙️  Configurando empresa...');
            $this->configureCertificateInCompany($company, $storagePath);

            // 6. Mostrar resumen
            $this->displaySuccessSummary($company);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error creando certificado de testing: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Mostrar header del comando
     */
    private function displayHeader(): void
    {
        $this->info('🧪 CREADOR DE CERTIFICADO DE TESTING');
        $this->info('====================================');
        $this->line('');
        $this->warn('⚠️  SOLO PARA DESARROLLO - No usar en producción');
        $this->line('');
    }

    /**
     * Obtener empresa objetivo
     */
    private function getTargetCompany(): ?Company
    {
        $companyId = $this->option('company');

        if ($companyId) {
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("❌ Empresa con ID {$companyId} no encontrada");
                return null;
            }
        } else {
            // Mostrar empresas disponibles
            $companies = Company::where('active', true)->get();
            
            if ($companies->isEmpty()) {
                $this->error('❌ No hay empresas activas en el sistema');
                return null;
            }

            $this->info('🏢 Empresas disponibles:');
            foreach ($companies as $company) {
                $hasWS = $company->ws_active ? '✅' : '❌';
                $hasCert = $company->certificate_path ? '🔒' : '🔓';
                
                $this->info("   {$company->id}. {$company->business_name} {$hasWS} {$hasCert}");
            }
            $this->line('');

            $companyId = $this->ask('Seleccione el ID de la empresa');
            $company = Company::find($companyId);

            if (!$company) {
                $this->error("❌ ID de empresa inválido: {$companyId}");
                return null;
            }
        }

        $this->info("🎯 Empresa seleccionada: {$company->business_name} (ID: {$company->id})");
        return $company;
    }

    /**
     * Generar certificado de testing
     */
    private function generateTestCertificate(): array
    {
        $password = $this->option('password');
        $alias = $this->option('alias');
        $days = (int) $this->option('days');

        // Crear certificado temporal usando OpenSSL
        $this->info('   • Generando clave privada...');
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->info('   • Creando certificado X.509...');
        
        // Configurar datos del certificado
        $dn = [
            'countryName' => 'AR',
            'stateOrProvinceName' => 'Buenos Aires',
            'localityName' => 'CABA',
            'organizationName' => 'Testing Certificate Authority',
            'organizationalUnitName' => 'Development',
            'commonName' => 'test-certificate.local',
            'emailAddress' => 'test@certificate.local'
        ];

        // Crear certificado
        $cert = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => 'sha256',
            'x509_extensions' => 'v3_req',
            'req_extensions' => 'v3_req',
        ]);

        $x509 = openssl_csr_sign($cert, null, $privateKey, $days, [
            'digest_alg' => 'sha256'
        ]);

        $this->info('   • Exportando a formato PKCS#12...');

        // Exportar a formato .p12
        $p12Data = '';
        $success = openssl_pkcs12_export(
            $x509, 
            $p12Data, 
            $privateKey, 
            $password,
            [
                'friendly_name' => $alias
            ]
        );

        if (!$success) {
            throw new \Exception('Error generando certificado PKCS#12: ' . openssl_error_string());
        }

        // Limpiar recursos
        openssl_pkey_free($privateKey);
        openssl_x509_free($x509);

        return [
            'data' => $p12Data,
            'password' => $password,
            'alias' => $alias,
            'expires_at' => Carbon::now()->addDays($days),
        ];
    }

    /**
     * Guardar certificado en storage
     */
    private function saveCertificateToStorage(array $certificateData, Company $company): string
    {
        // Crear nombre único para el archivo
        $filename = 'test_certificate_' . $company->id . '_' . time() . '.p12';
        $path = 'certificates/' . $filename;

        // Guardar en storage
        Storage::put($path, $certificateData['data']);

        $this->info("   • Certificado guardado en: {$path}");
        return $path;
    }

    /**
     * Configurar certificado en la empresa
     */
    private function configureCertificateInCompany(Company $company, string $storagePath): void
    {
        $password = $this->option('password');
        $alias = $this->option('alias');
        $days = (int) $this->option('days');
        $expiresAt = Carbon::now()->addDays($days);

        // Eliminar certificado anterior si existe
        if ($company->certificate_path && Storage::exists($company->certificate_path)) {
            Storage::delete($company->certificate_path);
            $this->info('   • Certificado anterior eliminado');
        }

        // Actualizar empresa
        $company->update([
            'certificate_path' => $storagePath,
            'certificate_password' => $password, // Se encripta automáticamente
            'certificate_alias' => $alias,
            'certificate_expires_at' => $expiresAt,
            'ws_active' => true, // Activar webservices
        ]);

        $this->info('   • Empresa actualizada con nuevo certificado');
        $this->info('   • Webservices activados automáticamente');
    }

    /**
     * Mostrar resumen de éxito
     */
    private function displaySuccessSummary(Company $company): void
    {
        $this->line('');
        $this->info('✅ CERTIFICADO DE TESTING CREADO EXITOSAMENTE');
        $this->info('===========================================');
        $this->line('');
        
        $this->info('📋 Detalles del Certificado:');
        $this->info("   • Empresa: {$company->business_name}");
        $this->info("   • Alias: " . $this->option('alias'));
        $this->info("   • Contraseña: " . $this->option('password'));
        $this->info("   • Válido por: " . $this->option('days') . " días");
        $this->info("   • Vence: " . Carbon::now()->addDays($this->option('days'))->format('d/m/Y H:i'));
        $this->line('');

        $this->info('🚀 Próximos Pasos:');
        $this->info('   1. Acceder a /company/certificates en el navegador');
        $this->info('   2. Verificar que el certificado aparece como "Activo"');
        $this->info('   3. Probar funcionalidades de webservices');
        $this->info('   4. Probar renovación o eliminación');
        $this->line('');

        $this->warn('⚠️  RECORDATORIO: Este es un certificado de testing');
        $this->warn('   • No válido para webservices reales de producción');
        $this->warn('   • Solo para probar funcionalidades del sistema');
        $this->warn('   • Eliminar antes de ir a producción');
    }
}