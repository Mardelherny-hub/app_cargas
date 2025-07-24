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
 * 6. Módulo 3: Capitanes, Viajes y Cargas
 * 
 * USO: php artisan db:seed
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
        $this->command->line('  └── Creando clientes y contactos...');
        
        $this->call([
            ClientsSeeder::class,
            ClientContactDataSeeder::class,
        ]);
        
        $this->command->info('  ✅ Clientes completados');
        $this->command->info('');

        //
        // === FASE 6: MÓDULO 3 - VIAJES Y CARGAS ===
        //
        $this->command->info('🚢 FASE 6: Módulo 3 - Viajes y Cargas');
        $this->command->line('  └── Creando capitanes, viajes y envíos...');
        
        $this->call([
            CaptainSeeder::class,
            VoyageSeeder::class,
            ShipmentSeeder::class,
        ]);
        
        $this->command->info('  ✅ Módulo 3 completado');
        $this->command->info('');

        //
        // === RESUMEN FINAL ===
        //
        $this->showCompletionSummary();
    }

    /**
     * Mostrar resumen de población completada
     */
    private function showCompletionSummary(): void
    {
        $this->command->info('=== 🎉 POBLACIÓN DE BASE DE DATOS COMPLETADA ===');
        $this->command->info('');
        
        // Contar registros principales si las tablas existen
        try {
            $this->command->info('📊 RESUMEN DE REGISTROS CREADOS:');
            
            if (class_exists('\App\Models\Country')) {
                $countries = \App\Models\Country::count();
                $this->command->line("  • Países: {$countries}");
            }
            
            if (class_exists('\App\Models\Company')) {
                $companies = \App\Models\Company::count();
                $this->command->line("  • Empresas: {$companies}");
            }
            
            if (class_exists('\App\Models\User')) {
                $users = \App\Models\User::count();
                $this->command->line("  • Usuarios: {$users}");
            }
            
            if (class_exists('\App\Models\VesselType')) {
                $vesselTypes = \App\Models\VesselType::count();
                $this->command->line("  • Tipos de embarcación: {$vesselTypes}");
            }
            
            if (class_exists('\App\Models\VesselOwner')) {
                $vesselOwners = \App\Models\VesselOwner::count();
                $this->command->line("  • Propietarios de embarcaciones: {$vesselOwners}");
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
            
        } catch (\Exception $e) {
            $this->command->warn('  (No se pudo obtener el conteo de registros - normal en primera ejecución)');
        }
        
        $this->command->info('');
        $this->command->info('🌊 SISTEMA DE TRANSPORTE FLUVIAL AR/PY LISTO');
        $this->command->info('');
        $this->command->info('📋 Próximos pasos:');
        $this->command->line('  • Verificar datos: php artisan tinker');
        $this->command->line('  • Ver usuarios: User::with(\'userable\')->get()');
        $this->command->line('  • Ver viajes: Voyage::with(\'shipments\')->get()');
        $this->command->line('  • Ver capitanes: Captain::with(\'country\')->get()');
        $this->command->info('');
        $this->command->info('✅ Base de datos poblada exitosamente con datos reales');
        $this->command->info('🚢 Sistema listo para pruebas y desarrollo');
    }
}