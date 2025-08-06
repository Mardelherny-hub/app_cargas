<?php

namespace App\Http\Controllers\Company\Manifests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voyage;
use App\Exports\ParanaExport;
use App\Exports\GuaranExport;
use App\Exports\LoginXmlExport;
use App\Exports\TfpTextExport;
use App\Exports\EdiCuscarExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Response;

/**
 * COMPLETADO: ManifestExportController
 * 
 * Maneja la exportación de manifiestos en todos los formatos:
 * - PARANA.xlsx (MAERSK format)  
 * - Guaran.csv (Multi-línea consolidado)
 * - Login.xml (XML anidado completo)
 * - TFP.txt (Jerárquico con delimitadores)
 * - EDI/CUSCAR (UN/EDIFACT estándar)
 */
class ManifestExportController extends Controller
{
    /**
     * Vista principal de exportación - Lista viajes disponibles
     */
    public function index()
    {
        // Obtener viajes con cargas completadas para exportar
        $voyages = Voyage::with(['shipments.billsOfLading', 'origin_port', 'destination_port'])
            ->where('company_id', auth()->user()->company_id)
            ->whereHas('shipments') // Solo viajes con cargas
            ->whereIn('status', ['completed', 'in_progress']) // Solo viajes listos
            ->latest()
            ->paginate(15);

        return view('company.manifests.export', compact('voyages'));
    }

    /**
     * Exportar manifiesto a formato PARANA (xlsx).
     * Formato Excel estándar MAERSK con 73 columnas
     */
    public function exportParana($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);
        
        try {
            return Excel::download(
                new ParanaExport($voyage), 
                'PARANA_' . $voyage->voyage_number . '.xlsx'
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error generando archivo PARANA: ' . $e->getMessage());
        }
    }

    /**
     * Exportar manifiesto a formato GUARAN (csv).
     * Formato CSV para manifiestos consolidados multi-línea
     */
    public function exportGuaran($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);
        
        try {
            return Excel::download(
                new GuaranExport($voyage), 
                'GUARAN_' . $voyage->voyage_number . '.csv', 
                \Maatwebsite\Excel\Excel::CSV
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error generando archivo Guaran: ' . $e->getMessage());
        }
    }

    /**
     * Exportar manifiesto a formato LOGIN (xml).
     * Estructura XML anidada completa por conocimiento
     */
    public function exportLogin($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);
        
        try {
            // Generar XML usando servicio especializado
            $xmlContent = $this->generateLoginXml($voyage);
            
            $filename = 'LOGIN_' . $voyage->voyage_number . '.xml';
            
            return Response::make($xmlContent, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Error generando archivo Login XML: ' . $e->getMessage());
        }
    }

    /**
     * Exportar manifiesto a formato TFP (txt).
     * Formato jerárquico con delimitadores específicos
     */
    public function exportTfp($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);
        
        try {
            // Generar TFP usando servicio especializado
            $tfpContent = $this->generateTfpText($voyage);
            
            $filename = 'TFP_' . $voyage->voyage_number . '.txt';
            
            return Response::make($tfpContent, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Error generando archivo TFP: ' . $e->getMessage());
        }
    }

