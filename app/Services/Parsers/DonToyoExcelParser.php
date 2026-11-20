<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\Models\{BillOfLading, Client, Company, Container, ContainerType, Country, DocumentType, ManifestImport, Shipment, ShipmentItem, Vessel, Voyage};
use App\Services\Parsers\Concerns\{EnsuresUniqueVoyageNumber, ExtractsEmbeddedTaxId, ResolvesExistingClient, ResolvesPorts, ResolvesVoyageCargoType};
use App\ValueObjects\ManifestParseResult;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Log};
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class DonToyoExcelParser implements ManifestParserInterface
{
    use EnsuresUniqueVoyageNumber, ExtractsEmbeddedTaxId, ResolvesExistingClient, ResolvesPorts, ResolvesVoyageCargoType;

    private const HEADERS = [
        'BUQUE' => ['Armador', 'CODIGO IMO BUQUE:', 'VIAJE:', 'IMPO/EXPO'],
        'BLS' => ['BL', 'Origen', 'Carga', 'Descarga', 'Destino Final', 'CUIT SIM', 'NCM', 'NCM o PE', 'Consolidado', 'Shipper', 'Cnee', 'Cuit Facturacion', 'NombreFacturacion', 'Volumen', 'Peso (KG)', 'Cantidad', 'DESCRIPCION MERCADERIA'],
        'CONTENEDORES' => ['BL', 'CONTENEDOR', 'TIPO (ISO)', 'Seal', 'Peso', 'Volumen', 'Tara', 'VGM', 'Temperatura'],
    ];

    public function canParse(string $filePath): bool
    {
        try {
            $this->readWorkbook($filePath);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Only the three manifest sheets are loaded; surcharge data is never read. */
    protected function readWorkbook(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        if (!$reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xls && !$reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx) {
            throw new DomainException('Se requiere un archivo Excel XLS o XLSX.');
        }
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(array_keys(self::HEADERS));
        $book = $reader->load($filePath);
        try {
            $data = [];
            foreach (self::HEADERS as $name => $headers) {
                $sheet = $book->getSheetByName($name);
                if (!$sheet) {
                    throw new DomainException("Falta la hoja {$name}.");
                }
                $width = count($headers);
                $rows = $sheet->rangeToArray('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($width) . $sheet->getHighestDataRow(), null, false, false, false);
                $actual = array_map(fn ($v) => mb_strtoupper($this->text($v)), array_shift($rows));
                $expected = array_map(fn ($v) => mb_strtoupper($v), $headers);
                if ($actual !== $expected) {
                    throw new DomainException("Encabezados no reconocidos en {$name}.");
                }
                $data[$name] = [];
                foreach ($rows as $index => $row) {
                    if (array_filter($row, fn ($v) => $this->text($v) !== '') === []) {
                        continue;
                    }
                    $record = array_combine($headers, array_map(fn ($v) => $this->text($v), $row));
                    $record['_row'] = $index + 2;
                    $data[$name][] = $record;
                }
            }
            return $data;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function text($value): string
    {
        $text = (string) ($value ?? '');
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        return trim(str_replace(["\xc2\xa0", "\r\n", "\r"], [' ', "\n", "\n"], $text));
    }

    protected function normalizeDecimal($value, bool $optional = false): ?float
    {
        $value = str_replace(' ', '', $this->text($value));
        if ($value === '' && $optional) {
            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $decimal = strrpos($value, ',') > strrpos($value, '.') ? ',' : '.';
            $value = str_replace($decimal === ',' ? '.' : ',', '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }
        if ($value === '' || !is_numeric($value) || !is_finite((float) $value)) {
            throw new DomainException('Número decimal inválido: ' . $value);
        }
        return (float) $value;
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (count($data['BUQUE'] ?? []) !== 1) {
            $errors[] = 'BUQUE debe contener exactamente una fila de datos.';
        }
        $meta = $data['BUQUE'][0] ?? [];
        if (!in_array($meta['IMPO/EXPO'] ?? '', ['I', 'E'], true)) {
            $errors[] = 'BUQUE: IMPO/EXPO debe ser I o E.';
        }
        if (($meta['CODIGO IMO BUQUE:'] ?? '') !== '' && (!ctype_digit($meta['CODIGO IMO BUQUE:']) || strlen($meta['CODIGO IMO BUQUE:']) !== 7)) {
            $errors[] = 'BUQUE: el IMO debe tener siete dígitos.';
        }
        if (empty($data['BLS']) || empty($data['CONTENEDORES'])) {
            $errors[] = 'BLS y CONTENEDORES deben contener datos.';
        }
        $bills = [];
        foreach ($data['BLS'] ?? [] as $row) {
            $label = 'BLS fila ' . ($row['_row'] ?? '?');
            foreach (['BL', 'Origen', 'Carga', 'Descarga', 'Destino Final', 'Shipper', 'Cnee', 'DESCRIPCION MERCADERIA'] as $field) {
                if (($row[$field] ?? '') === '') {
                    $errors[] = "{$label}: falta {$field}.";
                }
            }
            $number = $row['BL'] ?? '';
            if (isset($bills[$number]) || mb_strlen($number) > 100) {
                $errors[] = "{$label}: BL repetido o demasiado largo ({$number}).";
            }
            $bills[$number] = true;
            if (mb_strlen($row['NCM'] ?? '') > 20) {
                $errors[] = "{$label}: NCM supera 20 caracteres.";
            }
            foreach (['Shipper', 'Cnee'] as $field) {
                if (mb_strlen($row[$field] ?? '') > 255) {
                    $errors[] = "{$label}: {$field} supera 255 caracteres.";
                }
            }
            foreach (['Volumen', 'Peso (KG)', 'Cantidad'] as $field) {
                $this->validateNumber($row[$field] ?? '', $label, $field, $errors, false, $field === 'Cantidad');
            }
            foreach (['CUIT SIM', 'Cuit Facturacion'] as $field) {
                $tax = $row[$field] ?? '';
                if ($tax !== '' && (!ctype_digit(str_replace(['-', '.', ' '], '', $tax)) || $this->resolveTaxId($tax) === null)) {
                    $errors[] = "{$label}: {$field} inválido.";
                }
            }
            if (!in_array($row['Consolidado'] ?? '', ['S', 'N'], true)) {
                $errors[] = "{$label}: Consolidado debe ser S o N.";
            }
        }
        $numbers = [];
        $linked = [];
        foreach ($data['CONTENEDORES'] ?? [] as $row) {
            $label = 'CONTENEDORES fila ' . ($row['_row'] ?? '?');
            $number = $row['CONTENEDOR'] ?? '';
            if (strlen($number) !== 11 || !ctype_upper(substr($number, 0, 4)) || !ctype_alpha(substr($number, 0, 4)) || !ctype_digit(substr($number, 4))) {
                $errors[] = "{$label}: número de contenedor inválido ({$number}).";
            }
            if (isset($numbers[$number])) {
                $errors[] = "{$label}: contenedor repetido ({$number}).";
            }
            $numbers[$number] = true;
            $bl = $row['BL'] ?? '';
            if (!isset($bills[$bl])) {
                $errors[] = "{$label}: BL inexistente en BLS ({$bl}).";
            }
            $linked[$bl] = true;
            if (($row['TIPO (ISO)'] ?? '') === '') {
                $errors[] = "{$label}: falta TIPO (ISO).";
            }
            foreach (['Peso', 'Volumen', 'Tara', 'VGM', 'Temperatura'] as $field) {
                $this->validateNumber($row[$field] ?? '', $label, $field, $errors, in_array($field, ['Tara', 'VGM', 'Temperatura'], true));
            }
        }
        foreach (array_keys($bills) as $number) {
            if (!isset($linked[$number])) {
                $errors[] = "BL {$number}: no tiene contenedores asociados.";
            }
        }
        return $errors;
    }

    private function validateNumber($value, string $label, string $field, array &$errors, bool $optional = false, bool $integer = false): void
    {
        try {
            $n = $this->normalizeDecimal($value, $optional);
            if ($n === null) {
                return;
            }
            $max = $integer ? 2147483647 : ($field === 'Volumen' ? 9999999.999 : (in_array($field, ['Tara'], true) ? 999999.99 : 9999999999.99));
            if (($field !== 'Temperatura' && $n < 0) || abs($n) > $max || ($integer && floor($n) !== $n) || ($field === 'Temperatura' && abs($n) > 999.99)) {
                throw new DomainException('Valor fuera de rango');
            }
        } catch (DomainException $e) {
            $errors[] = "{$label}: {$field} inválido.";
        }
    }

    public function transform(array $data): array
    {
        $result = ['vessel' => $data['BUQUE'][0] ?? [], 'bills' => [], 'containers' => []];
        foreach ($data['BLS'] ?? [] as $row) {
            $row['quantity'] = (int) $this->normalizeDecimal($row['Cantidad']);
            $row['weight'] = $this->normalizeDecimal($row['Peso (KG)']);
            $row['volume'] = $this->normalizeDecimal($row['Volumen']);
            $result['bills'][$row['BL']] = $row;
        }
        foreach ($data['CONTENEDORES'] ?? [] as $row) {
            foreach (['Peso', 'Volumen', 'Tara', 'VGM', 'Temperatura'] as $field) {
                $row[$field] = $this->normalizeDecimal($row[$field], in_array($field, ['Tara', 'VGM', 'Temperatura'], true));
            }
            $result['containers'][$row['BL']][] = $row;
        }
        return $result;
    }

    protected function dateOption($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return is_numeric($value) ? Carbon::instance(Date::excelToDateTimeObject((float) $value)) : Carbon::parse($value);
        } catch (Throwable $e) {
            throw new DomainException('La fecha de salida informada no es válida.');
        }
    }

    /** Validate catalogs and ports without modifying existing records. */
    protected function preflight(array $data, array $options): array
    {
        $user = auth()->user();
        $companyId = $user?->company?->id;
        if (!$user || !$companyId) {
            throw new DomainException('Se requiere un usuario con empresa asignada.');
        }
        $vessel = Vessel::whereKey($options['vessel_id'] ?? null)->where('company_id', $companyId)->where('active', true)->first();
        if (!$vessel) {
            throw new DomainException('Seleccione una embarcación activa de su empresa; el archivo no informa su nombre.');
        }
        $voyageNumber = $this->text($options['voyage_number'] ?? '') ?: $this->text($data['vessel']['VIAJE:'] ?? '');
        if ($voyageNumber === '' || mb_strlen($voyageNumber) > 50) {
            throw new DomainException('Informe un número de viaje válido de hasta 50 caracteres.');
        }
        $types = [];
        $missing = [];
        foreach ($data['containers'] as $rows) {
            foreach ($rows as $row) {
                $code = $row['TIPO (ISO)'];
                if (isset($types[$code])) {
                    continue;
                }
                $matches = ContainerType::where('iso_code', $code)->where('active', true)->get();
                if ($matches->count() !== 1) {
                    $missing[$code] = $code;
                } else {
                    $types[$code] = $matches->first();
                }
            }
        }
        if ($missing) {
            throw new DomainException('Catálogo de tipos de contenedor pendiente: ' . implode(', ', $missing) . '. Solicite al administrador tipos activos con esos códigos ISO exactos y dimensiones correctas. No se crean tipos ni se sustituyen por otros ISO.');
        }
        $ports = [];
        foreach ($data['bills'] as $row) {
            foreach (['Origen', 'Carga', 'Descarga', 'Destino Final'] as $field) {
                $code = $row[$field];
                if (!isset($ports[$code])) {
                    try {
                        $ports[$code] = $this->resolvePortStrict($code);
                    } catch (\Illuminate\Database\QueryException $e) {
                        throw $e;
                    } catch (\Exception $e) {
                        throw new DomainException($e->getMessage(), 0, $e);
                    }
                }
            }
        }
        // Same canonical classification used for containerized Excel manifests.
        $cargo = DB::table('cargo_types')->where('name', 'CONTENEDORES')->where('active', true)->first();
        $packaging = DB::table('packaging_types')->where('name', 'CONTENEDOR')->where('active', true)->first();
        if (!$cargo || !$packaging) {
            throw new DomainException('Falta clasificación activa CONTENEDORES / CONTENEDOR en el catálogo.');
        }
        $existingContainers = Container::whereIn('container_number', array_merge(...array_map(fn ($rows) => array_column($rows, 'CONTENEDOR'), array_values($data['containers']))))->get()->keyBy('container_number');
        foreach ($data['containers'] as $rows) {
            foreach ($rows as $row) {
                $existing = $existingContainers->get($row['CONTENEDOR']);
                if ($existing && (int) $existing->container_type_id !== (int) $types[$row['TIPO (ISO)']]->id) {
                    throw new DomainException("El contenedor {$row['CONTENEDOR']} ya existe con otro tipo; revise el catálogo.");
                }
            }
        }
        $first = reset($data['bills']);
        $origin = $ports[$first['Carga']];
        $destination = $ports[$first['Descarga']];
        $cargoType = $this->resolveVoyageCargoTypeForCompany((int) $companyId, $origin, $destination);
        return compact('companyId', 'vessel', 'voyageNumber', 'types', 'ports', 'cargo', 'packaging', 'existingContainers', 'origin', 'destination', 'cargoType') + ['departure' => $this->dateOption($options['departure_date'] ?? null)];
    }

    private function clientPlan(string $name, int $countryHint, array &$warnings): array
    {
        $tax = $this->resolveTaxId(null, $name);
        $countryId = $countryHint;
        $documentId = null;
        if ($tax !== null) {
            if (!in_array(strlen($tax), [7, 8, 9, 11], true)) {
                throw new DomainException("Cliente {$name}: identificación fiscal sin país y tipo documental admitidos.");
            }
            $countryCode = strlen($tax) === 11 ? 'AR' : 'PY';
            $countryId = Country::where('alpha2_code', $countryCode)->value('id');
            $documentId = DocumentType::where('country_id', $countryId)->where('code', $countryCode === 'AR' ? 'CUIT' : 'RUC')->where('active', true)->value('id');
            if (!$countryId || !$documentId) {
                throw new DomainException("Cliente {$name}: falta país o tipo documental activo en el catálogo.");
            }
        }
        $resolved = $this->resolveExistingClient($tax, $name, (int) $countryId, $documentId);
        $warnings = array_values(array_unique(array_merge($warnings, $resolved['warnings'])));
        if ($resolved['client'] !== null) {
            return ['existing' => $resolved['client']];
        }
        if ($tax === null) {
            throw new DomainException("Cliente {$name}: falta ficha existente o identificación fiscal que permita determinar país y tipo documental. CUIT SIM no se asigna automáticamente al Shipper ni al Cnee.");
        }
        return ['create' => ['legal_name' => $name, 'commercial_name' => $name, 'tax_id' => $tax, 'country_id' => $countryId, 'document_type_id' => $documentId, 'status' => 'active']];
    }

    private function sourceNcm(string $number, array $row, array &$warnings): ?string
    {
        $declared = $this->text($row['NCM'] ?? '');
        // Only explicitly labelled codes: unlabelled numbers may be weights, quantities or references.
        preg_match_all('/\b(?:NCM|H\.?S\.?\s*CODE)\s*[:#-]?\s*(\d{4}(?:\.\d{2}){1,2})\b/iu',
            $row['DESCRIPCION MERCADERIA'], $matches);
        $found = $matches[1] ?? [];
        if (count($found) > 1) {
            $warnings[] = "BL {$number}: la descripción contiene varios códigos NCM/HS; el NCM queda vacío.";
            return null;
        }
        if ($declared !== '' && (!preg_match('/^\d{4}\.\d{2,3}$/', $declared) || str_starts_with($declared, '0000.'))) {
            $warnings[] = "BL {$number}: NCM de la columna G no identificable ({$declared}); queda vacío.";
            $declared = '';
        }
        if ($declared !== '' && $found && $found[0] !== $declared) {
            $warnings[] = "BL {$number}: NCM de la columna G y descripción diferentes; el NCM queda vacío.";
            return null;
        }
        return $declared !== '' ? $declared : ($found[0] ?? null);
    }

    private function warnUnsupportedPackaging(string $number, string $description, array &$warnings): void
    {
        preg_match_all('/\b(?:CARTONS?|FARDOS?)\b/iu', $description, $matches);
        foreach (array_unique(array_map('mb_strtoupper', $matches[0])) as $word) {
            $warnings[] = "BL {$number}: embalaje {$word} sin equivalencia en el catálogo; se conserva CONTENEDOR para MIC/DTA.";
        }
    }

    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $import = null;
        $started = microtime(true);
        $warnings = ['El archivo no informa fechas documentales ni direcciones. Pendiente completar antes de presentar a Aduana.'];
        try {
            $raw = $this->readWorkbook($filePath);
            $errors = $this->validate($raw);
            if ($errors) {
                return ManifestParseResult::failure($errors);
            }
            $data = $this->transform($raw);
            $plan = $this->preflight($data, $options);
            foreach ($data['bills'] as $number => &$row) {
                $this->warnUnsupportedPackaging($number, $row['DESCRIPCION MERCADERIA'], $warnings);
                $row['resolved_ncm'] = $this->sourceNcm($number, $row, $warnings);
            }
            unset($row);
            if (count(array_unique(array_column($data['bills'], 'Descarga'))) > 1) {
                $warnings[] = 'Los BL tienen varios puertos de descarga. El viaje utiliza el del primer BL; cada conocimiento conserva su propio puerto.';
            }
            if ($data['vessel']['CODIGO IMO BUQUE:'] !== $this->text($plan['vessel']->imo_number)) {
                $warnings[] = 'El IMO declarado no coincide con el de la ficha seleccionada o falta en ella. Se utiliza la embarcación confirmada sin modificar su catálogo.';
            }
            foreach ($data['bills'] as $number => $row) {
                $rows = $data['containers'][$number];
                if (abs(array_sum(array_column($rows, 'Peso')) - $row['weight']) > 0.02) {
                    $warnings[] = "BL {$number}: el peso de BLS difiere de la suma de Peso en CONTENEDORES; se conservan ambos sin repartir ni sustituir por VGM.";
                }
                if (abs(array_sum(array_column($rows, 'Volumen')) - $row['volume']) > 0.001) {
                    $warnings[] = "BL {$number}: el volumen de BLS difiere de CONTENEDORES; se conservan ambos.";
                }
            }
            $hash = ManifestImport::generateFileHash($filePath);
            if (ManifestImport::isFileAlreadyImported($hash, (int) $plan['companyId'])) {
                return ManifestParseResult::failure(['El archivo ya fue importado. Revise el historial de importaciones.']);
            }
            $import = ManifestImport::createForImport([
                'company_id' => $plan['companyId'], 'user_id' => auth()->id(),
                'file_name' => basename($filePath), 'file_format' => 'don_toyo_excel',
                'file_size_bytes' => filesize($filePath), 'file_hash' => $hash,
                'parser_config' => ['parser_class' => self::class, 'options' => $options],
            ]);
            return DB::transaction(function () use ($data, $plan, $import, $warnings, $started) {
                return Model::withoutEvents(function () use ($data, $plan, $import, $warnings, $started) {
                    $this->guardVoyageNumberIsFree($plan['voyageNumber'], (int) $plan['vessel']->id);
                    $created = array_fill_keys(['voyage', 'shipment', 'bill', 'item', 'container', 'client', 'port'], []);
                    $clients = [];
                    $this->clientIdentityIndex = null;
                    foreach ($data['bills'] as $row) {
                        foreach (['Shipper', 'Cnee'] as $role) {
                            $name = $row[$role];
                            if (isset($clients[$name])) {
                                continue;
                            }
                            // Origin is only a hint for existing records, never a country for creation.
                            $clientPlan = $this->clientPlan($name, (int) $plan['ports'][$row['Origen']]->country_id, $warnings);
                            $client = $clientPlan['existing'] ?? null;
                            if (!$client) {
                                $client = Client::create($clientPlan['create'] + ['created_by_company_id' => $plan['companyId'], 'verified_at' => now()]);
                                $this->rememberClientIdentity($client);
                                $created['client'][] = $client->id;
                            }
                            $clients[$name] = $client;
                        }
                    }
                    $voyage = Voyage::create([
                        'company_id' => $plan['companyId'], 'lead_vessel_id' => $plan['vessel']->id,
                        'voyage_number' => $plan['voyageNumber'], 'origin_port_id' => $plan['origin']->id,
                        'destination_port_id' => $plan['destination']->id, 'origin_country_id' => $plan['origin']->country_id,
                        'destination_country_id' => $plan['destination']->country_id, 'departure_date' => $plan['departure'],
                        'estimated_arrival_date' => null, 'voyage_type' => 'single_vessel', 'cargo_type' => $plan['cargoType'],
                        'status' => 'planning', 'vessel_count' => 1, 'created_by_user_id' => auth()->id(),
                        'operational_notes' => 'Importado desde resumen Don Toyo. Armador: ' . $data['vessel']['Armador'] . '; IMO declarado: ' . $data['vessel']['CODIGO IMO BUQUE:'] . '; IMPO/EXPO fuente: ' . $data['vessel']['IMPO/EXPO'],
                    ]);
                    $created['voyage'][] = $voyage->id;
                    $shipment = Shipment::create([
                        'voyage_id' => $voyage->id, 'vessel_id' => $plan['vessel']->id,
                        'shipment_number' => $plan['voyageNumber'] . '-01', 'sequence_in_voyage' => 1,
                        'status' => 'planning', 'is_lead_vessel' => true, 'vessel_role' => 'single',
                        'cargo_capacity_tons' => null, 'departure_time' => null, 'created_by_user_id' => auth()->id(),
                    ]);
                    $created['shipment'][] = $shipment->id;
                    $bills = $containers = [];
                    foreach ($data['bills'] as $number => $row) {
                        $rows = $data['containers'][$number];
                        $bill = BillOfLading::create([
                            'shipment_id' => $shipment->id, 'bill_number' => $number,
                            'shipper_id' => $clients[$row['Shipper']]->id, 'consignee_id' => $clients[$row['Cnee']]->id,
                            'notify_party_id' => $clients[$row['Cnee']]->id,
                            'loading_port_id' => $plan['ports'][$row['Carga']]->id, 'discharge_port_id' => $plan['ports'][$row['Descarga']]->id,
                            'final_destination_port_id' => $plan['ports'][$row['Destino Final']]->id,
                            'primary_cargo_type_id' => $plan['cargo']->id, 'primary_packaging_type_id' => $plan['packaging']->id,
                            'bill_date' => null, 'loading_date' => null, 'freight_terms' => null,
                            'total_packages' => $row['quantity'], 'gross_weight_kg' => $row['weight'], 'net_weight_kg' => null,
                            'volume_m3' => $row['volume'], 'container_count' => count($rows), 'cargo_description' => $row['DESCRIPCION MERCADERIA'],
                            'is_consolidated' => $row['Consolidado'] === 'S', 'status' => 'draft',
                            'webservice_data' => ['don_toyo_source' => array_diff_key($row, array_flip(['quantity', 'weight', 'volume', '_row', 'resolved_ncm']))],
                            'created_by_user_id' => auth()->id(),
                        ]);
                        $bills[] = $bill;
                        $created['bill'][] = $bill->id;
                        // One aggregate cargo item per source BL; package distribution is not supplied.
                        $item = ShipmentItem::create([
                            'bill_of_lading_id' => $bill->id, 'line_number' => 1,
                            'cargo_type_id' => $plan['cargo']->id, 'packaging_type_id' => $plan['packaging']->id,
                            'package_quantity' => $row['quantity'], 'gross_weight_kg' => $row['weight'], 'net_weight_kg' => null,
                            'volume_m3' => $row['volume'], 'item_description' => $row['DESCRIPCION MERCADERIA'],
                            'commodity_code' => $row['resolved_ncm'], 'container_condition' => Container::CONDITION_HOUSE_TO_HOUSE,
                            'currency_code' => null, 'status' => 'draft',
                            'created_by_user_id' => auth()->id(),
                        ]);
                        $created['item'][] = $item->id;
                        foreach ($rows as $containerRow) {
                            $container = $plan['existingContainers']->get($containerRow['CONTENEDOR']);
                            if (!$container) {
                                $type = $plan['types'][$containerRow['TIPO (ISO)']];
                                $empty = str_starts_with(mb_strtoupper($row['DESCRIPCION MERCADERIA']), 'VACIO');
                                $container = Container::create([
                                    'container_number' => $containerRow['CONTENEDOR'], 'container_type_id' => $type->id,
                                    'tare_weight_kg' => $containerRow['Tara'], 'max_gross_weight_kg' => $type->max_gross_weight_kg,
                                    'shipper_seal' => $containerRow['Seal'] ?: null,
                                    'condition' => $empty ? 'V' : 'L', 'container_condition' => Container::CONDITION_HOUSE_TO_HOUSE,
                                    'operational_status' => $empty ? 'empty' : 'loaded',
                                    'temperature_controlled' => $containerRow['Temperatura'] !== null,
                                    'set_temperature' => $containerRow['Temperatura'], 'created_by_user_id' => auth()->id(),
                                ]);
                                $created['container'][] = $container->id;
                            }
                            $containers[] = $container;
                            DB::table('container_shipment_item')->insert([
                                'container_id' => $container->id, 'shipment_item_id' => $item->id,
                                'package_quantity' => null, 'gross_weight_kg' => $containerRow['Peso'], 'net_weight_kg' => null,
                                'volume_m3' => $containerRow['Volumen'], 'verified_gross_mass_kg' => $containerRow['VGM'],
                                'required_temperature' => $containerRow['Temperatura'],
                                'container_condition' => in_array($container->container_condition, ['H', 'P'], true)
                                    ? $container->container_condition : Container::CONDITION_HOUSE_TO_HOUSE,
                                'status' => 'planned',
                                'webservice_data' => json_encode(['don_toyo_source' => $containerRow], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                                'created_by_user_id' => auth()->id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                    $documentaryWeight = number_format(array_sum(array_column($data['bills'], 'weight')), 2, '.', '');
                    $shipment->update(['cargo_weight_loaded' => $documentaryWeight]);
                    $voyage->update([
                        'total_cargo_weight' => $documentaryWeight,
                        'total_cargo_weight_loaded' => $documentaryWeight,
                    ]);
                    $import->recordExplicitlyCreatedObjects($created);
                    $statistics = ['bills_created' => count($bills), 'items_created' => count($created['item']), 'containers_processed' => count($containers), 'containers_created' => count($created['container']), 'import_id' => $import->id];
                    $import->markAsCompletedWithWarnings(['voyage_id' => $voyage->id, 'warnings' => $warnings, 'warnings_count' => count($warnings), 'import_statistics' => $statistics, 'processing_time_seconds' => round(microtime(true) - $started, 2)]);
                    return ManifestParseResult::success($voyage, [$shipment], $containers, $bills, $warnings, $statistics);
                });
            });
        } catch (Throwable $e) {
            $duplicate = str_contains($e->getMessage(), 'voyages_voyage_number_unique');
            $message = $duplicate ? 'El número de viaje ya existe para esta embarcación en su empresa. No se duplicó ningún dato.' : ($e instanceof DomainException ? $e->getMessage() : 'No se pudo importar el manifiesto. No se guardaron cargas; revise el archivo y los catálogos.');
            if ($import) {
                try {
                    $import->markAsFailed([$message]);
                } catch (Throwable $trackingError) {
                    Log::error('No se pudo registrar el fallo de importación Don Toyo', ['exception' => $trackingError]);
                }
            }
            Log::error('Importación Don Toyo rechazada', ['exception' => $e]);
            return ManifestParseResult::failure([$message], $warnings);
        }
    }

    public function getFormatInfo(): array
    {
        return ['name' => 'Don Toyo Excel', 'description' => 'Resumen de manifiesto con hojas BUQUE, BLS y CONTENEDORES', 'extensions' => ['xls', 'xlsx']];
    }

    public function getDefaultConfig(): array
    {
        return [];
    }
}
