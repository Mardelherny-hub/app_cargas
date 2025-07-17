<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * PROPÓSITO: Agregar campo contact_type para diferenciar usos de contacto
     * REQUERIMIENTO: Permitir múltiples contactos por cliente con diferentes usos
     * - AFIP/Webservices: Para envíos oficiales
     * - Manifiestos: Para notificaciones de manifiestos  
     * - Cartas de arribo: Para envío de cartas de aviso de arribo
     * - General: Contacto administrativo general
     * - Emergencia: Para situaciones urgentes
     */
    public function up(): void
    {
        Schema::table('client_contact_data', function (Blueprint $table) {
            // Agregar campo contact_type después de client_id
            $table->enum('contact_type', [
                'general',      // Contacto general/administrativo
                'afip',         // Para webservices AFIP y trámites oficiales
                'manifests',    // Para notificaciones de manifiestos
                'arrival_notices', // Para cartas de aviso de arribo
                'emergency',    // Para situaciones de emergencia
                'billing',      // Para facturación y cobranzas
                'operations'    // Para coordinación operativa
            ])
            ->default('general')
            ->after('client_id')
            ->comment('Tipo/uso del contacto para diferentes propósitos');

            // Agregar índice para búsquedas por tipo
            $table->index(['client_id', 'contact_type', 'active'], 'idx_client_contact_type');
            
            // Agregar índice compuesto para contactos de notificación
            $table->index(['contact_type', 'accepts_email_notifications', 'active'], 'idx_contact_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_contact_data', function (Blueprint $table) {
            // Remover índices primero
            $table->dropIndex('idx_client_contact_type');
            $table->dropIndex('idx_contact_notifications');
            
            // Remover campo
            $table->dropColumn('contact_type');
        });
    }
};

/*
TIPOS DE CONTACTO DEFINIDOS:

1. general: Contacto administrativo general (default)
2. afip: Para webservices AFIP, DGI y trámites oficiales
3. manifests: Para envío de manifiestos y documentación de carga
4. arrival_notices: Para cartas de aviso de arribo de barcos
5. emergency: Para situaciones de emergencia y urgencias
6. billing: Para facturación, cobranzas y temas comerciales  
7. operations: Para coordinación operativa y logística

NOTA: El constraint unique_primary_contact_per_client existente seguirá funcionando
ya que is_primary se mantiene independiente del contact_type. Un cliente puede tener:
- Un contacto principal (is_primary=true) de cualquier tipo
- Múltiples contactos de diferentes tipos
- Múltiples contactos del mismo tipo si es necesario

ÍNDICES AGREGADOS:
- idx_client_contact_type: Para buscar contactos por cliente y tipo
- idx_contact_notifications: Para obtener contactos que aceptan notificaciones por tipo
*/