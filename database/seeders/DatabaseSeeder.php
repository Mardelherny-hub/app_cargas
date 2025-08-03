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
 * 3. Tipos de carga y embalaje (AGREGADO - requerido por módulo cargas)
 * 4. Tipos y propietarios (para embarcaciones)
 * 5. Embarcaciones (requiere tipos y propietarios)
 * 6. Clientes y contactos (CORREGIDO: Agregado ClientContactDataSeeder)
 * 7. Dependencias básicas webservices (países específicos, MAERSK, puertos)
 * 8. Módulo 3: Capitanes, Viajes y Cargas (CON DATOS REALES PARANA)
 * 9. Módulo 4: Transacciones Webservices (CON DATOS REALES MAERSK)
 * 
 * CORRECCIÓN FINAL: Usar VoyagesFromParanaSeeder en lugar de VoyageSeeder
 * para datos más realistas del sistema PARANA.csv
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
        // === FASE 3: TIPOS DE CARGA Y EMBALAJE ===
        //
        $this->command->info('📦 FASE 3: Tipos de Carga y Embalaje');
        $this->command->line('  └── Creando tipos de carga y tipos de embalaje...');
        
        $this->call([
            CargoTypesSeederTemp::class,
            PackagingTypesSeeder::class,
            ContainerTypesSeeder::class,
        ]);
        
        $this->command->info('  ✅ Tipos de carga, embalaje y tipos de containers completados');
        $this->command->info('');

        //
        // === FASE 4: TIPOS Y PROPIETARIOS (PRE-EMBARCACIONES) ===
        //
        $this->command->info('🏭 FASE 4: Tipos y Propietarios');
        $this->command->line('  └── Creando tipos de embarcaciones y propietarios...');
        
        $this->call([
            VesselTypeSeeder::class,
            VesselOwnersSeeder::class,
        ]);
        
        $this->command->info('  ✅ Tipos y propietarios completados');
        $this->command->info('');

        //
        // === FASE 5: EMBARCACIONES ===
        //
        $this->command->info('🛳️ FASE 5: Embarcaciones');
        $this->command->line('  └── Creando flota de embarcaciones fluviales...');
        
        $this->call([
            VesselSeeder::class,
        ]);
        
        $this->command->info('  ✅ Embarcaciones completadas');
        $this->command->info('');

        //
        // === FASE 6: CLIENTES Y CONTACTOS ===
        //
        $this->command->info('🏢 FASE 6: Clientes y Contactos');
        $this->command->line('  └── Creando empresas, clientes de Argentina y Paraguay y sus contactos...');
        
        $this->call([
            ClientsSeeder::class,
            ClientContactDataSeeder::class,  // 🔧 CORREGIDO: Agregado seeder de contactos
        ]);
        
        $this->command->info('  ✅ Clientes y contactos completados');
        $this->command->info('');

        //
        // === FASE 6.1: Containers ===
        //
        $this->command->info('📦 FASE 6.1: Contenedores');
        $this->command->line('  └── Creando contenedores físicos usando datos reales...');

        $this->call([
            ContainerSeeder::class,
        ]);

        $this->command->info('  ✅ Contenedores completados');
        $this->command->info('');

        //
        // === FASE 7: DEPENDENCIAS WEBSERVICES ===
        //
        $this->command->info('🔧 FASE 7: Dependencias Webservices');
        $this->command->line('  └── Creando empresa MAERSK, puertos específicos y usuarios...');
        
        $this->call([
            WebserviceBasicDependenciesSeeder::class,
        ]);
        
        $this->command->info('  ✅ Dependencias webservices completadas');
        $this->command->info('');

        //
        // === FASE 8: MÓDULO 3 - VIAJES Y CARGAS (DATOS REALES PARANA) ===
        //
        $this->command->info('🚢 FASE 8: Viajes y Cargas');
        $this->command->line('  └── Creando capitanes, viajes y conocimientos con datos reales PARANA.csv...');
        
        $this->call([
            CaptainSeeder::class,
            VoyagesFromParanaSeeder::class,  // 🔧 CORREGIDO: Usar datos reales PARANA.csv
            ShipmentSeeder::class,
            BillOfLadingSeeder::class,
        ]);
        
        $this->command->info('  ✅ Viajes y cargas completados');
        $this->command->info('');

        //
        // === FASE 9: MÓDULO 4 - TRANSACCIONES WEBSERVICES (DATOS REALES MAERSK) ===
        //
        $this->command->info('🌐 FASE 9: Transacciones Webservices');
        $this->command->line('  └── Creando transacciones webservices con datos reales MAERSK...');
        
        $this->call([
            WebserviceTransactionsSeeder::class,
        ]);
        
        $this->command->info('  ✅ Transacciones webservices completadas');
        $this->command->info('');

        //
        // === RESUMEN FINAL ===
        //
        $this->command->info('🎉 POBLACIÓN DE BASE DE DATOS COMPLETADA');
        $this->command->info('');
        $this->command->info('📊 RESUMEN DE MÓDULOS CREADOS:');
        $this->command->line('  ✅ Catálogos base (países, puertos, aduanas)');
        $this->command->line('  ✅ Sistema de usuarios y permisos');
        $this->command->line('  ✅ Tipos de carga, embalaje y contenedores');
        $this->command->line('  ✅ Tipos de embarcaciones y propietarios');
        $this->command->line('  ✅ Flota de embarcaciones fluviales');
        $this->command->line('  ✅ Clientes argentinos y paraguayos CON CONTACTOS'); // 🔧 Actualizado
        $this->command->line('  ✅ Contenedores físicos');
        $this->command->line('  ✅ Dependencias webservices MAERSK');
        $this->command->line('  ✅ Capitanes, viajes y conocimientos (DATOS REALES PARANA.csv)'); // 🔧 Actualizado
        $this->command->line('  ✅ Transacciones webservices (DATOS REALES MAERSK)');
        $this->command->info('');
        $this->command->info('🚀 Sistema listo para uso en desarrollo y testing');
        $this->command->info('📋 Datos poblados con información realista del sistema PARANA');
        $this->command->info('');
    }
}