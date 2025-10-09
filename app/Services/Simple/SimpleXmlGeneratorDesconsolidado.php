<?php

namespace App\Services\Webservice\Argentina;

use App\Models\Voyage;
use App\Models\BillOfLading;
use App\Models\Container;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * GENERADOR XML AUTOMÁTICO - DESCONSOLIDADOS AFIP
 * 
 * Genera XML para webservice wgesinformacionanticipada
 * Métodos: RegistrarTitulosDesconsolidador, RectificarTitulosDesconsolidador, EliminarTitulosDesconsolidador
 * 
 * PATRÓN: Igual a SimpleXmlGeneratorAnticipada/MicDta
 * FUENTE: Manual AFIP wgesinformacionanticipada.pdf
 */
class SimpleXmlGeneratorDesconsolidado
{
    /**
     * Namespace SOAP de AFIP
     */
    private const NAMESPACE = 'Ar.Gob.Afip.Dga.Org.wgesinformacionanticipada';

    /**
     * @var Voyage
     */
    private $voyage;

    /**
     * @var string
     */
    private $identificadorViaje;

    public function __construct(Voyage $voyage)
    {
        $this->voyage = $voyage;
        $this->identificadorViaje = $voyage->afip_voyage_identifier ?? $voyage->voyage_number;
    }

