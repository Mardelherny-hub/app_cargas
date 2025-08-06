<?php
/**
 * ARCHIVO: app/Exports/LoginXmlExport.php
 * 
 * Export class para generar Login.xml desde Laravel Excel
 * (Alternativo al método directo en el controlador)
 */

namespace App\Exports;

use App\Models\Voyage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LoginXmlExport implements FromCollection, WithHeadings, WithMapping
{
    protected $voyage;

    public function __construct(Voyage $voyage)
    {
        $this->voyage = $voyage;
    }

    public function collection()
    {
        // Retornar collection de BLs para el XML
        return $this->voyage->shipments()
            ->with('billsOfLading.shipmentItems')
            ->get()
            ->flatMap(function ($shipment) {
                return $shipment->billsOfLading;
            });
    }

    public function headings(): array
    {
        return [
            'BL_Number',
            'BL_Date', 
            'Shipper_Name',
            'Consignee_Name',
            'Items_Count',
            'Total_Weight',
            'Total_Volume',
            'Commodity_Codes'
        ];
    }

    public function map($billOfLading): array
    {
        return [
            $billOfLading->bl_number ?? '',
            $billOfLading->bl_date ? $billOfLading->bl_date->format('Y-m-d') : '',
            $billOfLading->shipper->legal_name ?? 'N/A',
            $billOfLading->consignee->legal_name ?? 'N/A',
            $billOfLading->shipmentItems->count(),
            $billOfLading->shipmentItems->sum('gross_weight_kg'),
            $billOfLading->shipmentItems->sum('volume_m3'),
            $billOfLading->shipmentItems->pluck('commodity_code')->implode(', ')
        ];
    }
}

// =============================================================================

/**
 * ARCHIVO: app/Exports/TfpTextExport.php
 * 
 * Export class para generar TFP.txt
 */

namespace App\Exports;

use App\Models\Voyage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class TfpTextExport implements FromCollection, WithCustomStartCell, WithEvents
{
    protected $voyage;

    public function __construct(Voyage $voyage)
    {
        $this->voyage = $voyage;
    }

    public function collection()
    {
        // Generar las líneas del archivo TFP
        $lines = collect();
        
        // Header
        $lines->push("**VOYAGE**");
        $lines->push("VOYAGE_NUMBER: /*{$this->voyage->voyage_number}*/");
        
        // BLs
        foreach ($this->voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bl) {
                $lines->push("**BL**");
                $lines->push("BLNUMERO: /*{$bl->bl_number}*/");
                $lines->push("SHIPPER: /*" . ($bl->shipper->legal_name ?? 'N/A') . "*/");
            }
        }
        
        return $lines->map(function ($line) {
            return [$line]; // Cada línea como array
        });
    }

    public function startCell(): string
    {
        return 'A1';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Configurar como texto plano
                $event->sheet->getDelegate()->getStyle('A:A')
                    ->getAlignment()->setWrapText(false);
            },
        ];
    }
}

// =============================================================================

/**
 * ARCHIVO: app/Exports/EdiCuscarExport.php
 * 
 * Export class para generar EDI/CUSCAR
 */

namespace App\Exports;

use App\Models\Voyage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class EdiCuscarExport implements FromCollection, WithCustomStartCell
{
    protected $voyage;

    public function __construct(Voyage $voyage)
    {
        $this->voyage = $voyage;
    }

    public function collection()
    {
        $segments = collect();
        
        // UNB - Service String Advice (Header)
        $segments->push("UNB+UNOC:3+SENDER+RECEIVER+" . now()->format('yyMMdd:HHmm') . "+1'");
        
        // UNH - Message Header
        $segments->push("UNH+1+CUSCAR:D:95B:UN'");
        
        // BGM - Beginning of Message  
        $segments->push("BGM+85+{$this->voyage->voyage_number}+9'");
        
        // DTM - Date/Time
        $segments->push("DTM+137:" . now()->format('YmdHHmm') . ":203'");
        
        // TDT - Details of Transport
        $vesselName = $this->voyage->shipments->first()->vessel->name ?? 'UNKNOWN';
        $segments->push("TDT+20+{$this->voyage->voyage_number}++3:{$vesselName}'");
        
        // LOC - Place/Location Identification
        $originCode = $this->voyage->origin_port->code ?? 'ARUNKNOWN';
        $destCode = $this->voyage->destination_port->code ?? 'PYUNKNOWN';
        $segments->push("LOC+5+{$originCode}:139:6'");
        $segments->push("LOC+61+{$destCode}:139:6'");
        
        // Procesar cada BL
        foreach ($this->voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bl) {
                // CNI - Consignment Information
                $segments->push("CNI++" . ($bl->bl_number ?? '') . "'");
                
                // NAD - Name and Address (Shipper)
                if ($bl->shipper) {
                    $segments->push("NAD+CZ+++" . substr($bl->shipper->legal_name, 0, 35) . "'");
                }
                
                // NAD - Name and Address (Consignee)  
                if ($bl->consignee) {
                    $segments->push("NAD+CN+++" . substr($bl->consignee->legal_name, 0, 35) . "'");
                }
                
                // GDS - Nature of Cargo
                if ($bl->shipmentItems) {
                    foreach ($bl->shipmentItems as $item) {
                        $segments->push("GDS++" . ($item->commodity_code ?? '') . ":" . substr($item->item_description ?? '', 0, 35) . "'");
                        $segments->push("MEA+AAE+G+KGM:" . ($item->gross_weight_kg ?? 0) . "'");
                        $segments->push("QTY+52:" . ($item->package_quantity ?? 0) . "'");
                    }
                }
            }
        }
        
        // UNT - Message Trailer
        $segmentCount = $segments->count() + 1;
        $segments->push("UNT+{$segmentCount}+1'");
        
        // UNZ - Service String Advice Trailer
        $segments->push("UNZ+1+1'");
        
        return $segments->map(function ($segment) {
            return [$segment]; // Cada segmento como array
        });
    }

    public function startCell(): string
    {
        return 'A1';
    }
}

// =============================================================================

/**
 * NOTAS PARA IMPLEMENTACIÓN:
 * 
 * 1. Crear estos archivos en las rutas indicadas
 * 2. ParanaExport y GuaranExport ya existen (según el código actual)
 * 3. Los métodos en ManifestExportController usan generación directa
 *    pero también pueden usar estas clases Export si prefieres consistencia
 * 4. Para archivos de texto plano (TFP, EDI), es mejor la generación directa
 *    que se hace en el controlador
 */