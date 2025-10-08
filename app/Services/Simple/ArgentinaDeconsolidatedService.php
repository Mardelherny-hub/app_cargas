<?php

namespace App\Services\Simple;

use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Models\WebserviceResponse;
use App\Models\WebserviceLog;
use App\Models\VoyageWebserviceStatus;
use App\Models\WebserviceError;
use Illuminate\Support\Facades\Log;
use Exception;

class ArgentinaDeconsolidatedService extends BaseWebserviceService
{
    /**
     * Alias interno del servicio
     */
    protected function getWebserviceType(): string
    {
        // Mantener consistente con tu taxonomía interna (WebserviceError::WEBSERVICE_TYPES)
        // Ahí aparece 'desconsolidado' (es-ES). Si tu base usa ese string, podés cambiarlo.
        return 'deconsolidated';
    }

    /**
     * País (ruteo de credenciales)
     */
    protected function getCountry(): string
    {
        return 'AR';
    }

    /**
     * Config del WS (WSDL, operación, etc.)
     */
    protected function getWebserviceConfig(): array
    {
        return [
            'wsdl'      => config('webservices.aduana.argentina.wsdl.deconsolidated'),
            'operation' => 'RegistrarDesconsolidado',
            'namespace' => 'http://servicios1.afip.gob.ar/ws/wgesregsintia2',
        ];
    }

    protected function getWsdlUrl(): string
    {
        return $this->getWebserviceConfig()['wsdl'];
    }

    protected function getSoapOperation(): string
    {
        return $this->getWebserviceConfig()['operation'];
    }

    /**
     * Validaciones específicas antes del envío
     */
    protected function validateSpecificData(Voyage $voyage): array
    {
        $errors = [];

        // Debe haber BLs en el viaje (via hasManyThrough → bills_of_lading)
        if (!$voyage->relationLoaded('billsOfLading')) {
            $voyage->load('billsOfLading');
        }
        if (!$voyage->billsOfLading || $voyage->billsOfLading->isEmpty()) {
            $errors[] = 'El viaje no tiene conocimientos (Bills of Lading) asociados.';
        }

        // Master BL
        $master = $voyage->billsOfLading()
            ->where('is_master_bill', true)
            ->first();

        if (!$master) {
            $errors[] = 'No se encontró un Conocimiento Madre (is_master_bill = true).';
            return $errors;
        }

        // House BLs asociados al master
        $houseCount = $voyage->billsOfLading()
            ->where('is_house_bill', true)
            ->where('master_bill_number', $master->bill_number)
            ->count();

        if ($houseCount === 0) {
            $errors[] = 'No se encontraron Conocimientos Hijo (house) asociados al master BL.';
        }

        return $errors;
    }

    /**
     * Punto de entrada que invoca el controlador
     */
    public function send(Voyage $voyage): array
    {
        try {
            $errors = $this->validateSpecificData($voyage);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            // Delegar en el método requerido por la clase base
            return $this->sendSpecificWebservice($voyage, []);

        } catch (Exception $e) {
            Log::error('Error en envío de desconsolidado: '.$e->getMessage());
            WebserviceError::create([
                'service' => $this->getWebserviceType(),
                'country' => $this->getCountry(),
                'message' => $e->getMessage(),
                'category' => 'technical',
                'severity' => 'high',
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Método abstracto de la base — implementación exacta
     * Firma: sendSpecificWebservice(Voyage $voyage, array $options = []): array
     */
    protected function sendSpecificWebservice(Voyage $voyage, array $options = []): array
    {
        try {
            // 1) Generar XML exacto AFIP desde BBDD
            $xmlGenerator = new SimpleXmlGenerator();
            $xmlContent = $xmlGenerator->generateDeconsolidatedXml($voyage);

            // 2) Crear transacción persistida
            $transaction = WebserviceTransaction::create([
                'company_id' => $voyage->company_id,
                'voyage_id'  => $voyage->id,
                'service'    => $this->getWebserviceType(),
                'country'    => $this->getCountry(),
                'xml_sent'   => $xmlContent,
                'status'     => 'pending',
            ]);

            // 3) Cliente SOAP
            $client  = $this->getSoapClient();
            $params  = ['xml' => $xmlContent];
            $soapRes = $client->__soapCall($this->getSoapOperation(), [$params]);

            // 4) Persistir respuesta
            WebserviceResponse::create([
                'transaction_id' => $transaction->id,
                'response_raw'   => json_encode($soapRes, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),
                'status'         => 'completed',
            ]);

            WebserviceLog::create([
                'transaction_id' => $transaction->id,
                'level'          => 'info',
                'message'        => 'Desconsolidado enviado a AFIP correctamente.',
                'context'        => ['operation' => $this->getSoapOperation()],
            ]);

            // 5) Actualizar transacción + estado del viaje
            $transaction->update([
                'status'       => 'completed',
                'xml_response' => json_encode($soapRes, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),
            ]);

            VoyageWebserviceStatus::updateOrCreate(
                ['voyage_id' => $voyage->id, 'service' => $this->getWebserviceType()],
                [
                    'status'              => 'completed',
                    'last_transaction_id' => $transaction->id,
                ]
            );

            return [
                'success'        => true,
                'transaction_id' => $transaction->id,
                'response'       => $soapRes,
            ];

        } catch (Exception $e) {
            Log::error('Error en envío de desconsolidado: '.$e->getMessage());

            WebserviceError::create([
                'service'  => $this->getWebserviceType(),
                'country'  => $this->getCountry(),
                'message'  => $e->getMessage(),
                'category' => 'technical',
                'severity' => 'high',
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
