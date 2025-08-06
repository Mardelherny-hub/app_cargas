<?php

namespace App\ValueObjects;

use App\Models\Voyage;
use Illuminate\Support\Collection;

/**
 * VALUE OBJECT PARA RESULTADOS DE PARSING DE MANIFIESTOS
 * 
 * Encapsula de manera consistente todos los resultados de un proceso
 * de parsing de manifiesto, incluyendo datos creados, errores y estadísticas.
 * 
 * BENEFICIOS:
 * - Interfaz consistente entre diferentes parsers
 * - Información completa del resultado del proceso
 * - Inmutable una vez creado
 * - Fácil de testear y debuggear
 */
readonly class ManifestParseResult
{
    public function __construct(
        public bool $success,
        public ?Voyage $voyage,
        public array $shipments,
        public array $containers,
        public array $billsOfLading,
        public array $errors,
        public array $warnings,
        public array $statistics
    ) {}

    /**
     * Verificar si el parsing fue exitoso sin errores críticos
     */
    public function isSuccessful(): bool
    {
        return $this->success && empty($this->errors);
    }

    /**
     * Verificar si hay advertencias
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Obtener el primer error si existe
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Obtener todas las advertencias como string
     */
    public function getWarningsText(): string
    {
        return implode("\n", $this->warnings);
    }

    /**
     * Obtener todas los errores como string
     */
    public function getErrorsText(): string
    {
        return implode("\n", $this->errors);
    }

    /**
     * Obtener resumen estadístico
     */
    public function getStatsSummary(): array
    {
        return [
            'success' => $this->success,
            'voyage_created' => $this->voyage ? 1 : 0,
            'shipments_count' => count($this->shipments),
            'containers_count' => count($this->containers),
            'bills_count' => count($this->billsOfLading),
            'errors_count' => count($this->errors),
            'warnings_count' => count($this->warnings),
            'processed_items' => $this->statistics['processed'] ?? 0,
            'failed_items' => $this->statistics['errors'] ?? 0
        ];
    }

    /**
     * Convertir a array para respuesta JSON
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'voyage_id' => $this->voyage?->id,
            'voyage_number' => $this->voyage?->voyage_number,
            'shipments' => array_map(fn($s) => [
                'id' => $s->id,
                'shipment_number' => $s->shipment_number,
                'status' => $s->status
            ], $this->shipments),
            'containers' => array_map(fn($c) => [
                'id' => $c->id ?? null,
                'container_number' => $c->container_number ?? $c['number'] ?? null,
                'type' => $c->container_type ?? $c['type'] ?? null
            ], $this->containers),
            'bills_of_lading' => array_map(fn($bl) => [
                'id' => $bl->id,
                'bl_number' => $bl->bl_number,
                'status' => $bl->status
            ], $this->billsOfLading),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'statistics' => $this->statistics,
            'summary' => $this->getStatsSummary()
        ];
    }

    /**
     * Crear resultado de éxito
     */
    public static function success(
        ?Voyage $voyage = null,
        array $shipments = [],
        array $containers = [],
        array $billsOfLading = [],
        array $warnings = [],
        array $statistics = []
    ): self {
        return new self(
            success: true,
            voyage: $voyage,
            shipments: $shipments,
            containers: $containers,
            billsOfLading: $billsOfLading,
            errors: [],
            warnings: $warnings,
            statistics: $statistics
        );
    }

    /**
     * Crear resultado de error
     */
    public static function failure(
        array $errors,
        array $warnings = [],
        array $statistics = []
    ): self {
        return new self(
            success: false,
            voyage: null,
            shipments: [],
            containers: [],
            billsOfLading: [],
            errors: $errors,
            warnings: $warnings,
            statistics: $statistics
        );
    }

    /**
     * Crear resultado parcial (con algunos errores pero datos válidos)
     */
    public static function partial(
        ?Voyage $voyage,
        array $shipments,
        array $containers,
        array $billsOfLading,
        array $errors,
        array $warnings = [],
        array $statistics = []
    ): self {
        return new self(
            success: true, // Parcialmente exitoso
            voyage: $voyage,
            shipments: $shipments,
            containers: $containers,
            billsOfLading: $billsOfLading,
            errors: $errors,
            warnings: $warnings,
            statistics: $statistics
        );
    }
}