    /**
     * REGISTRAR TÍTULOS DESCONSOLIDADOR
     * 
     * @param string $transactionId ID único de transacción
     * @return string XML generado
     */
    public function generateRegistrar(string $transactionId): string
    {
        // Obtener BLs desconsolidados (que tienen título madre)
        $billsOfLading = $this->getDesconsolidatedBills();

        if ($billsOfLading->isEmpty()) {
            throw new \Exception('No hay títulos desconsolidados para registrar en este viaje.');
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" ';
        $xml .= 'xmlns:ar="' . self::NAMESPACE . '">';
        $xml .= '<soap:Body>';
        $xml .= '<ar:RegistrarTitulosDesconsolidador>';
        $xml .= '<ar:argWSAutenticacionEmpresa>';
        $xml .= '<ar:Token>##TOKEN##</ar:Token>';
        $xml .= '<ar:Sign>##SIGN##</ar:Sign>';
        $xml .= '<ar:CuitEmpresaConectada>##CUIT##</ar:CuitEmpresaConectada>';
        $xml .= '<ar:TipoAgente>##TIPO_AGENTE##</ar:TipoAgente>';
        $xml .= '<ar:Rol>##ROL##</ar:Rol>';
        $xml .= '</ar:argWSAutenticacionEmpresa>';
        $xml .= '<ar:argRegistrarTitulosDesconsolidador>';
        $xml .= '<ar:IdTransaccion>' . $this->sanitize($transactionId) . '</ar:IdTransaccion>';
        $xml .= '<ar:InformacionTitulosDesconsolidadorDoc>';
        $xml .= '<ar:IdentificadorViaje>' . $this->sanitize($this->identificadorViaje) . '</ar:IdentificadorViaje>';
        $xml .= '<ar:TitulosDesconsolidador>';

        foreach ($billsOfLading as $bill) {
            $xml .= $this->generateTituloDesconsolidador($bill);
        }

        $xml .= '</ar:TitulosDesconsolidador>';
        $xml .= '</ar:InformacionTitulosDesconsolidadorDoc>';
        $xml .= '</ar:argRegistrarTitulosDesconsolidador>';
        $xml .= '</ar:RegistrarTitulosDesconsolidador>';
        $xml .= '</soap:Body>';
        $xml .= '</soap:Envelope>';

        return $xml;
    }

    /**
     * RECTIFICAR TÍTULOS DESCONSOLIDADOR
     * 
     * @param string $transactionId ID único de transacción
     * @return string XML generado
     */
    public function generateRectificar(string $transactionId): string
    {
        $billsOfLading = $this->getDesconsolidatedBills();

        if ($billsOfLading->isEmpty()) {
            throw new \Exception('No hay títulos desconsolidados para rectificar en este viaje.');
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" ';
        $xml .= 'xmlns:ar="' . self::NAMESPACE . '">';
        $xml .= '<soap:Body>';
        $xml .= '<ar:RectificarTitulosDesconsolidador>';
        $xml .= '<ar:argWSAutenticacionEmpresa>';
        $xml .= '<ar:Token>##TOKEN##</ar:Token>';
        $xml .= '<ar:Sign>##SIGN##</ar:Sign>';
        $xml .= '<ar:CuitEmpresaConectada>##CUIT##</ar:CuitEmpresaConectada>';
        $xml .= '<ar:TipoAgente>##TIPO_AGENTE##</ar:TipoAgente>';
        $xml .= '<ar:Rol>##ROL##</ar:Rol>';
        $xml .= '</ar:argWSAutenticacionEmpresa>';
        $xml .= '<ar:argRectificarTitulosDesconsolidador>';
        $xml .= '<ar:IdTransaccion>' . $this->sanitize($transactionId) . '</ar:IdTransaccion>';
        $xml .= '<ar:InformacionTitulosDesconsolidadorDoc>';
        $xml .= '<ar:IdentificadorViaje>' . $this->sanitize($this->identificadorViaje) . '</ar:IdentificadorViaje>';
        $xml .= '<ar:TitulosDesconsolidador>';

        foreach ($billsOfLading as $bill) {
            $xml .= $this->generateTituloDesconsolidador($bill);
        }

        $xml .= '</ar:TitulosDesconsolidador>';
        $xml .= '</ar:InformacionTitulosDesconsolidadorDoc>';
        $xml .= '</ar:argRectificarTitulosDesconsolidador>';
        $xml .= '</ar:RectificarTitulosDesconsolidador>';
        $xml .= '</soap:Body>';
        $xml .= '</soap:Envelope>';

        return $xml;
    }

    /**
     * ELIMINAR TÍTULOS DESCONSOLIDADOR
     * 
     * @param string $transactionId ID único de transacción
     * @return string XML generado
     */
    public function generateEliminar(string $transactionId): string
    {
        $billsOfLading = $this->getDesconsolidatedBills();

        if ($billsOfLading->isEmpty()) {
            throw new \Exception('No hay títulos desconsolidados para eliminar en este viaje.');
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" ';
        $xml .= 'xmlns:ar="' . self::NAMESPACE . '">';
        $xml .= '<soap:Body>';
        $xml .= '<ar:EliminarTitulosDesconsolidador>';
        $xml .= '<ar:argWSAutenticacionEmpresa>';
        $xml .= '<ar:Token>##TOKEN##</ar:Token>';
        $xml .= '<ar:Sign>##SIGN##</ar:Sign>';
        $xml .= '<ar:CuitEmpresaConectada>##CUIT##</ar:CuitEmpresaConectada>';
        $xml .= '<ar:TipoAgente>##TIPO_AGENTE##</ar:TipoAgente>';
        $xml .= '<ar:Rol>##ROL##</ar:Rol>';
        $xml .= '</ar:argWSAutenticacionEmpresa>';
        $xml .= '<ar:argEliminarTitulosDesconsolidador>';
        $xml .= '<ar:IdTransaccion>' . $this->sanitize($transactionId) . '</ar:IdTransaccion>';
        $xml .= '<ar:InformacionTitulosDesconsolidadorDoc>';
        $xml .= '<ar:IdentificadorViaje>' . $this->sanitize($this->identificadorViaje) . '</ar:IdentificadorViaje>';
        $xml .= '<ar:PuertosConocimientos>';

        foreach ($billsOfLading as $bill) {
            $xml .= '<ar:PuertoConocimiento>';
            $xml .= '<ar:CodigoPuertoEmbarque>' . $this->sanitize($bill->loadingPort->afip_code ?? '') . '</ar:CodigoPuertoEmbarque>';
            $xml .= '<ar:NumeroConocimiento>' . $this->sanitize($bill->bill_number) . '</ar:NumeroConocimiento>';
            $xml .= '</ar:PuertoConocimiento>';
        }

        $xml .= '</ar:PuertosConocimientos>';
        $xml .= '</ar:InformacionTitulosDesconsolidadorDoc>';
        $xml .= '</ar:argEliminarTitulosDesconsolidador>';
        $xml .= '</ar:EliminarTitulosDesconsolidador>';
        $xml .= '</soap:Body>';
        $xml .= '</soap:Envelope>';

        return $xml;
    }

    /**
     * GENERAR ESTRUCTURA DE TÍTULO DESCONSOLIDADOR
     * 
     * @param BillOfLading $bill
     * @return string XML del título
     */
    private function generateTituloDesconsolidador(BillOfLading $bill): string
    {
        $xml = '<ar:TituloDesconsolidador>';

        // IDENTIFICADOR TÍTULO MADRE (obligatorio para desconsolidados)
        if ($bill->master_bill_number) {
            $xml .= '<ar:IdentificadorTituloMadre>' . $this->sanitize($bill->master_bill_number) . '</ar:IdentificadorTituloMadre>';
        }

        // FECHA EMBARQUE (obligatorio)
        $fechaEmbarque = $bill->loading_date ? Carbon::parse($bill->loading_date)->format('Y-m-d\TH:i:s') : Carbon::now()->format('Y-m-d\TH:i:s');
        $xml .= '<ar:FechaEmbarque>' . $fechaEmbarque . '</ar:FechaEmbarque>';

        // PUERTO EMBARQUE (obligatorio)
        $xml .= '<ar:CodigoPuertoEmbarque>' . $this->sanitize($bill->loadingPort->afip_code ?? '') . '</ar:CodigoPuertoEmbarque>';

        // FECHA CARGA LUGAR ORIGEN (opcional para desconsolidados)
        if ($bill->origin_loading_date) {
            $fechaCargaOrigen = Carbon::parse($bill->origin_loading_date)->format('Y-m-d\TH:i:s');
            $xml .= '<ar:FechaCargaLugarOrigen>' . $fechaCargaOrigen . '</ar:FechaCargaLugarOrigen>';
        }

        // LUGAR ORIGEN (obligatorio si hay fecha)
        if ($bill->origin_location) {
            $xml .= '<ar:LugarOrigen>' . $this->sanitize($bill->origin_location) . '</ar:LugarOrigen>';
        }

        // PAÍS LUGAR ORIGEN (obligatorio)
        $xml .= '<ar:CodigoPaisLugarOrigen>' . $this->sanitize($bill->origin_country_code ?? 'AR') . '</ar:CodigoPaisLugarOrigen>';

        // NÚMERO CONOCIMIENTO (obligatorio)
        $xml .= '<ar:NumeroConocimiento>' . $this->sanitize($bill->bill_number) . '</ar:NumeroConocimiento>';

        // PUERTO TRASBORDO (opcional)
        if ($bill->transshipmentPort) {
            $xml .= '<ar:CodigoPuertoTrasbordo>' . $this->sanitize($bill->transshipmentPort->afip_code ?? '') . '</ar:CodigoPuertoTrasbordo>';
        }

        // PUERTO DESCARGA (obligatorio)
        $xml .= '<ar:CodigoPuertoDescarga>' . $this->sanitize($bill->dischargePort->afip_code ?? '') . '</ar:CodigoPuertoDescarga>';

        // FECHA DESCARGA (opcional)
        if ($bill->discharge_date) {
            $fechaDescarga = Carbon::parse($bill->discharge_date)->format('Y-m-d\TH:i:s');
            $xml .= '<ar:FechaDescarga>' . $fechaDescarga . '</ar:FechaDescarga>';
        }

        // PAÍS DESTINO (obligatorio)
        $xml .= '<ar:CodigoPaisDestino>' . $this->sanitize($bill->destination_country_code ?? 'AR') . '</ar:CodigoPaisDestino>';

        // MARCA BULTOS (obligatorio)
        $xml .= '<ar:MarcaBultos>' . $this->sanitize($bill->cargo_marks ?? 'N/A') . '</ar:MarcaBultos>';

        // CONSIGNATARIO (opcional)
        if ($bill->consignee) {
            $xml .= '<ar:Consignatario>' . $this->sanitize($bill->consignee->legal_name ?? $bill->consignee->name) . '</ar:Consignatario>';
        }

        // NOTIFICAR A (opcional)
        if ($bill->notifyParty) {
            $xml .= '<ar:NotificarA>' . $this->sanitize($bill->notifyParty->legal_name ?? $bill->notifyParty->name) . '</ar:NotificarA>';
        }

        // INDICADOR CONSOLIDADO (obligatorio S/N)
        $xml .= '<ar:IndicadorConsolidado>' . ($bill->is_consolidated ? 'S' : 'N') . '</ar:IndicadorConsolidado>';

        // INDICADOR TRÁNSITO/TRASBORDO (obligatorio S/N)
        $xml .= '<ar:IndicadorTransitoTrasbordo>' . ($bill->is_transit_transshipment ? 'S' : 'N') . '</ar:IndicadorTransitoTrasbordo>';

        // DESTINATARIO MERCADERÍA (opcional)
        // Si hay items, tomamos el primero que tenga destinatario
        $firstItem = $bill->shipmentItems->first();
        if ($firstItem && $firstItem->consignee_client_id) {
            $destinatario = $firstItem->consignee;
            if ($destinatario) {
                $xml .= '<ar:TipoDocumentoDestinatarioMercaderia>CUIT</ar:TipoDocumentoDestinatarioMercaderia>';
                $xml .= '<ar:IdentificadorDestinatarioMercaderia>' . $this->sanitize($destinatario->tax_id) . '</ar:IdentificadorDestinatarioMercaderia>';
            }
        }

        // POSICIÓN ARANCELARIA (obligatorio - tomamos del primer item)
        if ($firstItem && $firstItem->tariff_code) {
            $xml .= '<ar:PosicionArancelaria>' . $this->sanitize($firstItem->tariff_code) . '</ar:PosicionArancelaria>';
        } else {
            $xml .= '<ar:PosicionArancelaria>0000.00.00.000</ar:PosicionArancelaria>';
        }

        // INDICADORES AFIP (obligatorios S/N)
        $xml .= '<ar:IndicadorOperadorLogisticoSeguro>' . ($firstItem->is_secure_logistics_operator ?? 'N') . '</ar:IndicadorOperadorLogisticoSeguro>';
        $xml .= '<ar:IndicadorTransitoMonitoreado>' . ($firstItem->is_monitored_transit ?? 'N') . '</ar:IndicadorTransitoMonitoreado>';
        $xml .= '<ar:IndicadorRenar>' . ($firstItem->is_renar ?? 'N') . '</ar:IndicadorRenar>';

        // RAZÓN SOCIAL FORWARDER EXTERIOR (obligatorio)
        $xml .= '<ar:RazonSocialFowarderExterior>' . $this->sanitize($bill->shipper->legal_name ?? $bill->shipper->name ?? 'NO ESPECIFICADO') . '</ar:RazonSocialFowarderExterior>';

        // COMENTARIO (opcional)
        if ($bill->special_instructions) {
            $xml .= '<ar:Comentario>' . $this->sanitize(substr($bill->special_instructions, 0, 60)) . '</ar:Comentario>';
        }

        // ADUANA DESCARGA (obligatorio)
        $xml .= '<ar:CodigoAduanaDescarga>' . $this->sanitize($bill->discharge_customs_code ?? '001') . '</ar:CodigoAduanaDescarga>';

        // LUGAR OPERATIVO DESCARGA (obligatorio)
        $xml .= '<ar:CodigoLugarOperativoDescarga>' . $this->sanitize($bill->operational_discharge_code ?? '11021') . '</ar:CodigoLugarOperativoDescarga>';

        // MERCADERÍAS (líneas)
        $xml .= '<ar:Mercaderias>';
        foreach ($bill->shipmentItems as $item) {
            $xml .= $this->generateLineaMercaderia($item);
        }
        $xml .= '</ar:Mercaderias>';

        // CONTENEDORES
        $containers = $this->getContainersForBillOfLading($bill);
        if ($containers->isNotEmpty()) {
            $xml .= '<ar:Contenedores>';
            foreach ($containers as $container) {
                $xml .= $this->generateContenedor($container, $bill);
            }
            $xml .= '</ar:Contenedores>';
        }

        $xml .= '</ar:TituloDesconsolidador>';

        return $xml;
    }

    /**
     * GENERAR LÍNEA DE MERCADERÍA
     * 
     * @param \App\Models\ShipmentItem $item
     * @return string XML de la línea
     */
    private function generateLineaMercaderia($item): string
    {
        $xml = '<ar:LineaMercaderia>';
        
        // NÚMERO LÍNEA (obligatorio)
        $xml .= '<ar:NumeroLinea>' . $item->line_number . '</ar:NumeroLinea>';
        
        // CÓDIGO EMBALAJE (obligatorio)
        $xml .= '<ar:CodigoEmbalaje>' . $this->sanitize($item->packagingType->afip_code ?? '99') . '</ar:CodigoEmbalaje>';
        
        // TIPO EMBALAJE (opcional)
        if ($item->packagingType && $item->packagingType->afip_code !== '05') {
            $xml .= '<ar:TipoEmbalaje>A</ar:TipoEmbalaje>';
        }
        
        // CONDICIÓN CONTENEDOR (obligatorio si código embalaje = 05)
        if ($item->packagingType && $item->packagingType->afip_code === '05') {
            $xml .= '<ar:CondicionContenedor>P</ar:CondicionContenedor>';
        }
        
        // CANTIDAD MANIFESTADA (obligatorio)
        $xml .= '<ar:CantidadManifestada>' . ($item->quantity ?? 0) . '</ar:CantidadManifestada>';
        
        // PESO/VOLUMEN MANIFESTADO (obligatorio)
        $xml .= '<ar:PesoVolumenManifestado>' . number_format($item->gross_weight_kg ?? 0, 2, '.', '') . '</ar:PesoVolumenManifestado>';
        
        // DESCRIPCIÓN MERCADERÍA (obligatorio)
        $xml .= '<ar:DescripcionMercaderia>' . $this->sanitize(substr($item->description, 0, 80)) . '</ar:DescripcionMercaderia>';
        
        // NÚMERO BULTOS (obligatorio)
        $xml .= '<ar:NumeroBultos>' . $this->sanitize($item->package_marks ?? 'N/A') . '</ar:NumeroBultos>';
        
        // TIPO CARGA (opcional)
        if ($item->cargoType) {
            $xml .= '<ar:TipoCarga>' . $this->sanitize($item->cargoType->code) . '</ar:TipoCarga>';
        }
        
        // COMENTARIO (opcional)
        if ($item->special_instructions) {
            $xml .= '<ar:Comentario>' . $this->sanitize(substr($item->special_instructions, 0, 60)) . '</ar:Comentario>';
        }
        
        $xml .= '</ar:LineaMercaderia>';
        
        return $xml;
    }

    /**
     * GENERAR CONTENEDOR
     * 
     * @param Container $container
     * @param BillOfLading $bill
     * @return string XML del contenedor
     */
    private function generateContenedor(Container $container, BillOfLading $bill): string
    {
        $xml = '<ar:Contenedor>';
        
        // CUIT ATA OPERADOR CONTENEDOR (opcional)
        if ($container->operator_client_id) {
            $operator = $container->operatorClient;
            if ($operator && $operator->tax_id) {
                $xml .= '<ar:CuitAtaOperadorContenedor>' . $this->sanitize($operator->tax_id) . '</ar:CuitAtaOperadorContenedor>';
            }
        }
        
        // CARACTERÍSTICAS CONTENEDOR (opcional - ISO 6343)
        if ($container->containerType) {
            $xml .= '<ar:CaracteristicasContenedor>' . $this->sanitize($container->containerType->iso_code ?? '') . '</ar:CaracteristicasContenedor>';
        }
        
        // IDENTIFICADOR CONTENEDOR (obligatorio)
        $xml .= '<ar:IdentificadorContenedor>' . $this->sanitize($container->container_number) . '</ar:IdentificadorContenedor>';
        
        // CONDICIÓN CONTENEDOR (obligatorio H/P)
        $xml .= '<ar:CondicionContenedor>' . ($container->container_condition ?? 'P') . '</ar:CondicionContenedor>';
        
        // TARA (obligatorio)
        $xml .= '<ar:Tara>' . ($container->tare_weight_kg ?? 0) . '</ar:Tara>';
        
        // PESO BRUTO (obligatorio)
        $xml .= '<ar:PesoBruto>' . ($container->current_gross_weight_kg ?? 0) . '</ar:PesoBruto>';
        
        // NÚMERO PRECINTO ORIGEN (opcional)
        if ($container->shipper_seal) {
            $xml .= '<ar:NumeroPrecintoOrigen>' . $this->sanitize($container->shipper_seal) . '</ar:NumeroPrecintoOrigen>';
        }
        
        // FECHA VENCIMIENTO CONTENEDOR (opcional)
        if ($container->csc_expiry_date) {
            $fechaVencimiento = Carbon::parse($container->csc_expiry_date)->format('Y-m-d\TH:i:s');
            $xml .= '<ar:FechaVencimientoContenedor>' . $fechaVencimiento . '</ar:FechaVencimientoContenedor>';
        }
        
        // PUERTO EMBARQUE (opcional)
        $xml .= '<ar:CodigoPuertoEmbarque>' . $this->sanitize($bill->loadingPort->afip_code ?? '') . '</ar:CodigoPuertoEmbarque>';
        
        // FECHA EMBARQUE (opcional)
        if ($bill->loading_date) {
            $fechaEmbarque = Carbon::parse($bill->loading_date)->format('Y-m-d\TH:i:s');
            $xml .= '<ar:FechaEmbarque>' . $fechaEmbarque . '</ar:FechaEmbarque>';
        }
        
        // PUERTO DESCARGA (opcional)
        $xml .= '<ar:CodigoPuertoDescarga>' . $this->sanitize($bill->dischargePort->afip_code ?? '') . '</ar:CodigoPuertoDescarga>';
        
        // FECHA DESCARGA (opcional)
        if ($bill->discharge_date) {
            $fechaDescarga = Carbon::parse($bill->discharge_date)->format('Y-m-d\TH:i:s');
            $xml .= '<ar:FechaDescarga>' . $fechaDescarga . '</ar:FechaDescarga>';
        }
        
        // ADUANA (opcional)
        $xml .= '<ar:CodigoAduana>' . $this->sanitize($bill->discharge_customs_code ?? '') . '</ar:CodigoAduana>';
        
        // LUGAR OPERATIVO DESCARGA (opcional)
        $xml .= '<ar:CodigoLugarOperativoDescarga>' . $this->sanitize($bill->operational_discharge_code ?? '') . '</ar:CodigoLugarOperativoDescarga>';
        
        $xml .= '</ar:Contenedor>';
        
        return $xml;
    }

    /**
     * OBTENER TODOS LOS CONTENEDORES DE UN BILL OF LADING
     * Combina relación directa + tabla pivot para cobertura total
     * 
     * NOTA: unique('id') previene duplicados en caso de datos inconsistentes
     * donde un contenedor esté en AMBAS relaciones simultáneamente
     * 
     * @param BillOfLading $bill
     * @return Collection
     */
    private function getContainersForBillOfLading(BillOfLading $bill): Collection
    {
        // Contenedores con relación directa (master/vacíos)
        $directContainers = $bill->containers;
        
        // Contenedores de items con carga distribuida (tabla pivot)
        $itemContainers = Container::whereHas('shipmentItems', function($q) use ($bill) {
            $q->where('bill_of_lading_id', $bill->id);
        })->with('containerType', 'operatorClient')->get();
        
        // Combinar y eliminar duplicados por ID
        // Si un contenedor está en ambas relaciones (error de datos), 
        // solo se incluirá una vez
        return $directContainers->merge($itemContainers)->unique('id');
    }

    /**
     * OBTENER BILLS OF LADING DESCONSOLIDADOS DEL VIAJE
     * Son aquellos que tienen master_bill_number (título madre)
     * 
     * @return Collection
     */
    private function getDesconsolidatedBills(): Collection
    {
        return BillOfLading::whereHas('shipment', function($q) {
            $q->where('voyage_id', $this->voyage->id);
        })
        ->whereNotNull('master_bill_number')
        ->with([
            'loadingPort',
            'dischargePort',
            'transshipmentPort',
            'shipper',
            'consignee',
            'notifyParty',
            'shipmentItems.packagingType',
            'shipmentItems.cargoType',
            'shipmentItems.consignee',
            'containers.containerType',
            'containers.operatorClient'
        ])
        ->get();
    }

    /**
     * SANITIZAR TEXTO PARA XML
     * 
     * @param string|null $text
     * @return string
     */
    private function sanitize(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Reemplazar entidades XML
        $text = str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'], $text);
        
        // Remover caracteres no imprimibles
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }
}