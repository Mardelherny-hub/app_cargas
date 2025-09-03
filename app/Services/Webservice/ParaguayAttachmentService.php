<?php

namespace App\Services\Webservice;

use App\Models\Company;
use App\Models\Voyage;
use App\Models\WebserviceTransaction;
use App\Services\Webservice\SoapClientService;
use App\Services\Webservice\ParaguayCustomsService;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class ParaguayAttachmentService
{
    private Company $company;
    private ParaguayCustomsService $paraguayService;
    private array $config;

    private const ALLOWED_TYPES = ['pdf'];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const REQUIRED_DOCS = [
        'conocimiento' => 'Conocimiento de Embarque',
        'factura' => 'Factura Comercial',
        'packing_list' => 'Lista de Empaque',
        'certificado_origen' => 'Certificado de Origen'
    ];

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->paraguayService = new ParaguayCustomsService($company);
        $this->config = [
            'storage_path' => 'webservices/paraguay/attachments',
            'max_file_size' => self::MAX_FILE_SIZE,
            'allowed_types' => self::ALLOWED_TYPES,
        ];
    }

    /**
     * Subir documentos para un viaje específico
     */
    public function uploadDocuments(Voyage $voyage, array $files, int $userId): array
    {
        try {
            // Validar que el manifiesto fue enviado
            $manifestSent = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'manifiesto')
                ->where('status', 'success')
                ->exists();

            if (!$manifestSent) {
                return [
                    'success' => false,
                    'error' => 'Debe enviar el manifiesto antes de adjuntar documentos'
                ];
            }

            $results = [];
            $transactionId = $this->generateTransactionId();

            foreach ($files as $docType => $file) {
                $result = $this->uploadSingleDocument($voyage, $docType, $file, $transactionId);
                $results[$docType] = $result;
            }

            // Enviar adjuntos a Paraguay si todos fueron subidos exitosamente
            $allUploaded = collect($results)->every(fn($r) => $r['success']);
            
            if ($allUploaded) {
                $soapResult = $this->sendAttachmentsToParaguay($voyage, $results, $userId);
                return array_merge($soapResult, ['uploads' => $results]);
            }

            return [
                'success' => false,
                'error' => 'Algunos archivos no se pudieron subir',
                'uploads' => $results
            ];

        } catch (Exception $e) {
            Log::error('Error subiendo documentos Paraguay', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Subir un documento individual
     */
    private function uploadSingleDocument(Voyage $voyage, string $docType, UploadedFile $file, string $transactionId): array
    {
        try {
            // Validar archivo
            $validation = $this->validateFile($file, $docType);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }

            // Generar nombre de archivo
            $fileName = $this->generateFileName($voyage, $docType, $file);
            $filePath = "{$this->config['storage_path']}/{$voyage->id}/{$fileName}";

            // Guardar archivo
            $path = Storage::put($filePath, file_get_contents($file->getRealPath()));

            return [
                'success' => true,
                'file_path' => $filePath,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $docType
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error guardando archivo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validar archivo subido
     */
    private function validateFile(UploadedFile $file, string $docType): array
    {
        // Validar tamaño
        if ($file->getSize() > $this->config['max_file_size']) {
            return [
                'valid' => false,
                'error' => 'Archivo excede el tamaño máximo permitido (10MB)'
            ];
        }

        // Validar extensión
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->config['allowed_types'])) {
            return [
                'valid' => false,
                'error' => 'Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', $this->config['allowed_types'])
            ];
        }

        // Validar que el tipo de documento es válido
        if (!isset(self::REQUIRED_DOCS[$docType])) {
            return [
                'valid' => false,
                'error' => 'Tipo de documento no válido: ' . $docType
            ];
        }

        return ['valid' => true];
    }

    /**
     * Generar nombre de archivo único
     */
    private function generateFileName(Voyage $voyage, string $docType, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        
        return "{$voyage->voyage_number}_{$docType}_{$timestamp}.{$extension}";
    }

    /**
     * Enviar adjuntos a Paraguay via SOAP
     */
    private function sendAttachmentsToParaguay(Voyage $voyage, array $uploadedFiles, int $userId): array
    {
        try {
            // Obtener referencia Paraguay del manifiesto
            $paraguayReference = WebserviceTransaction::where('voyage_id', $voyage->id)
                ->where('webservice_type', 'manifiesto')
                ->where('status', 'success')
                ->value('external_reference');

            if (!$paraguayReference) {
                return [
                    'success' => false,
                    'error' => 'No se encontró referencia de Paraguay del manifiesto'
                ];
            }

            // Crear transacción
            $transaction = WebserviceTransaction::create([
                'company_id' => $this->company->id,
                'user_id' => $userId,
                'voyage_id' => $voyage->id,
                'webservice_type' => 'adjuntos',
                'country' => 'PY',
                'transaction_id' => $this->generateTransactionId(),
                'status' => 'sending',
                'request_data' => [
                    'paraguay_reference' => $paraguayReference,
                    'files' => $uploadedFiles
                ]
            ]);

            // TODO: Implementar envío SOAP real
            // Por ahora simular éxito
            $transaction->update([
                'status' => 'success',
                'external_reference' => 'PY_ATT_' . now()->format('YmdHis'),
                'response_data' => [
                    'status' => 'RECEIVED',
                    'message' => 'Documentos recibidos correctamente'
                ]
            ]);

            return [
                'success' => true,
                'transaction_id' => $transaction->transaction_id,
                'paraguay_reference' => $paraguayReference,
                'message' => 'Documentos enviados exitosamente a Paraguay'
            ];

        } catch (Exception $e) {
            Log::error('Error enviando adjuntos a Paraguay', [
                'voyage_id' => $voyage->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error enviando a Paraguay: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generar ID de transacción único
     */
    private function generateTransactionId(): string
    {
        return 'PY_ATT_' . now()->format('YmdHis') . '_' . substr(uniqid(), -4);
    }
}