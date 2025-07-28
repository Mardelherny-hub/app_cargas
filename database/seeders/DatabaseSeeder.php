<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder - SISTEMA COMPLETO DE CARGAS FLUVIALES AR/PY
 * 
 * Ejecuta todos los seeders en el orden correcto respetando dependencias FK
 * 
 * ORDEN CRÍTICO POR DEPENDENCIAS:
 * 1. Catálogos base (países, puertos, aduanas)
 * 2. Sistema de usuarios y permisos
 * 3. Tipos y propietarios (para embarcaciones)
 * 4. Embarcaciones (requiere tipos y propietarios)
 * 5. Clientes y contactos
 * 6. Dependencias básicas webservices (países específicos, MAERSK, puertos)
 * 7. Módulo 3: Capitanes, Viajes y Cargas (CON DATOS REALES PARANA)
 * 8. Módulo 4: Transacciones Webservices (CON DATOS REALES MAERSK)
 * 
 * USO: php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Iniciando población completa de la base de datos...');
        $this->command->info('');

        //
        // === FASE 1: CATÁLOGOS BASE ===
        //
        $this->command->info('📋 FASE 1: Catálogos Base');
        $this->command->line('  └── Creando países, puertos, aduanas, tipos de documento...');
        
        $this->call([
            BaseCatalogsSeeder::class,
        ]);
        
        $this->command->info('  ✅ Catálogos base completados');
        $this->command->info('');

        //
        // === FASE 2: SISTEMA DE USUARIOS Y PERMISOS ===
        //
        $this->command->info('👥 FASE 2: Sistema de Usuarios');
        $this->command->line('  └── Creando roles, permisos y usuarios de prueba...');
        
        $this->call([
            RolesAndPermissionsSeeder::class,
            TestUsersSeeder::class,
        ]);
        
        $this->command->info('  ✅ Sistema de usuarios completado');
        $this->command->info('');

        //
        // === FASE 3: TIPOS Y PROPIETARIOS (PRE-EMBARCACIONES) ===
        //
        $this->command->info('🏭 FASE 3: Tipos y Propietarios');
        $this->command->line('  └── Creando tipos de embarcaciones y propietarios...');
        
        $this->call([
            VesselTypeSeeder::class,
            VesselOwnersSeeder::class,
        ]);
        
        $this->command->info('  ✅ Tipos y propietarios completados');
        $this->command->info('');

        //
        // === FASE 4: EMBARCACIONES ===
        //
        $this->command->info('🛳️ FASE 4: Embarcaciones');
        $this->command->line('  └── Creando flota de embarcaciones fluviales...');
        
        $this->call([
            VesselSeeder::class,
        ]);
        
        $this->command->info('  ✅ Embarcaciones completadas');
        $this->command->info('');

        //
        // === FASE 5: CLIENTES ===
        //
        $this->command->info('🏢 FASE 5: Clientes');
        $this->command->line('  └── Creando empresas y clientes de Argentina y Paraguay...');
        
        $this->call([
            ClientsSeeder::class,
        ]);
        
        $this->command->info('  ✅ Clientes completados');
        $this->command->info('');

        //
        // === FASE 6: DEPENDENCIAS WEBSERVICES ===
        //
        $this->command->info('🔧 FASE 6: Dependencias Webservices');
        $this->command->line('  └── Creando empresa MAERSK, puertos específicos y usuarios...');
        
        $this->call([
            WebserviceBasicDependenciesSeeder::class,
        ]);
        
        $this->command->info('  ✅ Dependencias webservices completadas');
        $this->command->info('');

        //
        // === FASE 7: MÓDULO 3 - VIAJES Y CARGAS (DATOS REALES PARANA) ===
        //
        $this->command->info('🚢 FASE 7: Viajes y Cargas');
        $this->command->line('  └── Creando capitanes, viajes y cargas con datos reales PARANA...');
        
        $this->call([
            CaptainSeeder::class,
            VoyagesFromParanaSeeder::class,
        ]);
        
        $this->command->info('  ✅ Viajes y cargas completados');
        $this->command->info('');

        //
        // === FASE 8: MÓDULO 4 - TRANSACCIONES WEBSERVICES (DATOS REALES MAERSK) ===
        //
        $this->command->info('📡 FASE 8: Transacciones Webservices');
        $this->command->line('  └── Creando transacciones webservice con datos reales MAERSK...');
        
        $this->call([
            WebserviceTransactionsSeeder::class,
        ]);
        
        $this->command->info('  ✅ Transacciones webservices completadas');
        $this->command->info('');

        //
        // === RESUMEN FINAL ===
        //
        $this->command->info('🎯 POBLACIÓN COMPLETADA');
        $this->command->line('────────────────────────────────────────');
        
        try {
            if (class_exists('\App\Models\Country')) {
                $countries = \App\Models\Country::count();
                $this->command->line("  • Países: {$countries}");
            }
            
            if (class_exists('\App\Models\Port')) {
                $ports = \App\Models\Port::count();
                $this->command->line("  • Puertos: {$ports}");
            }
            
            if (class_exists('\App\Models\User')) {
                $users = \App\Models\User::count();
                $this->command->line("  • Usuarios: {$users}");
            }
            
            if (class_exists('\App\Models\Company')) {
                $companies = \App\Models\Company::count();
                $this->command->line("  • Empresas: {$companies}");
            }
            
            if (class_exists('\App\Models\Vessel')) {
                $vessels = \App\Models\Vessel::count();
                $this->command->line("  • Embarcaciones: {$vessels}");
            }
            
            if (class_exists('\App\Models\Client')) {
                $clients = \App\Models\Client::count();
                $this->command->line("  • Clientes: {$clients}");
            }
            
            if (class_exists('\App\Models\Captain')) {
                $captains = \App\Models\Captain::count();
                $this->command->line("  • Capitanes: {$captains}");
            }
            
            if (class_exists('\App\Models\Voyage')) {
                $voyages = \App\Models\Voyage::count();
                $this->command->line("  • Viajes: {$voyages}");
            }
            
            if (class_exists('\App\Models\Shipment')) {
                $shipments = \App\Models\Shipment::count();
                $this->command->line("  • Envíos: {$shipments}");
            }

            if (class_exists('\App\Models\WebserviceTransaction')) {
                $transactions = \App\Models\WebserviceTransaction::count();
                $this->command->line("  • Transacciones WS: {$transactions}");
            }
            
        } catch (\Exception $e) {
            $this->command->warn('  (No se pudo obtener el conteo de registros - normal en primera ejecución)');
        }
        
        $this->command->info('');
        $this->command->info('🌊 SISTEMA DE TRANSPORTE FLUVIAL AR/PY LISTO');
        $this->command->info('');
        $this->command->info('📋 Próximos pasos:');
        $this->command->line('  • Verificar datos: php artisan tinker');
        $this->command->line('  • Ver usuarios: User::with(\'userable\')->get()');
        $this->command->line('  • Ver viajes PARANA: Voyage::with(\'company\')->get()');
        $this->command->line('  • Ver viajes por número: Voyage::where(\'voyage_number\', \'V022NB\')->first()');
        $this->command->line('  • Ver capitanes: Captain::with(\'country\')->get()');
        $this->command->line('  • Ver transacciones WS: WebserviceTransaction::with(\'company\')->get()');
        $this->command->info('');
        $this->command->info('✅ Base de datos poblada exitosamente con DATOS REALES PARANA.csv');
        $this->command->info('🚢 Sistema listo para pruebas de webservices con datos reales');
        $this->command->info('📡 Transacciones webservice MAERSK creadas y listas para testing');
        $this->command->info('');
        $this->command->info('🎯 CREDENCIALES PARA EL CLIENTE:');
        $this->command->line('  • Email: admin.maersk@cargas.com');
        $this->command->line('  • Password: Maersk2025!');
        $this->command->line('  • Empresa: MAERSK LINE ARGENTINA S.A.');
        $this->command->line('  • CUIT: 30688415531');
    }
}