    /**
     * Exportar manifiesto a formato EDI/CUSCAR.
     * Estándar UN/EDIFACT para intercambio electrónico
     */
    public function exportEdi($voyageId)
    {
        $voyage = $this->getVoyageForExport($voyageId);
        
        try {
            // Generar EDI usando servicio especializado
            $ediContent = $this->generateEdiCuscar($voyage);
            
            $filename = 'CUSCAR_' . $voyage->voyage_number . '.edi';
            
            return Response::make($ediContent, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Error generando archivo EDI: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS HELPER PRIVADOS
    // =========================================================================

    /**
     * Obtener voyage con validaciones de seguridad y relaciones necesarias
     */
    private function getVoyageForExport($voyageId): Voyage
    {
        return Voyage::with([
            'shipments.billsOfLading.shipper',
            'shipments.billsOfLading.consignee', 
            'shipments.billsOfLading.notifyParty',
            'shipments.billsOfLading.shipmentItems.cargoType',
            'shipments.billsOfLading.shipmentItems.packagingType',
            'shipments.vessel',
            'origin_port.country',
            'destination_port.country',
            'company'
        ])
        ->where('company_id', auth()->user()->company_id)
        ->findOrFail($voyageId);
    }

    /**
     * Generar contenido XML para formato Login
     */
    private function generateLoginXml(Voyage $voyage): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><BillOfLadingRoot></BillOfLadingRoot>');
        
        // Header información del viaje
        $voyageInfo = $xml->addChild('VoyageInfo');
        $voyageInfo->addChild('VoyageNumber', htmlspecialchars($voyage->voyage_number));
        $voyageInfo->addChild('VesselName', htmlspecialchars($voyage->shipments->first()->vessel->name ?? 'N/A'));
        $voyageInfo->addChild('OriginPort', htmlspecialchars($voyage->origin_port->name ?? 'N/A'));
        $voyageInfo->addChild('DestinationPort', htmlspecialchars($voyage->destination_port->name ?? 'N/A'));
        $voyageInfo->addChild('GeneratedAt', now()->toISOString());
        
        // Bills of Lading
        $billsContainer = $xml->addChild('BillsOfLading');
        
        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bl) {
                $billElement = $billsContainer->addChild('BillOfLading');
                
                // Información básica del BL
                $billElement->addChild('BLNumber', htmlspecialchars($bl->bl_number));
                $billElement->addChild('BLDate', $bl->bl_date ? $bl->bl_date->format('Y-m-d') : '');
                $billElement->addChild('ShipperName', htmlspecialchars($bl->shipper->legal_name ?? 'N/A'));
                $billElement->addChild('ConsigneeName', htmlspecialchars($bl->consignee->legal_name ?? 'N/A'));
                
                // Items del shipment
                if ($bl->shipmentItems && $bl->shipmentItems->count() > 0) {
                    $itemsContainer = $billElement->addChild('Items');
                    
                    foreach ($bl->shipmentItems as $item) {
                        $itemElement = $itemsContainer->addChild('Item');
                        $itemElement->addChild('Description', htmlspecialchars($item->item_description ?? ''));
                        $itemElement->addChild('CommodityCode', htmlspecialchars($item->commodity_code ?? ''));
                        $itemElement->addChild('PackageQuantity', $item->package_quantity ?? 0);
                        $itemElement->addChild('GrossWeight', $item->gross_weight_kg ?? 0);
                        $itemElement->addChild('NetWeight', $item->net_weight_kg ?? 0);
                        $itemElement->addChild('Volume', $item->volume_m3 ?? 0);
                    }
                }
            }
        }
        
        return $xml->asXML();
    }

