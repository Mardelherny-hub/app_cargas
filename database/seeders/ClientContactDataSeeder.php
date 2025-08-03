<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\ClientContactData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SEEDER BASADO EN MIGRACIÓN EXACTA
 * 
 * Columnas según migración 2025_07_16_224459_create_client_contact_data_table.php:
 * - id, client_id, email, secondary_email, phone, mobile_phone, fax
 * - address_line_1, address_line_2, city, state_province, postal_code
 * - latitude, longitude, contact_person_name, contact_person_position
 * - contact_person_phone, contact_person_email, business_hours, timezone
 * - communication_preferences, accepts_email_notifications, accepts_sms_notifications
 * - notes, internal_notes, active, is_primary, verified, verified_at
 * - created_by_user_id, updated_by_user_id, created_at, updated_at
 * 
 * SIN contact_type (eliminado en simplificación)
 */
class ClientContactDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📞 Creando información de contacto para clientes...');

        // Verificar que existan clientes con legal_name válido
        $clients = Client::with('country')
                        ->whereNotNull('legal_name')
                        ->where('legal_name', '!=', '')
                        ->get();
        
        if ($clients->isEmpty()) {
            $this->command->error('❌ No se encontraron clientes con legal_name válido. Ejecute ClientsSeeder primero.');
            return;
        }

        $totalClients = Client::count();
        $validClients = $clients->count();
        
        $this->command->info("📊 Procesando {$validClients} clientes válidos de {$totalClients} totales...");

        DB::transaction(function () use ($clients) {
            // Obtener usuarios para auditoría
            $users = User::where('active', true)->get();
            $defaultUser = $users->first();

            if (!$defaultUser) {
                $this->command->error('❌ No se encontraron usuarios activos para auditoría.');
                return;
            }

            foreach ($clients as $client) {
                $this->createContactDataForClient($client, $users->random() ?? $defaultUser);
            }
        });

        $this->command->info('✅ Información de contacto creada exitosamente!');
        $this->displaySummary();
    }

    /**
     * Crear información de contacto para un cliente específico
     */
    private function createContactDataForClient(Client $client, User $user): void
    {
        // Validar que el cliente tenga legal_name
        if (empty($client->legal_name)) {
            $this->command->warn("  ⚠️ Cliente ID {$client->id} sin legal_name - omitido");
            return;
        }

        $isArgentinian = $client->country && $client->country->alpha2_code === 'AR';
        $isParaguayan = $client->country && $client->country->alpha2_code === 'PY';

        // Crear contacto principal
        try {
            $primaryContact = $this->createPrimaryContact($client, $user, $isArgentinian, $isParaguayan);
            $this->command->line("  ✓ Contacto principal: {$client->legal_name}");

            // 70% de probabilidad de tener contacto secundario para empresas grandes
            if ($this->isLargeCompany($client->legal_name) && rand(1, 100) <= 70) {
                $this->createSecondaryContact($client, $user, $isArgentinian, $isParaguayan);
                $this->command->line("    + Contacto secundario agregado");
            }
        } catch (\Exception $e) {
            $this->command->error("  ❌ Error al crear contacto para {$client->legal_name}: " . $e->getMessage());
        }
    }

    /**
     * Crear contacto principal para el cliente
     */
    private function createPrimaryContact(Client $client, User $user, bool $isArgentinian, bool $isParaguayan): ClientContactData
    {
        $contactData = $this->generateContactDataFromMigration($client, $isArgentinian, $isParaguayan, true);
        
        return ClientContactData::create(array_merge($contactData, [
            'client_id' => $client->id,
            'is_primary' => true,
            'active' => true,
            'verified' => rand(1, 100) <= 85, // 85% verificado
            'verified_at' => rand(1, 100) <= 85 ? now()->subDays(rand(1, 180)) : null,
            'created_by_user_id' => $user->id,
        ]));
    }

    /**
     * Crear contacto secundario para el cliente
     */
    private function createSecondaryContact(Client $client, User $user, bool $isArgentinian, bool $isParaguayan): ClientContactData
    {
        $contactData = $this->generateContactDataFromMigration($client, $isArgentinian, $isParaguayan, false);
        
        return ClientContactData::create(array_merge($contactData, [
            'client_id' => $client->id,
            'is_primary' => false,
            'active' => rand(1, 100) <= 90, // 90% activo
            'verified' => rand(1, 100) <= 60, // 60% verificado
            'verified_at' => rand(1, 100) <= 60 ? now()->subDays(rand(1, 360)) : null,
            'created_by_user_id' => $user->id,
        ]));
    }

    /**
     * Generar datos de contacto según migración base EXACTA
     * Todas las columnas de: 2025_07_16_224459_create_client_contact_data_table.php
     */
    private function generateContactDataFromMigration(Client $client, bool $isArgentinian, bool $isParaguayan, bool $isPrimary): array
    {
        if ($isArgentinian) {
            return $this->generateArgentinianContactData($client->legal_name, $isPrimary);
        } elseif ($isParaguayan) {
            return $this->generateParaguayanContactData($client->legal_name, $isPrimary);
        } else {
            return $this->generateGenericContactData($client->legal_name, $isPrimary);
        }
    }

    /**
     * Generar datos argentinos de contacto - COLUMNAS EXACTAS DE MIGRACIÓN
     */
    private function generateArgentinianContactData(string $companyName, bool $isPrimary): array
    {
        $emails = ['info@', 'contacto@', 'ventas@', 'administracion@', 'operaciones@'];
        $argentinianCities = ['Buenos Aires', 'Córdoba', 'Rosario', 'La Plata', 'Mar del Plata'];
        $argentinianProvinces = ['Buenos Aires', 'Córdoba', 'Santa Fe', 'Mendoza', 'Tucumán'];
        $positions = ['Gerente General', 'Director Comercial', 'Jefe de Operaciones'];
        $argentinianNames = ['María González', 'Carlos Rodríguez', 'Ana Martínez', 'Diego López'];

        return [
            // INFORMACIÓN DE CONTACTO PRINCIPAL (según migración)
            'email' => $this->generateCompanyEmail($companyName, $emails),
            'secondary_email' => rand(1, 100) <= 40 ? $this->generateCompanyEmail($companyName, ['admin@', 'soporte@']) : null,
            'phone' => $this->generateArgentinianPhone(),
            'mobile_phone' => $this->generateArgentinianMobile(),
            'fax' => rand(1, 100) <= 25 ? $this->generateArgentinianPhone() : null,
            
            // DIRECCIÓN FÍSICA COMPLETA (según migración)
            'address_line_1' => $this->generateArgentinianAddress(),
            'address_line_2' => rand(1, 100) <= 30 ? $this->generateAddressLine2() : null,
            'city' => $argentinianCities[array_rand($argentinianCities)],
            'state_province' => $argentinianProvinces[array_rand($argentinianProvinces)],
            'postal_code' => $this->generateArgentinianPostalCode(),
            'latitude' => rand(1, 100) <= 20 ? -34.6118 + (rand(-100, 100) / 1000) : null,
            'longitude' => rand(1, 100) <= 20 ? -58.3960 + (rand(-100, 100) / 1000) : null,
            
            // PERSONA DE CONTACTO (según migración)
            'contact_person_name' => $argentinianNames[array_rand($argentinianNames)],
            'contact_person_position' => $positions[array_rand($positions)],
            'contact_person_phone' => $this->generateArgentinianMobile(),
            'contact_person_email' => $this->generatePersonalEmail(),
            
            // HORARIOS Y CONFIGURACIÓN (según migración)
            'business_hours' => $this->generateBusinessHours(),
            'timezone' => 'America/Argentina/Buenos_Aires',
            
            // PREFERENCIAS DE COMUNICACIÓN (según migración)
            'communication_preferences' => $this->generateCommunicationPreferences(),
            'accepts_email_notifications' => rand(1, 100) <= 90,
            'accepts_sms_notifications' => rand(1, 100) <= 60,
            
            // OBSERVACIONES Y NOTAS (según migración)
            'notes' => $this->generateContactNotes($isPrimary),
            'internal_notes' => rand(1, 100) <= 15 ? 'Contacto verificado por equipo comercial' : null,
        ];
    }

    /**
     * Generar datos paraguayos de contacto - COLUMNAS EXACTAS DE MIGRACIÓN
     */
    private function generateParaguayanContactData(string $companyName, bool $isPrimary): array
    {
        $emails = ['info@', 'contacto@', 'ventas@', 'administracion@', 'operaciones@'];
        $paraguayanCities = ['Asunción', 'Ciudad del Este', 'San Lorenzo', 'Lambaré', 'Encarnación'];
        $paraguayanDepartments = ['Central', 'Alto Paraná', 'Itapúa', 'Caaguazú', 'Amambay'];
        $positions = ['Gerente General', 'Director Comercial', 'Jefe de Operaciones'];
        $paraguayanNames = ['María Benítez', 'Carlos Giménez', 'Ana Villalba', 'Diego Escobar'];

        return [
            // INFORMACIÓN DE CONTACTO PRINCIPAL (según migración)
            'email' => $this->generateCompanyEmail($companyName, $emails),
            'secondary_email' => rand(1, 100) <= 40 ? $this->generateCompanyEmail($companyName, ['admin@', 'soporte@']) : null,
            'phone' => $this->generateParaguayanPhone(),
            'mobile_phone' => $this->generateParaguayanMobile(),
            'fax' => rand(1, 100) <= 25 ? $this->generateParaguayanPhone() : null,
            
            // DIRECCIÓN FÍSICA COMPLETA (según migración)
            'address_line_1' => $this->generateParaguayanAddress(),
            'address_line_2' => rand(1, 100) <= 30 ? $this->generateAddressLine2() : null,
            'city' => $paraguayanCities[array_rand($paraguayanCities)],
            'state_province' => $paraguayanDepartments[array_rand($paraguayanDepartments)],
            'postal_code' => $this->generateParaguayanPostalCode(),
            'latitude' => rand(1, 100) <= 20 ? -25.2637 + (rand(-100, 100) / 1000) : null,
            'longitude' => rand(1, 100) <= 20 ? -57.5759 + (rand(-100, 100) / 1000) : null,
            
            // PERSONA DE CONTACTO (según migración)
            'contact_person_name' => $paraguayanNames[array_rand($paraguayanNames)],
            'contact_person_position' => $positions[array_rand($positions)],
            'contact_person_phone' => $this->generateParaguayanMobile(),
            'contact_person_email' => $this->generatePersonalEmail(),
            
            // HORARIOS Y CONFIGURACIÓN (según migración)
            'business_hours' => $this->generateBusinessHours(),
            'timezone' => 'America/Asuncion',
            
            // PREFERENCIAS DE COMUNICACIÓN (según migración)
            'communication_preferences' => $this->generateCommunicationPreferences(),
            'accepts_email_notifications' => rand(1, 100) <= 85,
            'accepts_sms_notifications' => rand(1, 100) <= 50,
            
            // OBSERVACIONES Y NOTAS (según migración)
            'notes' => $this->generateContactNotes($isPrimary),
            'internal_notes' => rand(1, 100) <= 15 ? 'Cliente verificado para operaciones PY' : null,
        ];
    }

    /**
     * Generar datos genéricos de contacto - COLUMNAS EXACTAS DE MIGRACIÓN
     */
    private function generateGenericContactData(string $companyName, bool $isPrimary): array
    {
        $emails = ['info@', 'contact@', 'sales@', 'admin@', 'operations@'];
        $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Miami'];
        $states = ['NY', 'CA', 'IL', 'TX', 'FL'];
        $positions = ['General Manager', 'Operations Director', 'Sales Manager'];
        $names = ['John Smith', 'Jane Doe', 'Michael Johnson', 'Sarah Wilson'];

        return [
            // INFORMACIÓN DE CONTACTO PRINCIPAL (según migración)
            'email' => $this->generateCompanyEmail($companyName, $emails),
            'secondary_email' => rand(1, 100) <= 30 ? $this->generateCompanyEmail($companyName, ['support@']) : null,
            'phone' => '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999),
            'mobile_phone' => '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999),
            'fax' => rand(1, 100) <= 20 ? '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999) : null,
            
            // DIRECCIÓN FÍSICA COMPLETA (según migración)
            'address_line_1' => rand(100, 9999) . ' ' . ['Main St', 'Oak Ave', 'First St', 'Park Ave'][array_rand(['Main St', 'Oak Ave', 'First St', 'Park Ave'])],
            'address_line_2' => rand(1, 100) <= 25 ? 'Suite ' . rand(100, 999) : null,
            'city' => $cities[array_rand($cities)],
            'state_province' => $states[array_rand($states)],
            'postal_code' => rand(10000, 99999),
            'latitude' => null,
            'longitude' => null,
            
            // PERSONA DE CONTACTO (según migración)
            'contact_person_name' => $names[array_rand($names)],
            'contact_person_position' => $positions[array_rand($positions)],
            'contact_person_phone' => '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999),
            'contact_person_email' => strtolower(str_replace(' ', '.', $names[array_rand($names)])) . '@example.com',
            
            // HORARIOS Y CONFIGURACIÓN (según migración)
            'business_hours' => $this->generateBusinessHours(),
            'timezone' => 'UTC',
            
            // PREFERENCIAS DE COMUNICACIÓN (según migración)
            'communication_preferences' => $this->generateCommunicationPreferences(),
            'accepts_email_notifications' => rand(1, 100) <= 80,
            'accepts_sms_notifications' => rand(1, 100) <= 40,
            
            // OBSERVACIONES Y NOTAS (según migración)
            'notes' => $this->generateContactNotes($isPrimary),
            'internal_notes' => null,
        ];
    }

    /**
     * Genera un email tipo empresa (ej: ventas@empresa.com)
     */
    protected function generateCompanyEmail(string $companyName, array $prefixes = ['info@', 'contacto@', 'ventas@']): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', Str::slug($companyName)));
        $prefix = $prefixes[array_rand($prefixes)];
        return $prefix . $slug . '.com';
    }

    protected function generateArgentinianPhone(): string
    {
        $areaCodes = ['11', '223', '261', '341', '351', '387', '379'];
        $area = $areaCodes[array_rand($areaCodes)];
        $number = str_pad(mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        return "0{$area}-{$number}";
    }

    protected function generateArgentinianMobile(): string
    {
        $areaCodes = ['11', '223', '261', '341', '351', '387', '379'];
        $area = $areaCodes[array_rand($areaCodes)];
        $number = str_pad(mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        return "+549{$area}{$number}";
    }

    protected function displaySummary()
    {
        $this->command->info("📞 Contactos generados correctamente.");
    }

    protected function generateAddressLine2(): ?string
    {
        $extras = ['Piso 1', 'Oficina 3B', 'Departamento A', null, null];
        return $extras[array_rand($extras)];
    }

    protected function generateArgentinianAddress(): string
    {
        $streets = ['Av. Corrientes', 'Calle Belgrano', 'Ruta 2', 'Av. Colón', 'Calle San Martín'];
        $numbers = rand(100, 9999);
        return "{$streets[array_rand($streets)]} {$numbers}";
    }

    protected function generateArgentinianPostalCode(): string
    {
        return str_pad(rand(1000, 1999), 4, '0', STR_PAD_LEFT);
    }

    protected function generateBusinessHours(): string
    {
        return "Lunes a Viernes de 09:00 a 17:00";
    }

    protected function generateCommunicationPreferences(): string
    {
        $options = ['email', 'phone', 'whatsapp'];
        return $options[array_rand($options)];
    }

    protected function generateContactNotes(): ?string
    {
        $notes = [null, 'Cliente importante', 'Verificar dirección antes de envío', 'Prefiere contacto por mail'];
        return $notes[array_rand($notes)];
    }

    protected function generateParaguayanAddress(): string
    {
        $streets = ['Av. Mcal. López', 'Calle Palma', 'Ruta Transchaco', 'Av. República Argentina'];
        $numbers = rand(100, 9999);
        return "{$streets[array_rand($streets)]} {$numbers}";
    }

    protected function generateParaguayanMobile(): string
    {
        $prefixes = ['+595971', '+595981', '+595961'];
        return $prefixes[array_rand($prefixes)] . rand(100000, 999999);
    }

    protected function generateParaguayanPhone(): string
    {
        $prefixes = ['021', '061', '052'];
        return '0' . $prefixes[array_rand($prefixes)] . '-' . rand(200000, 999999);
    }

    protected function generateParaguayanPostalCode(): string
    {
        return str_pad(rand(1000, 1999), 4, '0', STR_PAD_LEFT);
    }

    protected function generatePersonalEmail(string $name = null): string
    {
        $domains = ['gmail.com', 'hotmail.com', 'yahoo.com'];
        $localPart = 'usuario' . rand(100, 999);
        return "{$localPart}@" . $domains[array_rand($domains)];
    }

    protected function isLargeCompany(string $businessName = null): bool
    {
        return rand(0, 1) === 1;
    }

}