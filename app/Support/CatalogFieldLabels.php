<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Etiquetas en español de los campos de los catálogos
 * (tipos de contenedor, de carga y de embalaje).
 *
 * Un campo sin etiqueta conserva el texto generado a partir de su nombre.
 */
final class CatalogFieldLabels
{
    private const LABELS = [
        // Identificación
        'code' => 'Código',
        'name' => 'Nombre',
        'short_name' => 'Nombre corto',
        'description' => 'Descripción',
        'category' => 'Categoría',
        'cargo_nature' => 'Naturaleza de la carga',
        'packaging_type' => 'Forma de transporte',
        'material_type' => 'Material',
        'parent_id' => 'Tipo padre',
        'level' => 'Nivel',
        'full_path' => 'Ruta completa',

        // Códigos externos
        'iso_code' => 'Código ISO',
        'iso_size_type' => 'Código ISO tamaño/tipo',
        'iso_group' => 'Grupo ISO',
        'unece_code' => 'Código UN/ECE',
        'imdg_class' => 'Clase IMDG',
        'imdg_code' => 'Código IMDG',
        'hs_code_prefix' => 'Prefijo de código SA',
        'tariff_classification' => 'Clasificación arancelaria',
        'webservice_code' => 'Código para webservices',
        'webservice_mapping' => 'Mapeo para webservices',
        'argentina_ws_code' => 'Código webservice Argentina',
        'paraguay_ws_code' => 'Código webservice Paraguay',
        'customs_code' => 'Código aduanero',
        'senasa_code' => 'Código SENASA',

        // Dimensiones
        'length_feet' => 'Largo (pies)',
        'width_feet' => 'Ancho (pies)',
        'height_feet' => 'Alto (pies)',
        'length_mm' => 'Largo (mm)',
        'width_mm' => 'Ancho (mm)',
        'height_mm' => 'Alto (mm)',
        'diameter_mm' => 'Diámetro (mm)',
        'internal_length_mm' => 'Largo interno (mm)',
        'internal_width_mm' => 'Ancho interno (mm)',
        'internal_height_mm' => 'Alto interno (mm)',
        'door_type' => 'Tipo de puerta',
        'door_width_mm' => 'Ancho de puerta (mm)',
        'door_height_mm' => 'Alto de puerta (mm)',
        'dimension_restrictions' => 'Restricciones de dimensiones',

        // Pesos y volúmenes
        'tare_weight_kg' => 'Tara (kg)',
        'empty_weight_kg' => 'Peso vacío (kg)',
        'max_gross_weight_kg' => 'Peso bruto máximo (kg)',
        'max_net_weight_kg' => 'Peso neto máximo (kg)',
        'max_payload_kg' => 'Carga útil máxima (kg)',
        'max_weight_per_container' => 'Peso máximo por contenedor',
        'weight_tolerance_percent' => 'Tolerancia de peso (%)',
        'typical_density' => 'Densidad típica (kg/m³)',
        'internal_volume_m3' => 'Volumen interno (m³)',
        'loading_volume_m3' => 'Volumen de carga (m³)',
        'volume_liters' => 'Volumen (litros)',
        'volume_m3' => 'Volumen (m³)',

        // Apilamiento
        'stackable' => 'Apilable',
        'is_stackable' => 'Apilable',
        'max_stack_height' => 'Altura máxima de apilamiento',
        'stacking_weight_limit_kg' => 'Límite de peso para apilar (kg)',
        'stacking_limitations' => 'Limitaciones de apilamiento',

        // Temperatura y ambiente
        'min_temperature_celsius' => 'Temperatura mínima (°C)',
        'max_temperature_celsius' => 'Temperatura máxima (°C)',
        'temperature_range' => 'Rango de temperatura',
        'temperature_range_min' => 'Temperatura mínima',
        'temperature_range_max' => 'Temperatura máxima',
        'humidity_requirements' => 'Requisitos de humedad',
        'is_refrigerated' => 'Refrigerado',
        'is_heated' => 'Con calefacción',
        'is_insulated' => 'Aislado',
        'is_ventilated' => 'Ventilado',
        'has_electrical_supply' => 'Con suministro eléctrico',
        'has_humidity_control' => 'Con control de humedad',
        'has_atmosphere_control' => 'Con control de atmósfera',
        'requires_refrigeration' => 'Requiere refrigeración',
        'requires_ventilation' => 'Requiere ventilación',
        'requires_humidity_control' => 'Requiere control de humedad',
        'requires_power_supply' => 'Requiere suministro eléctrico',
        'is_weatherproof' => 'Resistente a la intemperie',
        'is_moisture_resistant' => 'Resistente a la humedad',
        'is_uv_resistant' => 'Resistente a rayos UV',

        // Estructura
        'has_roof' => 'Con techo',
        'has_sidewalls' => 'Con paredes laterales',
        'has_end_walls' => 'Con paredes frontales',
        'has_doors' => 'Con puertas',
        'has_removable_top' => 'Techo removible',
        'has_folding_sides' => 'Lados plegables',
        'is_reusable' => 'Reutilizable',
        'is_returnable' => 'Retornable',
        'is_collapsible' => 'Plegable',
        'is_hermetic' => 'Hermético',
        'is_tamper_evident' => 'Evidencia de apertura',
        'is_child_resistant' => 'A prueba de niños',

        // Aptitudes
        'suitable_for_dangerous_goods' => 'Apto para mercancías peligrosas',
        'suitable_for_food' => 'Apto para alimentos',
        'suitable_for_chemicals' => 'Apto para químicos',
        'suitable_for_liquids' => 'Apto para líquidos',
        'suitable_for_powders' => 'Apto para polvos',
        'suitable_for_bulk_cargo' => 'Apto para carga a granel',
        'suitable_for_heavy_cargo' => 'Apto para carga pesada',
        'suitable_for_heavy_items' => 'Apto para artículos pesados',
        'suitable_for_fragile_items' => 'Apto para artículos frágiles',
        'suitable_for_oversized_cargo' => 'Apto para carga sobredimensionada',

        // Características de la carga
        'is_dangerous_goods' => 'Mercancía peligrosa',
        'is_perishable' => 'Perecedera',
        'is_fragile' => 'Frágil',
        'can_be_mixed' => 'Se puede mezclar',
        'incompatible_with' => 'Incompatible con',
        'allows_consolidation' => 'Permite consolidación',
        'allows_deconsolidation' => 'Permite desconsolidación',
        'allows_transshipment' => 'Permite transbordo',
        'typical_loading_time' => 'Tiempo típico de carga (horas)',

        // Manipulación
        'requires_special_handling' => 'Requiere manejo especial',
        'requires_monitoring' => 'Requiere monitoreo',
        'requires_palletizing' => 'Requiere paletizado',
        'requires_strapping' => 'Requiere zunchado',
        'requires_wrapping' => 'Requiere envoltura',
        'provides_cushioning' => 'Amortigua',
        'provides_impact_protection' => 'Protege contra impactos',
        'provides_theft_protection' => 'Protege contra robo',
        'handling_equipment' => 'Equipo de manejo',
        'handling_equipment_required' => 'Equipo de manejo requerido',

        // Documentación, permisos e inspección
        'requires_permits' => 'Requiere permisos',
        'requires_certificate_origin' => 'Requiere certificado de origen',
        'requires_health_certificate' => 'Requiere certificado sanitario',
        'requires_fumigation' => 'Requiere fumigación',
        'requires_fumigation_certificate' => 'Requiere certificado de fumigación',
        'requires_insurance' => 'Requiere seguro',
        'required_documents' => 'Documentos requeridos',
        'subject_to_inspection' => 'Sujeto a inspección',
        'inspection_percentage' => 'Porcentaje de inspección',
        'customs_requirements' => 'Requisitos aduaneros',
        'prohibited_countries' => 'Países prohibidos',
        'requires_pretrip_inspection' => 'Requiere inspección previa al viaje',
        'requires_cleaning' => 'Requiere limpieza',
        'inspection_interval_months' => 'Intervalo de inspección (meses)',
        'requires_testing' => 'Requiere pruebas',
        'testing_frequency_days' => 'Frecuencia de pruebas (días)',

        // Certificaciones
        'csc_certified' => 'Certificado CSC',
        'food_grade' => 'Grado alimentario',
        'pharmaceutical_grade' => 'Grado farmacéutico',
        'fda_approved' => 'Aprobado por FDA',
        'food_contact_safe' => 'Apto para contacto con alimentos',
        'certifications' => 'Certificaciones',
        'regulatory_compliance' => 'Cumplimiento normativo',
        'quality_standards' => 'Estándares de calidad',
        'acceptable_defect_rate_percent' => 'Tasa aceptable de defectos (%)',

        // Marcado
        'requires_labeling' => 'Requiere etiquetado',
        'allows_printing' => 'Permite impresión',
        'requires_hazmat_marking' => 'Requiere marcado de mercancía peligrosa',
        'required_markings' => 'Marcados requeridos',
        'prohibited_markings' => 'Marcados prohibidos',

        // Ambiente y reciclado
        'eco_friendly' => 'Amigable con el ambiente',
        'carbon_footprint_kg' => 'Huella de carbono (kg)',
        'environmental_certifications' => 'Certificaciones ambientales',
        'is_recyclable' => 'Reciclable',
        'is_biodegradable' => 'Biodegradable',
        'is_compostable' => 'Compostable',
        'contains_recycled_material' => 'Contiene material reciclado',
        'recycled_content_percent' => 'Contenido reciclado (%)',
        'disposal_instructions' => 'Instrucciones de descarte',

        // Costos y abastecimiento
        'daily_rental_rate' => 'Tarifa diaria de alquiler',
        'purchase_price_estimate' => 'Precio estimado de compra',
        'maintenance_cost_per_year' => 'Costo anual de mantenimiento',
        'insurance_rate' => 'Tasa de seguro',
        'freight_rates' => 'Tarifas de flete',
        'unit_cost' => 'Costo unitario',
        'cost_per_kg' => 'Costo por kg',
        'cost_per_m3' => 'Costo por m³',
        'cost_varies_by_quantity' => 'El costo varía según la cantidad',
        'minimum_order_quantity' => 'Cantidad mínima de pedido',
        'widely_available' => 'Ampliamente disponible',
        'typical_lead_time_days' => 'Plazo típico de entrega (días)',
        'preferred_suppliers' => 'Proveedores preferidos',
        'alternative_types' => 'Tipos alternativos',
        'typical_lifespan_years' => 'Vida útil típica (años)',

        // Uso y restricciones
        'allowed_conditions' => 'Condiciones permitidas',
        'condition_descriptions' => 'Descripción de condiciones',
        'compatible_vessel_types' => 'Tipos de embarcación compatibles',
        'restricted_ports' => 'Puertos con restricciones',
        'industry_applications' => 'Aplicaciones por industria',
        'commodity_compatibility' => 'Compatibilidad con mercaderías',
        'seasonal_considerations' => 'Consideraciones estacionales',
        'seasonal_restrictions' => 'Restricciones estacionales',
        'embargo_periods' => 'Períodos de embargo',

        // Estado y presentación
        'active' => 'Activo',
        'is_standard' => 'Estándar',
        'is_common' => 'Común',
        'is_specialized' => 'Especializado',
        'is_deprecated' => 'Obsoleto',
        'display_order' => 'Orden de visualización',
        'icon' => 'Ícono',
        'color_code' => 'Color',
        'created_date' => 'Fecha de alta',
        'created_by_user_id' => 'Usuario creador',
        'created_at' => 'Creado',
        'updated_at' => 'Actualizado',
    ];