    /**
     * Generar contenido TFP con delimitadores específicos
     */
    private function generateTfpText(Voyage $voyage): string
    {
        $content = [];
        
        // Header del archivo
        $content[] = "**VOYAGE**";
        $content[] = "VOYAGE_NUMBER: /*{$voyage->voyage_number}*/";
        $content[] = "VESSEL_NAME: /*" . ($voyage->shipments->first()->vessel->name ?? 'N/A') . "*/";
        $content[] = "ORIGIN_PORT: /*" . ($voyage->origin_port->name ?? 'N/A') . "*/";
        $content[] = "DESTINATION_PORT: /*" . ($voyage->destination_port->name ?? 'N/A') . "*/";
        $content[] = "";
        
        // Procesar cada BL
        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bl) {
                $content[] = "**BL**";
                $content[] = "BLNUMERO: /*{$bl->bl_number}*/";
                $content[] = "BLDATE: /*" . ($bl->bl_date ? $bl->bl_date->format('Y-m-d') : '') . "*/";
                $content[] = "SHIPPER: /*" . ($bl->shipper->legal_name ?? 'N/A') . "*/";
                $content[] = "CONSIGNEE: /*" . ($bl->consignee->legal_name ?? 'N/A') . "*/";
                $content[] = "";
                
                // Contenedores (si los hay)
                $content[] = "**CONTENEDORES**";
                $content[] = "CONTAINER_COUNT: /*" . ($shipment->containers_loaded ?? 0) . "*/";
                $content[] = "TOTAL_WEIGHT: /*" . ($shipment->cargo_weight_loaded ?? 0) . "*/";
                $content[] = "";
                
                // Items
                if ($bl->shipmentItems && $bl->shipmentItems->count() > 0) {
                    $content[] = "**LINEAS**";
                    foreach ($bl->shipmentItems as $item) {
                        $content[] = "DESCRIPTION: /*" . ($item->item_description ?? '') . "*/";
                        $content[] = "COMMODITY_CODE: /*" . ($item->commodity_code ?? '') . "*/";
                        $content[] = "PACKAGES: /*" . ($item->package_quantity ?? 0) . "*/";
                        $content[] = "GROSS_WEIGHT: /*" . ($item->gross_weight_kg ?? 0) . "*/";
                        $content[] = "";
                    }
                }
                
                $content[] = ""; // Separador entre BLs
            }
        }
        
        return implode("\n", $content);
    }

    /**
     * Generar contenido EDI/CUSCAR estándar UN/EDIFACT
     */
    private function generateEdiCuscar(Voyage $voyage): string
    {
        $segments = [];
        
        // UNB - Service String Advice (Header)
        $segments[] = "UNB+UNOC:3+SENDER+RECEIVER+" . now()->format('yyMMdd:HHmm') . "+1'";
        
        // UNH - Message Header
        $segments[] = "UNH+1+CUSCAR:D:95B:UN'";
        
        // BGM - Beginning of Message  
        $segments[] = "BGM+85+{$voyage->voyage_number}+9'";
        
        // DTM - Date/Time
        $segments[] = "DTM+137:" . now()->format('YmdHHmm') . ":203'";
        
        // TDT - Details of Transport
        $vesselName = $voyage->shipments->first()->vessel->name ?? 'UNKNOWN';
        $segments[] = "TDT+20+{$voyage->voyage_number}++3:{$vesselName}'";
        
        // LOC - Place/Location Identification
        $originCode = $voyage->origin_port->code ?? 'ARUNKNOWN';
        $destCode = $voyage->destination_port->code ?? 'PYUNKNOWN';
        $segments[] = "LOC+5+{$originCode}:139:6'";
        $segments[] = "LOC+61+{$destCode}:139:6'";
        
        // Procesar cada BL
        foreach ($voyage->shipments as $shipment) {
            foreach ($shipment->billsOfLading as $bl) {
                // CNI - Consignment Information
                $segments[] = "CNI++" . ($bl->bl_number ?? '') . "'";
                
                // MOA - Monetary Amount (si hay valor declarado)
                if ($bl->shipmentItems && $bl->shipmentItems->sum('declared_value') > 0) {
                    $totalValue = $bl->shipmentItems->sum('declared_value');
                    $segments[] = "MOA+77:{$totalValue}:USD'";
                }
                
                // Datos del cargador
                if ($bl->shipper) {
                    $segments[] = "NAD+CZ+++" . substr($bl->shipper->legal_name, 0, 35) . "'";
                }
                
                // Datos del consignatario  
                if ($bl->consignee) {
                    $segments[] = "NAD+CN+++" . substr($bl->consignee->legal_name, 0, 35) . "'";
                }
                
                // Items de carga
                if ($bl->shipmentItems) {
                    foreach ($bl->shipmentItems as $item) {
                        $segments[] = "GDS++" . ($item->commodity_code ?? '') . ":" . substr($item->item_description ?? '', 0, 35) . "'";
                        $segments[] = "MEA+AAE+G+KGM:" . ($item->gross_weight_kg ?? 0) . "'";
                        $segments[] = "QTY+52:" . ($item->package_quantity ?? 0) . "'";
                    }
                }
            }
        }
        
        // UNT - Message Trailer
        $segmentCount = count($segments) + 1;
        $segments[] = "UNT+{$segmentCount}+1'";
        
        // UNZ - Service String Advice Trailer
        $segments[] = "UNZ+1+1'";
        
        return implode("\n", $segments);
    }
}