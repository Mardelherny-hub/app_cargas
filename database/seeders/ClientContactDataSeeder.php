<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\ClientContactData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SEEDER CORREGIDO - Información de contacto para clientes
 * 
 * CORRECCIÓN CRÍTICA:
 * - ✅ CORREGIDO: client_type → client_roles (JSON array)
 * - ✅ ADAPTADO: Manejo de múltiples roles de cliente
 * - ✅ MANTIENE: Generación realista de datos de contacto AR/PY
 * 
 * Compatible con webservices AR/PY y nueva estructura de roles
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
        $contactData = $this->generateContactData($client, $isArgentinian, $isParaguayan, true);
        
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
        $contactData = $this->generateContactData($client, $isArgentinian, $isParaguayan, false);
        
        return ClientContactData::create(array_merge($contactData, [
            'client_id' => $client->id,
            'is_primary' => false,
            'active' => rand(1, 100) <= 90, // 90% activo
            'verified' => rand(1, 100) <= 60, // 60% verificado
            'verified_at' => rand(1, 100) <= 60 ? now()->subDays(rand(1, 90)) : null,
            'created_by_user_id' => $user->id,
        ]));
    }

    /**
     * Generar datos de contacto realistas según el país
     */
    private function generateContactData(Client $client, bool $isArgentinian, bool $isParaguayan, bool $isPrimary): array
    {
        $companyName = $client->legal_name;
        // CORRECCIÓN: Usar client_roles en lugar de client_type
        $clientRoles = $client->client_roles ?? [];

        if ($isArgentinian) {
            return $this->generateArgentinianContactData($companyName, $clientRoles, $isPrimary);
        } elseif ($isParaguayan) {
            return $this->generateParaguayanContactData($companyName, $clientRoles, $isPrimary);
        }

        return $this->generateGenericContactData($companyName, $clientRoles, $isPrimary);
    }

    /**
     * Generar datos de contacto para empresas argentinas
     * CORRECCIÓN: Recibe array de roles en lugar de string
     */
    private function generateArgentinianContactData(string $companyName, array $clientRoles, bool $isPrimary): array
    {
        $emails = [
            'administracion@' . $this->generateEmailDomain($companyName),
            'operaciones@' . $this->generateEmailDomain($companyName),
            'comercial@' . $this->generateEmailDomain($companyName),
            'logistica@' . $this->generateEmailDomain($companyName),
            'ventas@' . $this->generateEmailDomain($companyName),
        ];

        // CORRECCIÓN: Obtener posiciones basadas en roles múltiples
        $positions = $this->getPositionsByClientRoles($clientRoles);
        $argentinianNames = [
            'Carlos Rodríguez', 'Ana María González', 'Roberto Silva', 'María José Fernández',
            'Jorge Luis Martín', 'Silvana López', 'Eduardo Pérez', 'Claudia Morales',
            'Diego Romero', 'Patricia Ruiz', 'Fernando Castro', 'Mónica Herrera',
            'Alejandro Torres', 'Susana Díaz', 'Marcelo Jiménez', 'Valeria Sánchez'
        ];

        $argentinianCities = [
            'Buenos Aires', 'Rosario', 'Córdoba', 'La Plata', 'San Miguel de Tucumán',
            'Mar del Plata', 'Salta', 'Santa Fe', 'San Juan', 'Resistencia'
        ];

        $argentinianProvinces = [
            'Buenos Aires', 'Santa Fe', 'Córdoba', 'Mendoza', 'Tucumán',
            'Entre Ríos', 'Salta', 'Chaco', 'San Juan', 'Corrientes'
        ];

        return [
            'email' => $emails[array_rand($emails)],
            'secondary_email' => !$isPrimary ? $emails[array_rand($emails)] : null,
            'phone' => $this->generateArgentinianPhone(),
            'mobile_phone' => $this->generateArgentinianMobile(),
            'fax' => rand(1, 100) <= 30 ? $this->generateArgentinianPhone() : null,
            'address_line_1' => $this->generateArgentinianAddress(),
            'address_line_2' => rand(1, 100) <= 40 ? $this->generateAddressLine2() : null,
            'city' => $argentinianCities[array_rand($argentinianCities)],
            'state_province' => $argentinianProvinces[array_rand($argentinianProvinces)],
            'postal_code' => $this->generateArgentinianPostalCode(),
            'contact_person_name' => $argentinianNames[array_rand($argentinianNames)],
            'contact_person_position' => $positions[array_rand($positions)],
            'contact_person_phone' => $this->generateArgentinianMobile(),
            'contact_person_email' => $this->generatePersonalEmail(),
            'business_hours' => $this->generateBusinessHours(),
            'timezone' => 'America/Argentina/Buenos_Aires',
            'communication_preferences' => $this->generateCommunicationPreferences(),
            'accepts_email_notifications' => rand(1, 100) <= 85,
            'accepts_sms_notifications' => rand(1, 100) <= 60,
            'notes' => $this->generateContactNotes($isPrimary),
        ];
    }

    /**
     * Generar datos de contacto para empresas paraguayas
     * CORRECCIÓN: Recibe array de roles en lugar de string
     */
    private function generateParaguayanContactData(string $companyName, array $clientRoles, bool $isPrimary): array
    {
        $emails = [
            'admin@' . $this->generateEmailDomain($companyName),
            'operaciones@' . $this->generateEmailDomain($companyName),
            'comercial@' . $this->generateEmailDomain($companyName),
            'logistica@' . $this->generateEmailDomain($companyName),
        ];

        // CORRECCIÓN: Obtener posiciones basadas en roles múltiples
        $positions = $this->getPositionsByClientRoles($clientRoles);
        $paraguayanNames = [
            'José María Benítez', 'Ana Rosa Cáceres', 'Carlos Alberto Franco', 'María Elena Duarte',
            'Luis Fernando Aguilar', 'Rosa María Villalba', 'Miguel Ángel Rodríguez', 'Carmen López',
            'Roberto Carlos Ayala', 'Miriam Beatriz Ovelar', 'Sergio Daniel Martínez', 'Liz Noguera'
        ];

        $paraguayanCities = [
            'Asunción', 'Ciudad del Este', 'San Lorenzo', 'Luque', 'Capiatá',
            'Lambaré', 'Fernando de la Mora', 'Nemby', 'Encarnación', 'Pedro Juan Caballero'
        ];

        $paraguayanDepartments = [
            'Central', 'Alto Paraná', 'Itapúa', 'Caaguazú', 'Paraguarí',
            'Cordillera', 'Guairá', 'Misiones', 'Ñeembucú', 'Amambay'
        ];

        return [
            'email' => $emails[array_rand($emails)],
            'secondary_email' => !$isPrimary ? $emails[array_rand($emails)] : null,
            'phone' => $this->generateParaguayanPhone(),
            'mobile_phone' => $this->generateParaguayanMobile(),
            'fax' => rand(1, 100) <= 25 ? $this->generateParaguayanPhone() : null,
            'address_line_1' => $this->generateParaguayanAddress(),
            'address_line_2' => rand(1, 100) <= 30 ? $this->generateAddressLine2() : null,
            'city' => $paraguayanCities[array_rand($paraguayanCities)],
            'state_province' => $paraguayanDepartments[array_rand($paraguayanDepartments)],
            'postal_code' => $this->generateParaguayanPostalCode(),
            'contact_person_name' => $paraguayanNames[array_rand($paraguayanNames)],
            'contact_person_position' => $positions[array_rand($positions)],
            'contact_person_phone' => $this->generateParaguayanMobile(),
            'contact_person_email' => $this->generatePersonalEmail(),
            'business_hours' => $this->generateBusinessHours(),
            'timezone' => 'America/Asuncion',
            'communication_preferences' => $this->generateCommunicationPreferences(),
            'accepts_email_notifications' => rand(1, 100) <= 85,
            'accepts_sms_notifications' => rand(1, 100) <= 50,
            'notes' => $this->generateContactNotes($isPrimary),
        ];
    }

    /**
     * Generar datos genéricos de contacto
     * CORRECCIÓN: Recibe array de roles en lugar de string
     */
    private function generateGenericContactData(string $companyName, array $clientRoles, bool $isPrimary): array
    {
        // Usar nombre genérico si companyName está vacío
        $safeName = !empty($companyName) ? $companyName : 'Cliente-' . rand(1000, 9999);
        
        return [
            'email' => 'contacto@' . $this->generateEmailDomain($safeName),
            'phone' => '+000 00 0000-0000',
            'contact_person_name' => 'Contacto Principal',
            'contact_person_position' => 'Gerente General',
            'address_line_1' => 'Dirección no especificada',
            'city' => 'Ciudad no especificada',
            'timezone' => 'UTC',
            'accepts_email_notifications' => true,
            'accepts_sms_notifications' => false,
        ];
    }

    /**
     * CORRECCIÓN: Obtener posiciones basadas en array de roles en lugar de string
     */
    private function getPositionsByClientRoles(array $clientRoles): array
    {
        $allPositions = [
            'Gerente General',
            'Director Comercial',
            'Jefe de Operaciones',
            'Coordinador Logístico',
            'Responsable de Importaciones',
            'Responsable de Exportaciones',
        ];

        $roleSpecificPositions = [];

        // Posiciones específicas según roles
        if (in_array('shipper', $clientRoles)) {
            $roleSpecificPositions = array_merge($roleSpecificPositions, [
                'Gerente de Exportaciones',
                'Coordinador de Embarques',
                'Jefe de Logística Saliente',
                'Responsable de Despachos',
            ]);
        }

        if (in_array('consignee', $clientRoles)) {
            $roleSpecificPositions = array_merge($roleSpecificPositions, [
                'Gerente de Importaciones', 
                'Coordinador de Recepciones',
                'Jefe de Logística Entrante',
                'Responsable de Recepciones',
            ]);
        }

        if (in_array('notify_party', $clientRoles)) {
            $roleSpecificPositions = array_merge($roleSpecificPositions, [
                'Coordinador de Comunicaciones',
                'Responsable de Notificaciones',
                'Jefe de Seguimiento',
            ]);
        }

        // Combinar posiciones generales con específicas
        $combinedPositions = array_merge($allPositions, $roleSpecificPositions);
        
        // Eliminar duplicados y retornar
        return array_unique($combinedPositions);
    }

    /**
     * Generar dominio de email basado en el nombre de la empresa
     */
    private function generateEmailDomain(string $companyName): string
    {
        // Manejar nombres vacíos o solo espacios
        if (empty(trim($companyName))) {
            $companyName = 'empresa' . rand(1000, 9999);
        }
        
        $domain = strtolower(trim($companyName));
        // Remover caracteres especiales y espacios
        $domain = preg_replace('/[^a-z0-9]/', '', $domain);
        
        // Asegurar que el dominio tenga al menos 3 caracteres
        if (strlen($domain) < 3) {
            $domain = 'empresa' . rand(100, 999);
        }
        
        // Truncar si es muy largo
        $domain = substr($domain, 0, 15);
        
        return $domain . '.com.ar';
    }

    /**
     * Generar teléfono argentino
     */
    private function generateArgentinianPhone(): string
    {
        $areaCodes = ['011', '0223', '0341', '0351', '0381', '0261', '0342'];
        return $areaCodes[array_rand($areaCodes)] . ' ' . rand(1000, 9999) . '-' . rand(1000, 9999);
    }

    /**
     * Generar celular argentino
     */
    private function generateArgentinianMobile(): string
    {
        return '+54 9 ' . rand(11, 99) . ' ' . rand(1000, 9999) . '-' . rand(1000, 9999);
    }

    /**
     * Generar dirección argentina
     */
    private function generateArgentinianAddress(): string
    {
        $streets = ['Av. Corrientes', 'Av. Santa Fe', 'Av. Rivadavia', 'Av. Cabildo', 'Sarmiento', 'San Martín', 'Belgrano', 'Mitre'];
        return $streets[array_rand($streets)] . ' ' . rand(100, 9999);
    }

    /**
     * Generar código postal argentino
     */
    private function generateArgentinianPostalCode(): string
    {
        return 'C' . rand(1000, 1900) . chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90));
    }

    /**
     * Generar teléfono paraguayo
     */
    private function generateParaguayanPhone(): string
    {
        return '+595 21 ' . rand(100, 999) . ' ' . rand(100, 999);
    }

    /**
     * Generar celular paraguayo
     */
    private function generateParaguayanMobile(): string
    {
        return '+595 9' . rand(71, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999);
    }

    /**
     * Generar dirección paraguaya
     */
    private function generateParaguayanAddress(): string
    {
        $streets = ['Av. Mariscal López', 'Av. España', 'Av. Brasilia', 'General Díaz', 'Eligio Ayala', 'Palma', 'Chile', 'Oliva'];
        return $streets[array_rand($streets)] . ' ' . rand(100, 9999);
    }

    /**
     * Generar código postal paraguayo
     */
    private function generateParaguayanPostalCode(): string
    {
        return rand(1000, 9999);
    }

    /**
     * Generar segunda línea de dirección
     */
    private function generateAddressLine2(): string
    {
        $options = ['Piso ' . rand(1, 20), 'Oficina ' . rand(1, 50), 'Depto. ' . chr(rand(65, 90)), 'Local ' . rand(1, 20)];
        return $options[array_rand($options)];
    }

    /**
     * Generar email personal
     */
    private function generatePersonalEmail(): string
    {
        $names = ['carlos', 'ana', 'roberto', 'maria', 'jorge', 'patricia', 'diego', 'claudia'];
        $domains = ['gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com'];
        return $names[array_rand($names)] . rand(1, 99) . '@' . $domains[array_rand($domains)];
    }

    /**
     * Generar horarios de negocio
     */
    private function generateBusinessHours(): array
    {
        return [
            'monday' => ['open' => '08:00', 'close' => '17:00'],
            'tuesday' => ['open' => '08:00', 'close' => '17:00'],
            'wednesday' => ['open' => '08:00', 'close' => '17:00'],
            'thursday' => ['open' => '08:00', 'close' => '17:00'],
            'friday' => ['open' => '08:00', 'close' => '17:00'],
            'saturday' => rand(1, 100) <= 40 ? ['open' => '09:00', 'close' => '13:00'] : null,
            'sunday' => null,
        ];
    }

    /**
     * Generar preferencias de comunicación
     */
    private function generateCommunicationPreferences(): array
    {
        return [
            'preferred_language' => rand(1, 100) <= 95 ? 'es' : 'en',
            'preferred_time' => ['09:00', '17:00'],
            'emergency_contact' => rand(1, 100) <= 30,
            'marketing_consent' => rand(1, 100) <= 40,
        ];
    }

    /**
     * Generar notas de contacto
     */
    private function generateContactNotes(bool $isPrimary): ?string
    {
        if (rand(1, 100) <= 30) {
            $notes = [
                'Contactar preferentemente por email',
                'Disponible en horario comercial únicamente',
                'Solicitar confirmación de recepción',
                'Contacto principal para operaciones urgentes',
                'Verificar horarios antes de llamar',
            ];
            
            return $notes[array_rand($notes)];
        }
        
        return null;
    }

    /**
     * Verificar si es una empresa grande
     */
    private function isLargeCompany(string $companyName): bool
    {
        $largeCompanies = [
            'MAERSK', 'TERMINAL', 'CARGILL', 'NAVIERA', 'LOGÍSTICA', 'AGENCIA',
            'FRIGORÍFICO', 'CONTAINERS', 'IMPORTADORA', 'EXPORTADOR'
        ];
        
        foreach ($largeCompanies as $large) {
            if (strpos(strtoupper($companyName), $large) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Mostrar resumen de la ejecución
     */
    private function displaySummary(): void
    {
        $totalContacts = ClientContactData::count();
        $primaryContacts = ClientContactData::where('is_primary', true)->count();
        $secondaryContacts = ClientContactData::where('is_primary', false)->count();
        $verifiedContacts = ClientContactData::where('verified', true)->count();
        $argentinianContacts = ClientContactData::whereHas('client', function($q) {
            $q->whereHas('country', function($q2) {
                $q2->where('alpha2_code', 'AR');
            });
        })->count();
        $paraguayanContacts = ClientContactData::whereHas('client', function($q) {
            $q->whereHas('country', function($q2) {
                $q2->where('alpha2_code', 'PY');
            });
        })->count();

        $this->command->info('');
        $this->command->info('📊 RESUMEN DE CONTACTOS CREADOS:');
        $this->command->line("   Total de contactos: {$totalContacts}");
        $this->command->line("   Contactos primarios: {$primaryContacts}");
        $this->command->line("   Contactos secundarios: {$secondaryContacts}");
        $this->command->line("   Contactos verificados: {$verifiedContacts}");
        $this->command->line("   Contactos argentinos: {$argentinianContacts}");
        $this->command->line("   Contactos paraguayos: {$paraguayanContacts}");
        $this->command->info('');
    }
}