    private const OPTIONS = [
        // Naturaleza de la carga
        'solid' => 'Sólida',
        'liquid' => 'Líquida',
        'gas' => 'Gaseosa',
        'mixed' => 'Mixta',
        'other' => 'Otro',

        // Forma de transporte
        'containerized' => 'En contenedor',
        'bulk' => 'Granel',
        'break_bulk' => 'Carga general',
        'ro_ro' => 'Ro-Ro',
        'neo_bulk' => 'Neo granel',

        // Categoría de embalaje
        'pallet' => 'Pallet',
        'box' => 'Caja',
        'bag' => 'Bolsa',
        'drum' => 'Tambor',
        'container' => 'Contenedor',
        'bundle' => 'Atado',
        'roll' => 'Rollo',
        'bale' => 'Fardo',
        'crate' => 'Jaula',
        'barrel' => 'Barril',
        'tank' => 'Tanque',
        'tray' => 'Bandeja',
        'specialized' => 'Especializado',

        // Material
        'wood' => 'Madera',
        'cardboard' => 'Cartón',
        'plastic' => 'Plástico',
        'metal' => 'Metal',
        'fabric' => 'Tela',
        'paper' => 'Papel',
        'composite' => 'Compuesto',
        'glass' => 'Vidrio',
        'rubber' => 'Caucho',

        // Equipo de manejo
        'manual' => 'Manual',
        'forklift' => 'Autoelevador',
        'crane' => 'Grúa',
        'conveyor' => 'Cinta transportadora',
    ];

    public static function for(string $field): string
    {
        return self::LABELS[$field]
            ?? (string) Str::of($field)->replace('_', ' ')->title();
    }

    public static function option(string $value): string
    {
        return self::OPTIONS[$value]
            ?? (string) Str::of($value)->replace('_', ' ')->title();
    }
}
