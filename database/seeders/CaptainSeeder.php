<?php

namespace Database\Seeders;

use App\Models\Captain;
use App\Models\Company;
use App\Models\Country;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CaptainSeeder - MÓDULO 3: VIAJES Y CARGAS (CORREGIDO)
 * 
 * Seeder para capitanes del sistema de transporte fluvial AR/PY
 * 
 * FIX: Campos corregidos según migración create_captains_table.php:
 * - total_voyages_completed (no total_voyages)
 * - route_restrictions (no route_competencies) 
 * - first_voyage_date, daily_rate, rate_currency, safety_incidents, performance_notes
 * 
 * DATOS REALES DEL SISTEMA:
 * - Empresas: Rio de la Plata Transport S.A., Navegación Paraguay S.A.
 * - Embarcaciones: PAR13001, GUARAN F, REINA DEL PARANA
 * - Rutas: ARBUE → PYTVT (Buenos Aires → Paraguay Terminal Villeta)
 * - Competencias: Navegación fluvial, contenedores, convoy
 */
class CaptainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Creando capitanes para transporte fluvial AR/PY...');

        // Obtener países y empresas (CORREGIDO: usar alpha2_code)
        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();
        
        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Países AR/PY no encontrados. Ejecutar seeders de países primero.');
            return;
        }

        // Obtener empresas reales del sistema
        $rioPlataTransport = Company::where('tax_id', '20123456789')->first(); 
        $navegacionParaguay = Company::where('tax_id', '80987654321')->first(); 
        $logisticaIntegral = Company::where('tax_id', '30555666777')->first(); 

        if (!$rioPlataTransport || !$navegacionParaguay) {
            $this->command->error('❌ Empresas principales no encontradas. Ejecutar seeders de empresas primero.');
            return;
        }

        // Limpiar tabla existente
        DB::table('captains')->delete();

        //
        // === CAPITANES ARGENTINOS SENIORS ===
        //
        $this->createCaptainsData([
            [
                'first_name' => 'Carlos Eduardo',
                'last_name' => 'Rodriguez Martinez',
                'birth_date' => '1968-03-15',
                'gender' => 'male',
                'nationality' => 'AR',
                'blood_type' => 'O+',
                'email' => 'carlos.rodriguez@riotransport.com.ar',
                'phone' => '+54 11 4523-7890',
                'mobile_phone' => '+54 9 11 6789-5432',
                'emergency_contact_name' => 'María Elena Rodriguez',
                'emergency_contact_phone' => '+54 9 11 6789-5433',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Av. Costanera Sur 1445, Dept. 8B',
                'city' => 'Buenos Aires',
                'state_province' => 'CABA',
                'postal_code' => 'C1107',
                'country_id' => $argentina->id,
                'license_country_id' => $argentina->id,
                'primary_company_id' => $rioPlataTransport->id,
                'document_type' => 'DNI',
                'document_number' => '16789234',
                'license_number' => 'PNA-AR-001245',
                'license_class' => 'master',
                'license_status' => 'valid',
                'license_issued_at' => '2019-06-15',
                'license_expires_at' => '2025-06-15',
                'medical_certificate_expires_at' => '2025-03-20',
                'safety_training_expires_at' => '2025-08-10',
                'employment_status' => 'employed',
                'available_for_hire' => true,
                'first_voyage_date' => '1996-08-20',
                'last_voyage_date' => '2024-12-15',
                'total_voyages_completed' => 342,
                'years_of_experience' => 28,
                'performance_rating' => 4.8,
                'daily_rate' => 950.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Excelente record de seguridad y puntualidad',
                'specializations' => 'Comandante Senior Hidrovía Paraguay-Paraná, Operaciones de Convoy',
                'vessel_type_competencies' => ['barge', 'tugboat', 'pusher', 'convoy_leader'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo', 'bulk_cargo'],
                'additional_certifications' => ['STCW_VI_1', 'Dangerous_Goods', 'Bridge_Resource_Management'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Capitán senior con 28 años de experiencia en la Hidrovía. Comandante principal de PAR13001.',
            ],
            [
                'first_name' => 'Miguel Ángel',
                'last_name' => 'Fernandez Silva',
                'birth_date' => '1971-09-22',
                'gender' => 'male',
                'nationality' => 'AR',
                'blood_type' => 'A+',
                'email' => 'miguel.fernandez@riotransport.com.ar',
                'phone' => '+54 11 4756-2134',
                'mobile_phone' => '+54 9 11 5432-9876',
                'emergency_contact_name' => 'Ana Lucia Fernandez',
                'emergency_contact_phone' => '+54 9 11 5432-9877',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Calle Almirante Brown 2890',
                'city' => 'Rosario',
                'state_province' => 'Santa Fe',
                'postal_code' => 'S2000',
                'country_id' => $argentina->id,
                'license_country_id' => $argentina->id,
                'primary_company_id' => $rioPlataTransport->id,
                'document_type' => 'DNI',
                'document_number' => '18234567',
                'license_number' => 'PNA-AR-001456',
                'license_class' => 'master',
                'license_status' => 'valid',
                'license_issued_at' => '2020-03-10',
                'license_expires_at' => '2026-03-10',
                'medical_certificate_expires_at' => '2025-01-15',
                'safety_training_expires_at' => '2025-05-20',
                'employment_status' => 'employed',
                'available_for_hire' => true,
                'first_voyage_date' => '1999-05-15',
                'last_voyage_date' => '2024-12-08',
                'total_voyages_completed' => 287,
                'years_of_experience' => 25,
                'performance_rating' => 4.7,
                'daily_rate' => 880.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 1,
                'performance_notes' => 'Especialista en operaciones complejas de remolque',
                'specializations' => 'Especialista en Remolque y Convoy, Navegación Nocturna',
                'vessel_type_competencies' => ['tugboat', 'pusher', 'convoy_leader', 'barge'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo', 'liquid_cargo'],
                'additional_certifications' => ['STCW_VI_1', 'Tug_Operations', 'Night_Navigation'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Experto en operaciones de remolque. Comandante de REINA DEL PARANA.',
            ],
            [
                'first_name' => 'Roberto Daniel',
                'last_name' => 'Gutierrez Morales',
                'birth_date' => '1975-11-08',
                'gender' => 'male',
                'nationality' => 'AR',
                'blood_type' => 'B+',
                'email' => 'roberto.gutierrez@logiintegral.com',
                'phone' => '+54 341 422-8765',
                'mobile_phone' => '+54 9 341 567-1234',
                'emergency_contact_name' => 'Silvia Beatriz Gutierrez',
                'emergency_contact_phone' => '+54 9 341 567-1235',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Bv. Oroño 1567, Piso 4',
                'city' => 'Rosario',
                'state_province' => 'Santa Fe',
                'postal_code' => 'S2000',
                'country_id' => $argentina->id,
                'license_country_id' => $argentina->id,
                'primary_company_id' => $logisticaIntegral?->id,
                'document_type' => 'DNI',
                'document_number' => '19876543',
                'license_number' => 'PNA-AR-001789',
                'license_class' => 'chief_officer',
                'license_status' => 'valid',
                'license_issued_at' => '2021-08-20',
                'license_expires_at' => '2026-08-20',
                'medical_certificate_expires_at' => '2025-06-30',
                'safety_training_expires_at' => '2025-09-15',
                'employment_status' => 'employed',
                'available_for_hire' => false,
                'first_voyage_date' => '2007-03-10',
                'last_voyage_date' => '2024-12-20',
                'total_voyages_completed' => 156,
                'years_of_experience' => 18,
                'performance_rating' => 4.6,
                'daily_rate' => 720.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Primer oficial confiable con expertise en desconsolidación',
                'specializations' => 'Primer Oficial, Operaciones de Desconsolidación',
                'vessel_type_competencies' => ['barge', 'self_propelled', 'pusher'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo'],
                'additional_certifications' => ['STCW_II_1', 'Cargo_Operations', 'Port_Operations'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Primer oficial especializado en operaciones de desconsolidación.',
            ],
        ]);

        //
        // === CAPITANES PARAGUAYOS SENIORS ===
        //
        $this->createCaptainsData([
            [
                'first_name' => 'Juan Carlos',
                'last_name' => 'Benítez Ayala',
                'birth_date' => '1969-05-30',
                'gender' => 'male',
                'nationality' => 'PY',
                'blood_type' => 'O+',
                'email' => 'juan.benitez@navegacionpy.com.py',
                'phone' => '+595 21 234-5678',
                'mobile_phone' => '+595 981 234-567',
                'emergency_contact_name' => 'Carmen Rosa Benítez',
                'emergency_contact_phone' => '+595 981 234-568',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Av. Costanera Norte 1234',
                'city' => 'Asunción',
                'state_province' => 'Distrito Capital',
                'postal_code' => '1001',
                'country_id' => $paraguay->id,
                'license_country_id' => $paraguay->id,
                'primary_company_id' => $navegacionParaguay->id,
                'document_type' => 'CI',
                'document_number' => '1234567',
                'license_number' => 'PNA-PY-000123',
                'license_class' => 'master',
                'license_status' => 'valid',
                'license_issued_at' => '2018-11-20',
                'license_expires_at' => '2025-11-20',
                'medical_certificate_expires_at' => '2025-04-15',
                'safety_training_expires_at' => '2025-07-30',
                'employment_status' => 'employed',
                'available_for_hire' => true,
                'first_voyage_date' => '1994-07-10',
                'last_voyage_date' => '2024-12-18',
                'total_voyages_completed' => 398,
                'years_of_experience' => 30,
                'performance_rating' => 4.9,
                'daily_rate' => 890.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Record excepcional, líder en operaciones de Terminal Villeta',
                'specializations' => 'Capitán Senior Terminal Villeta, Experto en Transbordos',
                'vessel_type_competencies' => ['barge', 'tugboat', 'pusher', 'convoy_leader', 'transshipment'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo', 'bulk_cargo', 'transshipment'],
                'additional_certifications' => ['STCW_VI_1', 'Transshipment_Operations', 'Terminal_Operations'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Capitán senior con 30 años de experiencia. Especialista en Terminal TERPORT VILLETA.',
            ],
            [
                'first_name' => 'Pedro Antonio',
                'last_name' => 'Silva Recalde',
                'birth_date' => '1973-12-03',
                'gender' => 'male',
                'nationality' => 'PY',
                'blood_type' => 'A-',
                'email' => 'pedro.silva@navegacionpy.com.py',
                'phone' => '+595 61 345-6789',
                'mobile_phone' => '+595 982 345-678',
                'emergency_contact_name' => 'Mercedes Silva',
                'emergency_contact_phone' => '+595 982 345-679',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Terminal Portuario Villeta, Zona Industrial',
                'city' => 'Villeta',
                'state_province' => 'Central',
                'postal_code' => '2680',
                'country_id' => $paraguay->id,
                'license_country_id' => $paraguay->id,
                'primary_company_id' => $navegacionParaguay->id,
                'document_type' => 'CI',
                'document_number' => '2345678',
                'license_number' => 'PNA-PY-000234',
                'license_class' => 'master',
                'license_status' => 'valid',
                'license_issued_at' => '2019-07-15',
                'license_expires_at' => '2025-07-15',
                'medical_certificate_expires_at' => '2025-02-28',
                'safety_training_expires_at' => '2025-06-10',
                'employment_status' => 'employed',
                'available_for_hire' => true,
                'first_voyage_date' => '2003-01-20',
                'last_voyage_date' => '2024-12-10',
                'total_voyages_completed' => 234,
                'years_of_experience' => 22,
                'performance_rating' => 4.7,
                'daily_rate' => 820.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Comandante confiable especializado en convoy internacional',
                'specializations' => 'Comandante GUARAN F, Especialista en Convoy Internacional',
                'vessel_type_competencies' => ['barge', 'pusher', 'convoy_leader'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo'],
                'additional_certifications' => ['STCW_VI_1', 'International_Convoy', 'Paraguay_River_Navigation'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Comandante principal de GUARAN F. Experto en convoy internacional.',
            ],
        ]);

        //
        // === CAPITANES JÓVENES EN DESARROLLO ===
        //
        $this->createCaptainsData([
            [
                'first_name' => 'Leonardo Matías',
                'last_name' => 'Gonzalez Paz',
                'birth_date' => '1985-07-14',
                'gender' => 'male',
                'nationality' => 'AR',
                'blood_type' => 'AB+',
                'email' => 'leonardo.gonzalez@riotransport.com.ar',
                'phone' => '+54 11 5789-4321',
                'mobile_phone' => '+54 9 11 6543-2198',
                'emergency_contact_name' => 'Marcos Gonzalez',
                'emergency_contact_phone' => '+54 9 11 6543-2199',
                'emergency_contact_relationship' => 'Padre',
                'address' => 'Av. Paseo Colón 850, Dept. 12A',
                'city' => 'Buenos Aires',
                'state_province' => 'CABA',
                'postal_code' => 'C1063',
                'country_id' => $argentina->id,
                'license_country_id' => $argentina->id,
                'primary_company_id' => $rioPlataTransport->id,
                'document_type' => 'DNI',
                'document_number' => '32145678',
                'license_number' => 'PNA-AR-002134',
                'license_class' => 'officer',
                'license_status' => 'valid',
                'license_issued_at' => '2022-09-10',
                'license_expires_at' => '2027-09-10',
                'medical_certificate_expires_at' => '2025-12-01',
                'safety_training_expires_at' => '2025-11-15',
                'employment_status' => 'employed',
                'available_for_hire' => true,
                'first_voyage_date' => '2017-03-25',
                'last_voyage_date' => '2024-12-05',
                'total_voyages_completed' => 89,
                'years_of_experience' => 8,
                'performance_rating' => 4.3,
                'daily_rate' => 450.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Oficial joven con excelente dominio de tecnologías modernas',
                'specializations' => 'Oficial de Navegación, Especialista en Tecnología Marítima',
                'vessel_type_competencies' => ['barge', 'tugboat', 'self_propelled'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo'],
                'additional_certifications' => ['STCW_II_1', 'ECDIS', 'Modern_Navigation_Systems'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Oficial joven con excelente formación en nuevas tecnologías.',
            ],
            [
                'first_name' => 'María Fernanda',
                'last_name' => 'Acosta Villalba',
                'birth_date' => '1987-02-25',
                'gender' => 'female',
                'nationality' => 'PY',
                'blood_type' => 'O-',
                'email' => 'maria.acosta@navegacionpy.com.py',
                'phone' => '+595 21 456-7890',
                'mobile_phone' => '+595 983 456-789',
                'emergency_contact_name' => 'Luis Acosta',
                'emergency_contact_phone' => '+595 983 456-790',
                'emergency_contact_relationship' => 'Padre',
                'address' => 'Calle Palma 1456, Barrio Recoleta',
                'city' => 'Asunción',
                'state_province' => 'Distrito Capital',
                'postal_code' => '1209',
                'country_id' => $paraguay->id,
                'license_country_id' => $paraguay->id,
                'primary_company_id' => $navegacionParaguay->id,
                'document_type' => 'CI',
                'document_number' => '4567890',
                'license_number' => 'PNA-PY-000456',
                'license_class' => 'chief_officer',
                'license_status' => 'valid',
                'license_issued_at' => '2023-04-20',
                'license_expires_at' => '2028-04-20',
                'medical_certificate_expires_at' => '2025-10-30',
                'safety_training_expires_at' => '2025-12-20',
                'employment_status' => 'employed',
                'available_for_hire' => true,
                'first_voyage_date' => '2019-01-15',
                'last_voyage_date' => '2024-12-12',
                'total_voyages_completed' => 67,
                'years_of_experience' => 6,
                'performance_rating' => 4.5,
                'daily_rate' => 520.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Primera oficial femenina, liderazgo excepcional y visión ambiental',
                'specializations' => 'Primera Oficial, Pionera Femenina en Navegación Fluvial',
                'vessel_type_competencies' => ['barge', 'self_propelled', 'pusher'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo'],
                'additional_certifications' => ['STCW_II_1', 'Leadership_Training', 'Environmental_Protection'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Primera oficial femenina. Destacada en liderazgo y protección ambiental.',
            ],
        ]);

        //
        // === CAPITANES FREELANCE/DISPONIBLES ===
        //
        $this->createCaptainsData([
            [
                'first_name' => 'Alberto Ramón',
                'last_name' => 'Mendoza Torres',
                'birth_date' => '1965-10-12',
                'gender' => 'male',
                'nationality' => 'AR',
                'blood_type' => 'A+',
                'email' => 'alberto.mendoza@freelance.mar.ar',
                'phone' => '+54 376 442-3456',
                'mobile_phone' => '+54 9 376 567-8901',
                'emergency_contact_name' => 'Norma Mendoza',
                'emergency_contact_phone' => '+54 9 376 567-8902',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Puerto de Posadas, Zona Portuaria',
                'city' => 'Posadas',
                'state_province' => 'Misiones',
                'postal_code' => 'N3300',
                'country_id' => $argentina->id,
                'license_country_id' => $argentina->id,
                'primary_company_id' => null, // Freelance
                'document_type' => 'DNI',
                'document_number' => '14567890',
                'license_number' => 'PNA-AR-000789',
                'license_class' => 'master',
                'license_status' => 'valid',
                'license_issued_at' => '2017-12-05',
                'license_expires_at' => '2025-12-05',
                'medical_certificate_expires_at' => '2025-08-20',
                'safety_training_expires_at' => '2025-10-15',
                'employment_status' => 'freelance',
                'available_for_hire' => true,
                'first_voyage_date' => '1989-03-15',
                'last_voyage_date' => '2024-11-28',
                'total_voyages_completed' => 456,
                'years_of_experience' => 35,
                'performance_rating' => 4.8,
                'daily_rate' => 1200.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 2,
                'performance_notes' => 'Capitán veterano con vasta experiencia en Alto Paraná',
                'specializations' => 'Capitán Freelance Senior, Especialista en Alto Paraná',
                'vessel_type_competencies' => ['barge', 'tugboat', 'pusher', 'convoy_leader', 'river_specialized'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['bulk_cargo', 'general_cargo', 'containers'],
                'additional_certifications' => ['STCW_VI_1', 'River_Expertise', 'Emergency_Response'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Capitán freelance con 35 años de experiencia. Especialista en Alto Paraná.',
            ],
            [
                'first_name' => 'Osvaldo Rubén',
                'last_name' => 'Báez Ortigoza',
                'birth_date' => '1978-08-19',
                'gender' => 'male',
                'nationality' => 'PY',
                'blood_type' => 'B+',
                'email' => 'osvaldo.baez@freelance.py',
                'phone' => '+595 343 567-8901',
                'mobile_phone' => '+595 984 567-890',
                'emergency_contact_name' => 'Cristina Báez',
                'emergency_contact_phone' => '+595 984 567-891',
                'emergency_contact_relationship' => 'Esposa',
                'address' => 'Ruta 1, Km 45, Puerto San Antonio',
                'city' => 'San Antonio',
                'state_province' => 'Central',
                'postal_code' => '2720',
                'country_id' => $paraguay->id,
                'license_country_id' => $paraguay->id,
                'primary_company_id' => null, // Freelance
                'document_type' => 'CI',
                'document_number' => '3456789',
                'license_number' => 'PNA-PY-000567',
                'license_class' => 'master',
                'license_status' => 'valid',
                'license_issued_at' => '2020-06-30',
                'license_expires_at' => '2026-06-30',
                'medical_certificate_expires_at' => '2025-05-15',
                'safety_training_expires_at' => '2025-08-25',
                'employment_status' => 'freelance',
                'available_for_hire' => true,
                'first_voyage_date' => '2010-09-12',
                'last_voyage_date' => '2024-12-01',
                'total_voyages_completed' => 178,
                'years_of_experience' => 15,
                'performance_rating' => 4.4,
                'daily_rate' => 680.00,
                'rate_currency' => 'USD',
                'safety_incidents' => 0,
                'performance_notes' => 'Capitán freelance confiable, especializado en operaciones de emergencia',
                'specializations' => 'Capitán Freelance, Especialista en Operaciones Rápidas',
                'vessel_type_competencies' => ['barge', 'tugboat', 'pusher'],
                'route_restrictions' => null,
                'cargo_type_competencies' => ['containers', 'general_cargo'],
                'additional_certifications' => ['STCW_VI_1', 'Fast_Operations', 'Emergency_Response'],
                'active' => true,
                'verified' => true,
                'verification_notes' => 'Capitán freelance especializado en operaciones rápidas y de emergencia.',
            ],
        ]);

        $this->command->info('✅ Capitanes creados exitosamente para transporte fluvial AR/PY');
        $this->command->info('');
        $this->showCreatedSummary();
    }

    /**
     * Crear múltiples capitanes con datos base comunes
     */
    private function createCaptainsData(array $captainsData): void
    {
        foreach ($captainsData as $data) {
            // Datos base comunes para todos los capitanes
            $baseData = [
                'created_date' => now(),
                'last_updated_date' => now(),
                'created_by_user_id' => 1, // Admin
                'last_updated_by_user_id' => 1,
            ];

            // Convertir fechas string a Carbon
            $dateFields = ['birth_date', 'license_issued_at', 'license_expires_at', 
                          'medical_certificate_expires_at', 'safety_training_expires_at',
                          'first_voyage_date', 'last_voyage_date'];
            
            foreach ($dateFields as $field) {
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = Carbon::parse($data[$field]);
                }
            }

            Captain::create(array_merge($baseData, $data));
        }
    }

    /**
     * Mostrar resumen de capitanes creados
     */
    private function showCreatedSummary(): void
    {
        $totalCaptains = Captain::count();
        $argentineCaptains = Captain::whereHas('country', fn($q) => $q->where('alpha2_code', 'AR'))->count();
        $paraguayanCaptains = Captain::whereHas('country', fn($q) => $q->where('alpha2_code', 'PY'))->count();
        $masters = Captain::where('license_class', 'master')->count();
        $officers = Captain::where('license_class', 'chief_officer')->count();
        $juniorOfficers = Captain::where('license_class', 'officer')->count();
        $employed = Captain::where('employment_status', 'employed')->count();
        $freelance = Captain::where('employment_status', 'freelance')->count();

        $this->command->info('=== 🚢 RESUMEN DE CAPITANES CREADOS ===');
        $this->command->info('');
        $this->command->info("📊 Total capitanes: {$totalCaptains}");
        $this->command->info("🇦🇷 Argentinos: {$argentineCaptains}");
        $this->command->info("🇵🇾 Paraguayos: {$paraguayanCaptains}");
        $this->command->info('');
        $this->command->info('⚓ Por clase de licencia:');
        $this->command->info("   • Capitanes (Master): {$masters}");
        $this->command->info("   • Primeros Oficiales: {$officers}");
        $this->command->info("   • Oficiales: {$juniorOfficers}");
        $this->command->info('');
        $this->command->info('💼 Por estado laboral:');
        $this->command->info("   • Empleados: {$employed}");
        $this->command->info("   • Freelance: {$freelance}");
        $this->command->info('');
        $this->command->info('🛳️ CAPITANES DESTACADOS:');
        $this->command->info('   • Carlos Rodriguez - Comandante PAR13001 (28 años exp.)');
        $this->command->info('   • Juan Benítez - Especialista Terminal Villeta (30 años exp.)');
        $this->command->info('   • Pedro Silva - Comandante GUARAN F (22 años exp.)');
        $this->command->info('   • María Acosta - Primera oficial femenina (6 años exp.)');
        $this->command->info('   • Alberto Mendoza - Freelance Alto Paraná (35 años exp.)');
        $this->command->info('');
        $this->command->info('✅ Todos los capitanes tienen licencias vigentes');
        $this->command->info('✅ Competencias específicas para transporte fluvial AR/PY');
        $this->command->info('✅ Relaciones establecidas con empresas reales del sistema');
        $this->command->info('✅ CAMPOS CORREGIDOS según migración create_captains_table.php');
    }
}