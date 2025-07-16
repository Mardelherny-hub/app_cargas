<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\ClientContactData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para crear información de contacto realista para clientes existentes
 * 
 * Se ejecuta después de ClientsSeeder para agregar emails, teléfonos,
 * direcciones y preferencias de comunicación a empresas argentinas y paraguayas
 * 
 * Compatible con webservices AR/PY
 */
class ClientContactDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📞 Creando información de contacto para clientes...');

        // Verificar que existan clientes con business_name válido
        $clients = Client::with('country')
                        ->whereNotNull('business_name')
                        ->where('business_name', '!=', '')
                        ->get();
        
        if ($clients->isEmpty()) {
            $this->command->error('❌ No se encontraron clientes con business_name válido. Ejecute ClientsSeeder primero.');
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
        // Validar que el cliente tenga business_name
        if (empty($client->business_name)) {
            $this->command->warn("  ⚠️ Cliente ID {$client->id} sin business_name - omitido");
            return;
        }

        $isArgentinian = $client->country && $client->country->alpha2_code === 'AR';
        $isParaguayan = $client->country && $client->country->alpha2_code === 'PY';

        // Crear contacto principal
        try {
            $primaryContact = $this->createPrimaryContact($client, $user, $isArgentinian, $isParaguayan);
            $this->command->line("  ✓ Contacto principal: {$client->business_name}");

            // 70% de probabilidad de tener contacto secundario para empresas grandes
            if ($this->isLargeCompany($client->business_name) && rand(1, 100) <= 70) {
                $this->createSecondaryContact($client, $user, $isArgentinian, $isParaguayan);
                $this->command->line("    + Contacto secundario agregado");
            }
        } catch (\Exception $e) {
            $this->command->error("  ❌ Error al crear contacto para {$client->business_name}: " . $e->getMessage());
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
        $companyName = $client->business_name;
        $clientType = $client->client_type;

        if ($isArgentinian) {
            return $this->generateArgentinianContactData($companyName, $clientType, $isPrimary);
        } elseif ($isParaguayan) {
            return $this->generateParaguayanContactData($companyName, $clientType, $isPrimary);
        }

        return $this->generateGenericContactData($companyName, $clientType, $isPrimary);
    }

    /**
     * Generar datos de contacto para empresas argentinas
     */
    private function generateArgentinianContactData(string $companyName, string $clientType, bool $isPrimary): array
    {
        $emails = [
            'administracion@' . $this->generateEmailDomain($companyName),
            'operaciones@' . $this->generateEmailDomain($companyName),
            'comercial@' . $this->generateEmailDomain($companyName),
            'logistica@' . $this->generateEmailDomain($companyName),
            'ventas@' . $this->generateEmailDomain($companyName),
        ];

        $positions = $this->getPositionsByClientType($clientType);
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
            'accepts_email_notifications' => rand(1, 100) <= 90,
            'accepts_sms_notifications' => rand(1, 100) <= 60,
            'notes' => $this->generateContactNotes($isPrimary),
        ];
    }

    /**
     * Generar datos de contacto para empresas paraguayas
     */
    private function generateParaguayanContactData(string $companyName, string $clientType, bool $isPrimary): array
    {
        $emails = [
            'administracion@' . $this->generateEmailDomain($companyName),
            'operaciones@' . $this->generateEmailDomain($companyName),
            'comercial@' . $this->generateEmailDomain($companyName),
            'ventas@' . $this->generateEmailDomain($companyName),
            'gerencia@' . $this->generateEmailDomain($companyName),
        ];

        $positions = $this->getPositionsByClientType($clientType);
        $paraguayanNames = [
            'José Luis Benítez', 'Carmen Sosa', 'Miguel Ángel Flores', 'Rosa Mendoza',
            'Ramón González', 'Lourdes Martínez', 'Alfredo Cabrera', 'Mirta Villalba',
            'Oscar Ayala', 'Graciela Valdez', 'Rubén Acosta', 'Norma Cáceres',
            'Enrique Romero', 'Estela Gómez', 'Víctor Duarte', 'Elida Montiel'
        ];

        $paraguayanCities = [
            'Asunción', 'Ciudad del Este', 'San Lorenzo', 'Luque', 'Capiatá',
            'Lambaré', 'Fernando de la Mora', 'Nemby', 'Encarnación', 'Pedro Juan Caballero'
        ];

        $paraguayanDepartments = [
            'Central', 'Alto Paraná', 'Itapúa', 'Cordillera', 'Paraguarí',
            'Guairá', 'Caazapá', 'Misiones', 'Ñeembucú', 'Amambay'
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
     */
    private function generateGenericContactData(string $companyName, string $clientType, bool $isPrimary): array
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
        
        // Limitar longitud del dominio
        $domain = substr($domain, 0, 15);
        
        $extensions = ['com.ar', 'com.py', 'com', 'net', 'org'];
        return $domain . '.' . $extensions[array_rand($extensions)];
    }

    /**
     * Obtener posiciones según el tipo de cliente
     */
    private function getPositionsByClientType(string $clientType): array
    {
        switch ($clientType) {
            case 'shipper':
                return [
                    'Gerente de Exportaciones', 'Jefe de Logística', 'Coordinador de Embarques',
                    'Encargado de Comercio Exterior', 'Supervisor de Despachos'
                ];
            case 'consignee':
                return [
                    'Gerente de Importaciones', 'Jefe de Operaciones', 'Coordinador de Recepciones',
                    'Encargado de Almacenes', 'Supervisor de Descargas'
                ];
            case 'notify_party':
                return [
                    'Coordinador de Notificaciones', 'Jefe de Comunicaciones', 'Encargado de Seguimiento',
                    'Supervisor de Tráfico', 'Responsable de Clientes'
                ];
            case 'owner':
                return [
                    'Propietario', 'Gerente General', 'Director Comercial',
                    'Jefe de Operaciones', 'Coordinador General'
                ];
            default:
                return ['Gerente General', 'Jefe de Operaciones', 'Coordinador'];
        }
    }

    /**
     * Generar teléfono argentino
     */
    private function generateArgentinianPhone(): string
    {
        $areaCodes = ['11', '351', '341', '221', '381', '223', '387', '342', '264', '362'];
        $areaCode = $areaCodes[array_rand($areaCodes)];
        $number = rand(1000, 9999) . '-' . rand(1000, 9999);
        return '+54 ' . $areaCode . ' ' . $number;
    }

    /**
     * Generar móvil argentino
     */
    private function generateArgentinianMobile(): string
    {
        $areaCode = rand(11, 99);
        $number = rand(1000, 9999) . '-' . rand(1000, 9999);
        return '+54 9 ' . $areaCode . ' ' . $number;
    }

    /**
     * Generar teléfono paraguayo
     */
    private function generateParaguayanPhone(): string
    {
        $number = rand(100, 999) . '-' . rand(100, 999);
        return '+595 21 ' . $number;
    }

    /**
     * Generar móvil paraguayo
     */
    private function generateParaguayanMobile(): string
    {
        $carriers = ['971', '972', '975', '981', '983', '985'];
        $carrier = $carriers[array_rand($carriers)];
        $number = rand(100, 999) . '-' . rand(100, 999);
        return '+595 ' . $carrier . ' ' . $number;
    }

    /**
     * Generar dirección argentina
     */
    private function generateArgentinianAddress(): string
    {
        $streets = [
            'Av. Corrientes', 'Av. Santa Fe', 'Av. Rivadavia', 'Av. Cabildo', 'Av. Juan B. Justo',
            'San Martín', 'Belgrano', 'Mitre', 'Sarmiento', 'Moreno', 'Tucumán', 'Paraguay',
            'Uruguay', 'Perú', 'Chile', 'Brasil', 'México', 'Venezuela'
        ];
        
        $street = $streets[array_rand($streets)];
        $number = rand(100, 9999);
        
        return $street . ' ' . $number;
    }

    /**
     * Generar dirección paraguaya
     */
    private function generateParaguayanAddress(): string
    {
        $streets = [
            'Av. Mariscal López', 'Av. España', 'Av. Brasilia', 'Av. Santísimo Sacramento',
            'Eligio Ayala', 'Palma', 'Chile', 'Estrella', 'Independencia Nacional',
            'Cerro Corá', 'General Díaz', 'Ygatimí', 'Haedo', 'Colón'
        ];
        
        $street = $streets[array_rand($streets)];
        $number = rand(100, 9999);
        
        return $street . ' ' . $number;
    }

    /**
     * Generar línea de dirección 2
     */
    private function generateAddressLine2(): string
    {
        $options = [
            'Piso ' . rand(1, 20),
            'Oficina ' . rand(1, 50),
            'Depto. ' . chr(rand(65, 90)),
            'Local ' . rand(1, 20),
            'Galpón ' . rand(1, 10),
        ];
        
        return $options[array_rand($options)];
    }

    /**
     * Generar código postal argentino
     */
    private function generateArgentinianPostalCode(): string
    {
        return chr(rand(65, 67)) . rand(1000, 9999) . chr(rand(65, 90)) . chr(rand(65, 90)) . chr(rand(65, 90));
    }

    /**
     * Generar código postal paraguayo
     */
    private function generateParaguayanPostalCode(): string
    {
        return rand(1000, 9999) . '';
    }

    /**
     * Generar email personal
     */
    private function generatePersonalEmail(): string
    {
        $providers = ['gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com'];
        $names = ['juan', 'maria', 'carlos', 'ana', 'luis', 'sofia', 'diego', 'laura'];
        
        $name = $names[array_rand($names)];
        $provider = $providers[array_rand($providers)];
        $number = rand(1, 999);
        
        return $name . $number . '@' . $provider;
    }

    /**
     * Generar horarios de atención
     */
    private function generateBusinessHours(): array
    {
        return [
            'monday' => ['open' => '08:00', 'close' => '17:00'],
            'tuesday' => ['open' => '08:00', 'close' => '17:00'],
            'wednesday' => ['open' => '08:00', 'close' => '17:00'],
            'thursday' => ['open' => '08:00', 'close' => '17:00'],
            'friday' => ['open' => '08:00', 'close' => '16:00'],
            'saturday' => rand(1, 100) <= 50 ? ['open' => '09:00', 'close' => '13:00'] : null,
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
            'ALUAR', 'SIDERAR', 'TENARIS', 'BUNGE', 'CARGILL', 'DREYFUS',
            'ARCELOR MITTAL', 'MOLINOS RIO DE LA PLATA', 'PETROPAR', 'ACEPAR'
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