<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Container;
use App\Models\Voyage;
use App\Models\VoyageAttachment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * SISTEMA SIMPLE WEBSERVICES - Generador XML Paraguay
 *
 * Genera XML automáticamente desde BD para webservices DNA Paraguay (GDSF)
 * Sigue el mismo patrón exitoso de SimpleXmlGenerator (Argentina)
 *
 * MÉTODOS GDSF SOPORTADOS:
 * - XFFM: Carátula/Manifiesto Fluvial
 * - XFBL: Conocimientos/BLs
 * - XFBT: Hoja de Ruta/Barcazas
 * - XISP: Incluir Embarcación
 * - XRSP: Desvincular Embarcación
 * - XFCT: Cerrar Viaje
 *
 * IMPORTANTE:
 * - USA SOLO campos verificados de modelos
 * - NO inventa datos
 * - Genera XML según especificación GDSF
 */
class SimpleXmlGeneratorParaguay
{
    private Company $company;
    private array $config;

    /**
     * Códigos EDIFACT 1001 para tipos de documentos
     * Referencia: Manual GDSF DNA Paraguay
     */
    private const DOCUMENT_TYPE_CODES = [
        'invoice' => '380',
        'commercial_invoice' => '380',
        'factura' => '380',
        'packing_list' => '271',
        'lista_empaque' => '271',
        'certificate' => '861',
        'certificate_origin' => '861',
        'certificate_origin' => '705',  //  (Certificado de Origen)
        'certificado_origen' => '705',   
        'certificate_phytosanitary' => '710',  //  (Certificado Fitosanitario)
        'certificado_fitosanitario' => '710', 
        'certificado' => '861',
        'permit' => '911',
        'permiso' => '911',
        'license' => '911',
        'licencia' => '911',
        'other' => '999',
        'otro' => '999',
    ];

    /**
     * Tamaño máximo de archivo para DocAnexo (5MB en bytes)
     */
    private const MAX_FILE_SIZE_BYTES = 5242880;

    public function __construct(Company $company, array $config = [])
    {
        $this->company = $company;
        $this->config = $config;
    }

    /**
     * XFFM - Carátula/Manifiesto Fluvial
     * Primer mensaje obligatorio - Registra el viaje en DNA Paraguay
     *
     * ✅ CORREGIDO según XML exitoso de Roberto Benbassat
     *
     * @return string XML completo
     */
    public function createXffmXml(Voyage $voyage, string $transactionId): string
    {
        try {
            // Cargar relaciones necesarias
            $voyage->load([
                'leadVessel.flagCountry',
                'leadVessel.vesselType',
                'originPort.country',
                'destinationPort.country',
                'company',
            ]);

            // Cargar containers del voyage para el XML
            $containers = Container::whereHas('shipmentItems.billOfLading.shipment', function($q) use ($voyage) {
                $q->where('voyage_id', $voyage->id);
            })->with(['containerType', 'shipmentItems'])->get();

            $w = new \XMLWriter;
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // ❌ SIN SOAP Envelope - XML directo según Roberto
            $w->startElement('MicDta');

            // ✅ CAMPO 1: fechEmi (fecha emisión del manifiesto)
            $fechEmi = $voyage->departure_date
                ? Carbon::parse($voyage->departure_date)->format('Y-m-d\TH:i:s')
                : now()->format('Y-m-d\TH:i:s');
            $w->writeElement('fechEmi', $fechEmi);

            // ✅ CAMPO 2: idTransaccion
            $w->writeElement('idTransaccion', substr($transactionId, 0, 20));

            // ✅ CAMPO 3: viaTrans (8 = Fluvial según tabla DNA)
            $w->writeElement('viaTrans', '8');

            // ✅ CAMPO 4: codPaisProc (país de procedencia - código alpha2)
            $w->writeElement('codPaisProc',
                $voyage->originPort->country->alpha2_code 
            );

            // ✅ CAMPO 5: transportistas (PLURAL con sub-elemento singular)
            $w->startElement('transportistas');
            $w->startElement('transportista');

            // codPais del transportista
            $w->writeElement('codPais',
                $this->company->country
            );

            // idFiscal (RUC/CUIT)
            $w->writeElement('idFiscal',
                $this->company->tax_id ?? '0'
            );

            // nombre
            $w->writeElement('nombre', htmlspecialchars(
                substr($this->company->legal_name ?? 'NO ESPECIFICADO', 0, 100)
            ));

            // tipTrans (R=Remitente, T=Transportista)
            $w->writeElement('tipTrans', 'R');

            // ✅ direccion (con sub-elementos según Roberto)
            $w->startElement('direccion');
            $w->writeElement('barrio', htmlspecialchars(
                substr($this->company->address ?? 'NO ESPECIFICADO', 0, 50)
            ));
            $w->writeElement('ciudad', htmlspecialchars(
                substr($this->company->city ?? 'NO ESPECIFICADO', 0, 50)
            ));
            $w->writeElement('codPostal', htmlspecialchars(
                substr($this->company->postal_code ?? '000', 0, 10)
            ));
            $w->writeElement('estado', htmlspecialchars(
                substr($this->company->country ?? 'PARAGUAYO', 0, 50)
            ));
            $w->writeElement('nombreCalle', htmlspecialchars(
                substr($this->company->address ?? 'NO ESPECIFICADO', 0, 100)
            ));
            $w->endElement(); // direccion

            $w->endElement(); // transportista
            $w->endElement(); // transportistas

            // ✅ CAMPO 6: embarcaciones (PLURAL con sub-elemento singular)
            $w->startElement('embarcaciones');
            $w->startElement('embarcacion');

            $vessel = $voyage->leadVessel;

            // codPais de la embarcación
            $w->writeElement('codPais',
                $vessel->flagCountry->alpha2_code
            );

            // id (matrícula/registro)
            $w->writeElement('id', htmlspecialchars(
                substr($vessel->registration_number ?? 'SIN-REGISTRO', 0, 20)
            ));

            // tipEmb (tipo de embarcación - BUM=Barcaza, REM=Remolcador)
            $vesselTypeCode = 'BUM'; // Default
            if ($vessel->vesselType) {
                $typeMap = [
                    'barge' => 'BUM',
                    'tugboat' => 'REM',
                    'push_boat' => 'REM',
                ];
                $vesselTypeCode = $typeMap[$vessel->vesselType->code] ?? 'BUM';
            }
            $w->writeElement('tipEmb', $vesselTypeCode);

            // nombreEmb (nombre de la embarcación)
            $w->writeElement('nombreEmb', htmlspecialchars(
                substr($vessel->name ?? 'NO ESPECIFICADO', 0, 50)
            ));

            // indenLastre (indicador en lastre: N=con carga, S=vacío)
            $indenLastre = ($voyage->is_empty_transport === 'S') ? 'S' : 'N';
            $w->writeElement('indenLastre', $indenLastre);

            // ✅ CONTENEDORES (si hay)
            if ($containers->isNotEmpty()) {
                $w->startElement('contenedores');

                foreach ($containers as $container) {
                    $w->startElement('contenedor');

                    // condicion (P=Pier/Muelle, H=House/Casa)
                    $condicion = $container->container_condition === 'H' ? 'H' : 'P';
                    $w->writeElement('condicion', $condicion);

                    // id (número del contenedor)
                    $w->writeElement('id', htmlspecialchars(
                        substr($container->full_container_number ??
                            $container->container_number ?? 'SIN-NUMERO', 0, 20)
                    ));

                    // medidas (40DV, 20GP, etc.)
                    $w->writeElement('medidas', htmlspecialchars(
                        substr($container->containerType->iso_code ?? '40DV', 0, 4)
                    ));

                    // ✅ PRECINTOS (solo si hay al menos uno)
                    if ($container->carrier_seal || $container->customs_seal) {
                        $w->startElement('precintos');

                        if ($container->carrier_seal) {
                            $w->startElement('precinto');
                            $w->writeElement('nroPrecinto', htmlspecialchars(
                                substr($container->carrier_seal, 0, 35)
                            ));
                            $w->writeElement('tipPrecin', 'BC');
                            $w->endElement(); // precinto
                        }

                        if ($container->customs_seal) {
                            $w->startElement('precinto');
                            $w->writeElement('nroPrecinto', htmlspecialchars(
                                substr($container->customs_seal, 0, 35)
                            ));
                            $w->writeElement('tipPrecin', 'BC');
                            $w->endElement(); // precinto
                        }

                        $w->endElement(); // precintos
                    }

                    $w->endElement(); // contenedor
                }

                $w->endElement(); // contenedores
            }

            $w->endElement(); // embarcacion
            $w->endElement(); // embarcaciones

            $w->endElement(); // MicDta

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFFM generado (CORREGIDO)', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'containers_count' => $containers->count(),
                'xml_length' => strlen($xmlContent),
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFFM', [
                'voyage_id' => $voyage->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * XFBL - Conocimientos/BLs
     * Mensaje para declarar los Bills of Lading del viaje
     * Requiere XFFM enviado previamente
     *
     * ✅ CORREGIDO según XML exitoso de Roberto Benbassat
     *
     * @param  string|null  $nroViaje  Número de viaje retornado por XFFM
     * @return string XML completo
     */
    public function createXfblXml(Voyage $voyage, string $transactionId, ?string $nroViaje = null): string
    {
        try {
            // Cargar Bills of Lading con todas sus relaciones
            $voyage->load([
                'shipments.billsOfLading.shipper',
                'shipments.billsOfLading.consignee',
                'shipments.billsOfLading.shipmentItems',
                'leadVessel',
            ]);

            // Obtener todos los BLs del viaje
            $billsOfLading = $voyage->shipments->flatMap->billsOfLading;

            if ($billsOfLading->isEmpty()) {
                throw new Exception('No hay Bills of Lading para generar XFBL');
            }

            // Obtener containers del voyage
            $voyageContainers = Container::whereHas('shipmentItems.shipment', function ($q) use ($voyage) {
                $q->where('voyage_id', $voyage->id);
            })->get();

            $w = new \XMLWriter;
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // ❌ SIN SOAP Envelope - XML directo
            $w->startElement('titTrans'); // Plural contenedor

            foreach ($billsOfLading as $bl) {
                $w->startElement('titTran'); // Singular

                // ✅ 1. BULTOS (PRIMERO según Roberto)
                $w->startElement('bultos');
                $w->startElement('bulto');

                $w->writeElement('cantBultos', (string) ($bl->total_packages ?? 1));
                $w->writeElement('cantTotalBultos', (string) ($bl->total_packages ?? 1));
                $w->writeElement('pesoBruto', number_format($bl->gross_weight_kg ?? 0, 3, '.', ''));
                $w->writeElement('pesoTotalBruto', number_format($bl->gross_weight_kg ?? 0, 3, '.', ''));

                // Indicador carga suelta (S/N)
                // Si el packaging type es suitable_for_bulk_cargo, entonces es carga suelta
                $isBulkCargo = $bl->primaryPackagingType?->suitable_for_bulk_cargo ?? false;
                $w->writeElement('indCargSuelt', $isBulkCargo ? 'S' : 'N');

                // Marcas y números
                $w->writeElement('marcaNro', htmlspecialchars(
                    substr($bl->cargo_marks ?? 'S/M', 0, 500)
                ));

                // Tipo de embalaje (código según tabla DNA)
                $packagingCode = $bl->primaryPackagingType->code ?? '99';
                $w->writeElement('tipEmbalaje', $packagingCode);

                // Indicador combustible
                $w->writeElement('indCombustible', 'N');
                $w->writeElement('cantLitros', '0');

                // Descripción mercadería
                $w->writeElement('descMercaderia', htmlspecialchars(
                    substr($bl->cargo_description ?? 'MERCADERIA GENERAL', 0, 500)
                ));

                $w->endElement(); // bulto
                $w->endElement(); // bultos

                // ✅ 2. PESO BRUTO TOTAL
                $w->writeElement('pesoBrutoTotal', number_format($bl->gross_weight_kg ?? 0, 3, '.', ''));

                // ✅ 3. CONSIGNATARIO
                $w->startElement('consignatario');
                $consignee = $bl->consignee;

                // Determinar si es tránsito PRIMERO
                $loadingCountry = $bl->loadingPort->country->alpha2_code ?? '';
                $dischargeCountry = $bl->dischargePort->country->alpha2_code ?? '';
                $isTransit = ($loadingCountry !== 'PY' && $dischargeCountry !== 'PY');

                // CASO e) - Si es tránsito, idFiscal debe ser 0 según DNA Paraguay
                $idFiscal = $isTransit ? '0' : (string) ($consignee->tax_id ?? '0');
                $w->writeElement('idFiscal', $idFiscal);

                // Nombre/razón social siempre se envía (información del destinatario real)
                $w->writeElement('nomRazSoc', htmlspecialchars(
                    substr($consignee->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                ));

                // Dirección: si es tránsito y no hay dirección, usar genérica
                $w->startElement('direccion');
                $direccion = $consignee->address ?? null;
                if ($isTransit && empty($direccion)) {
                    $direccion = 'EN TRANSITO INTERNACIONAL';
                }
                $w->writeElement('nombreCalle', htmlspecialchars(
                    substr($direccion ?? 'NO ESPECIFICADO', 0, 100)
                ));
                $w->endElement(); // direccion
                $w->endElement(); // consignatario

                // ✅ 4. DESCRIPCIÓN MERCADERÍA (repetida)
                $w->writeElement('descMercaderia', htmlspecialchars(
                    substr($bl->cargo_description ?? 'MERCADERIA GENERAL', 0, 500)
                ));

                // ✅ 5. ID MIC/DTA PRIMERA FRACCIÓN (vacío si no aplica)
                $w->writeElement('idMicDtaPriFracc', '');

                // ✅ 6. ID TÍTULO TRANSPORTE (número de BL)
                $w->writeElement('idTitTrans', htmlspecialchars(
                    substr($bl->bill_number ?? 'SIN-BL', 0, 18)
                ));

                // ✅ 7. INDICADOR CONSOLIDADO
                $w->writeElement('idConsol', $bl->is_consolidated ? 'S' : 'N');

                // ✅ 8. INDICADOR FINALIDAD COMERCIAL
                $w->writeElement('indFinCom', 'S'); // S=Sí tiene fin comercial

                // ✅ 9. INDICADOR FRACCIONAMIENTO TRANSPORTE
                $indFraccTransp = $bl->is_consolidated ? 'S' : 'N'; // Indicador fraccionamiento: S si es consolidado, N si no
                $w->writeElement('indFraccTransp', $indFraccTransp);

                // ✅ 10. REMITENTE
                $w->startElement('remitente');
                $shipper = $bl->shipper;
                $w->writeElement('idFiscal', (string) ($shipper->tax_id ?? '0'));
                $w->writeElement('nomRazSoc', htmlspecialchars(
                    substr($shipper->legal_name ?? 'NO ESPECIFICADO', 0, 100)
                ));
                $w->writeElement('nroDocIdent', (string) ($shipper->tax_id ?? '0'));
                $w->writeElement('tipDocIdent', 'CI'); // CI=CUIT/RUC
                $w->startElement('direccion');
                $w->writeElement('nombreCalle', htmlspecialchars(
                    substr($shipper->address ?? 'NO ESPECIFICADO', 0, 100)
                ));
                $w->endElement(); // direccion
                $w->endElement(); // remitente

                // ✅ 11. INDICADOR TRÁFICO (IMP/EXP/TRA)
                // Determinar según puertos
                $indTra = 'IMP'; // Default
                if ($bl->loadingPort && $bl->dischargePort) {
                    $loadingCountry = $bl->loadingPort->country->alpha2_code ?? '';
                    $dischargeCountry = $bl->dischargePort->country->alpha2_code ?? '';

                    if ($loadingCountry === 'PY') {
                        $indTra = 'EXP';
                    } elseif ($dischargeCountry === 'PY') {
                        $indTra = 'IMP';
                    } else {
                        $indTra = 'TRA';
                    }
                }
                if ($bl->loadingPort && $bl->dischargePort) {
                    $loadingCountry = $bl->loadingPort->country->alpha2_code ?? '';
                    $dischargeCountry = $bl->dischargePort->country->alpha2_code ?? '';

                    if ($loadingCountry === 'PY') {
                        $indTra = 'EXP'; // Exportación desde Paraguay
                    } elseif ($dischargeCountry === 'PY') {
                        $indTra = 'IMP'; // Importación a Paraguay
                    } else {
                        $indTra = 'TRA'; // Tránsito
                    }
                }
                $w->writeElement('indTra', $indTra);

                // ✅ 12. NÚMERO DE BL (repetido)
                $w->writeElement('nroBL', htmlspecialchars(
                    substr($bl->bill_number ?? 'SIN-BL', 0, 18)
                ));

                // ✅ 13. CÓDIGO DELEGACIÓN ADUANERA
                // Determinar INTRAZONA vs EXTRAZONA
                $loadingCountry = $bl->loadingPort->country->alpha2_code ?? '';
                $dischargeCountry = $bl->dischargePort->country->alpha2_code ?? '';

                // INTRAZONA: Ambos puertos en Paraguay → codDelegacion vacío
                // EXTRAZONA: Al menos un puerto fuera de Paraguay → código de delegación
                $codDelegacion = '';

                if ($loadingCountry !== 'PY' || $dischargeCountry !== 'PY') {
                    // EXTRAZONA - Necesita código de delegación
                    if ($bl->dischargeCustoms) {
                        $codDelegacion = $bl->dischargeCustoms->code ?? '';
                    }
                    
                    // Si no hay código y no es Intrazona, usar default solo para Extrazona
                    if (empty($codDelegacion) && $loadingCountry !== 'PY') {
                        // El puerto de carga es el extranjero, usar su código
                        $codDelegacion = $bl->loadingPort->unlocode ?? 'BAI';
                    }
                }

                // IMPORTANTE: En Intrazona el elemento se envía vacío o se omite según Manual GDSF
                $w->writeElement('codDelegacion', $codDelegacion);
                $w->writeElement('codDelegacion', $codDelegacion);

                // ✅ 14. EMBARCACIONES DEL TÍTULO
                // Soportar múltiples embarcaciones en el mismo viaje
                $w->startElement('TitEmbarcaciones');

                // Obtener TODAS las embarcaciones únicas asociadas a este BL
                // Un BL puede tener múltiples shipments (ej: transbordo, convoy)
                $vesselIds = collect();

                // Primero: obtener vessel del shipment directo del BL
                if ($bl->shipment && $bl->shipment->vessel_id) {
                    $vesselIds->push($bl->shipment->vessel_id);
                }

                // Segundo: si el BL tiene containers, pueden estar en otros shipments del mismo voyage
                $blContainers = $voyageContainers->filter(function ($container) use ($bl) {
                    return $container->shipmentItems->contains(function ($item) use ($bl) {
                        return $item->bill_of_lading_id === $bl->id;
                    });
                });

                foreach ($blContainers as $container) {
                    foreach ($container->shipmentItems as $item) {
                        if ($item->billOfLading && $item->billOfLading->shipment) {
                            $vesselIds->push($item->billOfLading->shipment->vessel_id);
                        }
                    }
                }

                // Eliminar duplicados y obtener vessels
                $uniqueVesselIds = $vesselIds->unique()->filter();

                if ($uniqueVesselIds->isEmpty()) {
                    // Fallback: usar leadVessel del voyage
                    $uniqueVesselIds = collect([$voyage->lead_vessel_id])->filter();
                }

                // Generar TitEmbarcacion por cada vessel único
                foreach ($uniqueVesselIds as $vesselId) {
                    $vessel = \App\Models\Vessel::find($vesselId);
                    if ($vessel && $vessel->registration_number) {
                        $w->startElement('TitEmbarcacion');
                        $w->writeElement('idEmbarcacion', htmlspecialchars(
                            substr($vessel->registration_number, 0, 20)
                        ));
                        $w->endElement(); // TitEmbarcacion
                    }
                }

                $w->endElement(); // TitEmbarcaciones

                // ✅ 15. CONTENEDORES DEL TÍTULO
                // Obtener contenedores asociados a este BL
                $blContainers = $voyageContainers->filter(function ($container) use ($bl) {
                    return $container->shipmentItems->contains(function ($item) use ($bl) {
                        return $item->bill_of_lading_id === $bl->id;
                    });
                });

                if ($blContainers->isNotEmpty()) {
                    $w->startElement('TitContenedores');
                    $w->startElement('TitContenedor');

                    foreach ($blContainers as $container) {
                        $w->writeElement('idContenedor', htmlspecialchars(
                            substr($container->full_container_number ??
                                $container->container_number ?? 'SIN-CONT', 0, 20)
                        ));
                    }

                    $w->endElement(); // TitContenedor
                    $w->endElement(); // TitContenedores
                }

                // ✅ 16. DOCUMENTOS ANEXOS (opcional)
                $attachments = \App\Models\VoyageAttachment::where('voyage_id', $voyage->id)
                    ->where(function($q) use ($bl) {
                        $q->where('bill_of_lading_id', $bl->id)
                        ->orWhereNull('bill_of_lading_id');
                    })
                    ->get();

                if ($attachments->isNotEmpty()) {
                    foreach ($attachments as $attachment) {
                        $w->startElement('DocAnexo');
                        
                            // Número/referencia del documento (OBLIGATORIO)
                            $documento = $attachment->document_number ?? $attachment->original_name;
                            $w->writeElement('documento', htmlspecialchars(substr($documento, 0, 39)));
                            
                            // Tipo de documento (OBLIGATORIO)
                            $w->writeElement('tipDoc', $this->getDocumentTypeCode($attachment->document_type));
                            
                            // Archivo en Base64 (OPCIONAL - max 5MB)
                            $validation = $this->canIncludeFileInDocAnexo($attachment);
                            
                            if ($validation['can_include']) {
                                try {
                                    $base64Content = $attachment->getBase64Content();
                                    $w->writeElement('archivo', $base64Content);
                                    
                                    Log::info('DocAnexo: archivo incluido', [
                                        'attachment_id' => $attachment->id,
                                        'file_size' => $validation['file_size'],
                                        'document' => $documento,
                                    ]);
                                } catch (\Exception $e) {
                                    Log::warning('DocAnexo: error al cargar archivo, se envía solo referencia', [
                                        'attachment_id' => $attachment->id,
                                        'error' => $e->getMessage(),
                                        'document' => $documento,
                                    ]);
                                }
                            } else {
                                Log::warning('DocAnexo: archivo no incluido', [
                                    'attachment_id' => $attachment->id,
                                    'reason' => $validation['reason'],
                                    'file_size' => $validation['file_size'] ?? null,
                                    'document' => $documento,
                                ]);
                            }
                            
                        $w->endElement(); // DocAnexo
                    }
                }

                $w->endElement(); // titTran
            }

            $w->endElement(); // titTrans

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFBL generado (CORREGIDO)', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'nro_viaje' => $nroViaje,
                'bls_count' => $billsOfLading->count(),
                'xml_length' => strlen($xmlContent),
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFBL', [
                'voyage_id' => $voyage->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * XFBT - Hoja de Ruta (Rutas e Itinerarios)
     * Declara las rutas del viaje con origen, destino, fechas y aduanas
     *
     * ✅ CORREGIDO según XML exitoso de Roberto Benbassat
     *
     * @param  string|null  $nroViaje  Número de viaje retornado por XFFM
     * @return string XML completo
     */
    public function createXfbtXml(Voyage $voyage, string $transactionId, ?string $nroViaje = null): string
    {
        try {
            // Cargar relaciones necesarias para rutas
            $voyage->load([
                'originPort.country',
                'destinationPort.country',
                'originCustoms',
                'destinationCustoms',
                'shipments.billsOfLading',
            ]);

            // Obtener todos los BLs para generar una ruta por cada uno
            $billsOfLading = $voyage->shipments->flatMap->billsOfLading;

            if ($billsOfLading->isEmpty()) {
                throw new Exception('No hay Bills of Lading para generar XFBT');
            }

            $w = new \XMLWriter;
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // ❌ SIN SOAP Envelope - XML directo
            $w->startElement('RutasInf'); // Plural contenedor

            foreach ($billsOfLading as $bl) {
                $w->startElement('rutInf'); // Singular

                // ✅ 1. DESCRIPCIÓN RUTA ITINERARIOS
                $originPort = $voyage->originPort;
                $destPort = $voyage->destinationPort;
                $descRuta = sprintf('%s-%s',
                    $originPort->name ?? 'ORIGEN',
                    $destPort->name ?? 'DESTINO'
                );
                $w->writeElement('descRutItinerarios', htmlspecialchars(
                    substr($descRuta, 0, 500)
                ));

                // ✅ 2. FECHA LLEGADA PREVISTA (arribo)
                $fechLlegPrev = $voyage->estimated_arrival_date
                    ? Carbon::parse($voyage->estimated_arrival_date)->format('Y-m-d\TH:i:s')
                    : Carbon::now()->addDays(7)->format('Y-m-d\TH:i:s');
                $w->writeElement('fechLlegPrev', $fechLlegPrev);

                // ✅ 3. FECHA PARTIDA (embarque)
                $fechPart = $voyage->departure_date
                    ? Carbon::parse($voyage->departure_date)->format('Y-m-d\TH:i:s')
                    : now()->format('Y-m-d\TH:i:s');
                $w->writeElement('fechPart', $fechPart);

                // ✅ 4. PAÍS DE ORIGEN
                $w->startElement('paisDeOrigen');

                // País de partida (carga)
                $loadingCountry = $bl->loadingPort->country ?? $originPort->country;
                $w->writeElement('codPaisPart',
                    $loadingCountry->alpha2_code 
                );

                // Ciudad de partida (UNLOCODE)
                $w->writeElement('codCiuPart',
                    $bl->loadingPort->unlocode ?? $originPort->unlocode ?? 'BAI'
                );

                // País de salida
                $w->writeElement('codPaisSal',
                    $originPort->country->alpha2_code 
                );

                // Ciudad de salida (UNLOCODE)
                $w->writeElement('codCiuSal',
                    $originPort->unlocode ?? 'BAI'
                );

                // Lugar operativo de salida (UNLOCODE completo con país)
                $lugOperSal = $originPort->country->alpha2_code ;
                $lugOperSal .= $originPort->unlocode ?? 'BAI';
                $w->writeElement('codLugOperSal', $lugOperSal);

                $w->endElement(); // paisDeOrigen

                // ✅ 5. PAÍS DESTINO
                $w->startElement('paisDest');

                $destCountry = $destPort->country->alpha2_code;
                $w->writeElement('codPais', $destCountry);

                // Solo enviar aduanas si destino es Paraguay
                if ($destCountry === 'PY') {
                    // Aduana de entrada (primera que toca - usar transshipment como entrada si existe)
                    $aduanaEntrada = $voyage->transshipmentCustoms->code 
                                ?? $voyage->destinationCustoms->code;
                    
                    // Aduana de destino (destino final)
                    $aduanaDestino = $voyage->destinationCustoms->code;
                    
                    if ($aduanaEntrada) {
                        $w->writeElement('codAduEnt', $aduanaEntrada);
                    }
                    
                    if ($aduanaDestino) {
                        $w->writeElement('codAduDest', $aduanaDestino);
                    }
                }

                $w->endElement(); // paisDest

                // ✅ 6. PLAZO ORIGEN-DESTINO (días de viaje)
                $plazo = 2; // Default
                if ($voyage->departure_date && $voyage->estimated_arrival_date) {
                    $plazo = Carbon::parse($voyage->departure_date)
                        ->diffInDays(Carbon::parse($voyage->estimated_arrival_date));
                }
                $w->writeElement('plazoOrigenDestino', (string) $plazo);

                // ✅ 7. ID TÍTULO TRANSPORTE (número de BL)
                $w->writeElement('idTitTrans', htmlspecialchars(
                    substr($bl->bill_number ?? 'SIN-BL', 0, 18)
                ));

                $w->endElement(); // rutInf
            }

            $w->endElement(); // RutasInf

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFBT generado (CORREGIDO)', [
                'voyage_id' => $voyage->id,
                'transaction_id' => $transactionId,
                'nro_viaje' => $nroViaje,
                'routes_count' => $billsOfLading->count(),
                'xml_length' => strlen($xmlContent),
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFBT', [
                'voyage_id' => $voyage->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * XFCT - Cerrar Viaje
     * Cierra el número de viaje cuando todo está completo
     *
     * ✅ CORREGIDO según Manual GDSF página 18
     *
     * @param  string  $nroViaje  Número de viaje retornado por DNA
     * @param  string|null  $observaciones  Observaciones del cierre (opcional)
     * @return string XML completo
     */
    public function createXfctXml(string $nroViaje, string $transactionId, ?string $observaciones = null): string
    {
        try {
            $w = new \XMLWriter;
            $w->openMemory();
            $w->startDocument('1.0', 'UTF-8');

            // ❌ SIN SOAP Envelope - XML directo (siguiendo patrón de los otros 3)
            // Nota: El manual no especifica el nombre del elemento raíz para XFCT,
            // pero por consistencia usamos un patrón similar a los demás mensajes
            $w->startElement('CerrarViaje');

            // ✅ Campo obligatorio según manual
            $w->writeElement('nroViaje', htmlspecialchars($nroViaje));

            // ✅ Campo opcional según manual (página 18)
            if ($observaciones) {
                $w->writeElement('Obs', htmlspecialchars(
                    substr($observaciones, 0, 100)
                ));
            }

            $w->endElement(); // CerrarViaje

            $w->endDocument();
            $xmlContent = $w->outputMemory();

            Log::info('XML XFCT generado (CORREGIDO)', [
                'nro_viaje' => $nroViaje,
                'transaction_id' => $transactionId,
                'has_observaciones' => ! empty($observaciones),
                'xml_length' => strlen($xmlContent),
            ]);

            return $xmlContent;

        } catch (Exception $e) {
            Log::error('Error generando XML XFCT', [
                'nro_viaje' => $nroViaje ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * HELPERS - Mapeo de códigos según tablas DNA Paraguay
     */

    /**
     * Mapear código de país a formato DNA (3 dígitos)
     */
    private function getCountryCode(string $alpha2Code): string
    {
        $mapping = [
            'AR' => '032', // Argentina
            'PY' => '600', // Paraguay
            'BR' => '076', // Brasil
            'UY' => '858', // Uruguay
            'BO' => '068', // Bolivia
            'US' => '840', // Estados Unidos
        ];

        return $mapping[strtoupper($alpha2Code)] ?? '032';
    }

    /**
     * Mapear tipo de documento
     */
    private function getDocumentType(string $type): string
    {
        $mapping = [
            'DNI' => '1',
            'PASSPORT' => '2',
            'CI' => '3', // Cédula de Identidad
            'RUC' => '4', // Paraguay
            'CUIT' => '5', // Argentina
        ];

        return $mapping[strtoupper($type)] ?? '1';
    }

    /**
     * Mapear tipo de documento a código EDIFACT 1001
     */
    private function getDocumentTypeCode(?string $documentType): string
    {
        if (!$documentType) {
            return '999'; // Otros
        }
        
        $normalized = strtolower(trim($documentType));
        return self::DOCUMENT_TYPE_CODES[$normalized] ?? '999';
    }

    /**
     * Validar si un archivo puede ser incluido en DocAnexo
     */
    private function canIncludeFileInDocAnexo(VoyageAttachment $attachment): array
    {
        // Verificar que existe el archivo
        if (!$attachment->file_path || !\Storage::exists($attachment->file_path)) {
            return [
                'can_include' => false,
                'reason' => 'Archivo no encontrado en storage'
            ];
        }
        
        // Verificar tamaño
        $fileSize = \Storage::size($attachment->file_path);
        if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
            return [
                'can_include' => false,
                'reason' => 'Archivo excede 5MB',
                'file_size' => $fileSize
            ];
        }
        
        return [
            'can_include' => true,
            'file_size' => $fileSize
        ];
    }
}
