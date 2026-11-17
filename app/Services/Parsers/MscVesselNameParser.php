<?php

namespace App\Services\Parsers;

use App\Contracts\ManifestParserInterface;
use App\Models\{BillOfLading, CargoType, Client, Company, Container, ContainerType, Country, DocumentType, ManifestImport, Port, Shipment, ShipmentItem, Vessel, Voyage};
use App\Services\Parsers\Concerns\{ExtractsEmbeddedTaxId, ResolvesClientAddresses, ResolvesExistingClient, ResolvesPorts, ResolvesVoyageCargoType};
use App\ValueObjects\ManifestParseResult;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class MscVesselNameParser implements ManifestParserInterface
{
    use ExtractsEmbeddedTaxId;
    use ResolvesClientAddresses;
    use ResolvesExistingClient;
    use ResolvesPorts;
    use ResolvesVoyageCargoType;

    protected array $warnings = [];
    protected array $createdClientIds = [];

    public function canParse(string $filePath): bool
    {
        $head = @file_get_contents($filePath, false, null, 0, 8192);
        if ($head === false) {
            return false;
        }
        $head = $this->normalizeContent($head);

        return preg_match('/\AVESSEL NAME(?: {2,}|\t+)\S/', $head) === 1
            && preg_match('/^VOYAGE(?: {2,}|\t+)\S/m', $head) === 1
            && preg_match('/^BILL OF LADING(?: {2,}|\t+)\S/m', $head) === 1
            && preg_match('/^LOADING PORT NAME(?: {2,}|\t+)\S/m', $head) === 1
            && preg_match('/^DISCHARGE PORT CODE(?: {2,}|\t+)\S/m', $head) === 1;
    }

    protected function normalizeContent(string $content): string
    {
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        return ltrim(str_replace(["\r\n", "\r"], "\n", $content), "\xEF\xBB\xBF \t\n");
    }

    /** Cada etiqueta conserva sus ocurrencias, incluidos los precintos vacíos. */
    protected function fields(string $section): array
    {
        $fields = [];
        foreach (explode("\n", trim($section)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = preg_split('/ {2,}|\t+/', $line, 2);
            $label = trim($parts[0]);
            $fields[$label][] = trim($parts[1] ?? '');
        }

        return $fields;
    }

    protected function value(array $fields, string $label): string
    {
        $values = $fields[$label] ?? [];
        if (count($values) > 1) {
            throw new DomainException("MSC: campo repetido fuera de su sección: {$label}.");
        }

        return $values[0] ?? '';
    }

    protected function extractBills(string $content): array
    {
        $bills = [];
        $sections = preg_split('/^###FIN BL###[ \t]*(?:\n|$)/m', $content);
        $tail = array_pop($sections);
        if (trim($tail) !== '' || $sections === []) {
            throw new DomainException('MSC: falta el cierre ###FIN BL### o hay contenido fuera de los conocimientos.');
        }
        foreach ($sections as $section) {
            $parts = preg_split('/^(?=NUMBER OUTER PACKAGES(?: {2,}|\t+))/m', trim($section));
            $header = $this->fields(array_shift($parts));
            $items = [];
            $totals = [];
            foreach ($parts as $part) {
                $split = preg_split('/^(?=TOTAL GROSS WEIGHT(?: {2,}|\t+))/m', $part);
                if (count($split) > 2 || ($totals !== [])) {
                    throw new DomainException('MSC: secciones de totales fuera de orden.');
                }
                $items[] = $this->fields($split[0]);
                if (isset($split[1])) {
                    $totals = $this->fields($split[1]);
                }
            }
            $bills[] = ['header' => $header, 'items' => $items, 'totals' => $totals];
        }
        if (count($bills) !== preg_match_all('/^BILL OF LADING(?: {2,}|\t+)/m', $content)) {
            throw new DomainException('MSC: la delimitación no conserva todos los conocimientos.');
        }

        return $bills;
    }

    protected function decimal(string $raw): float
    {
        $raw = trim($raw);
        if (!preg_match('/^\d+(?:[.,]\d+)*$/D', $raw)) {
            throw new DomainException('MSC: cantidad o peso ausente o inválido.');
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $decimal = strrpos($raw, ',') > strrpos($raw, '.') ? ',' : '.';
            $raw = str_replace($decimal === ',' ? '.' : ',', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw) || !is_finite((float) $raw)) {
            throw new DomainException('MSC: formato decimal ambiguo.');
        }

        return (float) $raw;
    }

    public function validate(array $data): array
    {
        try {
            if ($data === []) {
                throw new DomainException('MSC: no hay conocimientos.');
            }
            $numbers = [];
            $first = $data[0]['header'];
            foreach ($data as $index => $bill) {
                $header = $bill['header'];
                foreach (['VESSEL NAME', 'VOYAGE', 'LOADING PORT NAME', 'BILL OF LADING', 'DISCHARGE PORT CODE', 'SHIPPER', 'CONSIGNEE'] as $label) {
                    $value = $this->value($header, $label);
                    if ($value === '') {
                        throw new DomainException('MSC: falta ' . $label . ' en el conocimiento ' . ($index + 1) . '.');
                    }
                }
                foreach (['VESSEL NAME', 'VOYAGE', 'LOADING PORT NAME', 'DISCHARGE PORT CODE'] as $label) {
                    if ($this->value($header, $label) !== $this->value($first, $label)) {
                        throw new DomainException('MSC: el archivo contiene distintos viajes, embarcaciones o rutas.');
                    }
                }
                $number = $this->value($header, 'BILL OF LADING');
                if (isset($numbers[$number]) || mb_strlen($number) > 100 || mb_strlen($this->value($header, 'VOYAGE')) > 50) {
                    throw new DomainException('MSC: número de conocimiento duplicado o identificación demasiado extensa.');
                }
                $numbers[$number] = true;
                foreach (['SHIPPER', 'CONSIGNEE', 'NOTIFY'] as $role) {
                    if (mb_strlen($this->value($header, $role)) > 255) {
                        throw new DomainException('MSC: nombre de parte demasiado extenso.');
                    }
                }
                if ($bill['items'] === []) {
                    throw new DomainException('MSC: conocimiento sin mercadería.');
                }
                $packages = $weight = 0;
                foreach ($bill['items'] as $item) {
                    foreach (['DESCRIPTION', 'KIND OUTER PACKAGES', 'CONTAINER NR.', 'SIZE', 'TYPE CONTAINER'] as $label) {
                        if ($this->value($item, $label) === '') {
                            throw new DomainException("MSC: mercadería sin {$label}.");
                        }
                    }
                    $quantity = $this->decimal($this->value($item, 'NUMBER OUTER PACKAGES'));
                    $gross = $this->decimal($this->value($item, 'GROSS WEIGHT'));
                    $tare = $this->decimal($this->value($item, 'TARE'));
                    if ($quantity <= 0 || floor($quantity) !== $quantity || $quantity > 2147483647 || $gross <= 0 || $gross >= 1e10 || $tare >= 1e6) {
                        throw new DomainException('MSC: bultos, peso o tara fuera de rango.');
                    }
                    if (strtolower($this->value($item, 'GROSS WEIGHT UNIT')) !== 'kgs'
                        || $this->value($item, 'CONDITION') !== 'Port to Port'
                        || !preg_match('/^[A-Z]{4}\d{7}$/D', $this->value($item, 'CONTAINER NR.'))) {
                        throw new DomainException('MSC: unidad, condición o número de contenedor no soportado.');
                    }
                    $this->containerTypeCode($item);
                    $packages += $quantity;
                    $weight += $gross;
                }
                if ($packages > 2147483647 || $weight >= 1e10
                    || abs($packages - $this->decimal($this->value($bill['totals'], 'TOTAL NUMBER OUTER PACKAGES'))) > 0.001
                    || abs($weight - $this->decimal($this->value($bill['totals'], 'TOTAL GROSS WEIGHT'))) > 0.02) {
                    throw new DomainException('MSC: los totales de bultos o peso no coinciden con la mercadería.');
                }
                $this->decimal($this->value($bill['totals'], 'TOTAL TARE'));
            }
        } catch (DomainException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    protected function containerTypeCode(array $item): string
    {
        $code = $this->value($item, 'SIZE') . $this->value($item, 'TYPE CONTAINER');
        $mapping = ['20DV' => '20GP', '40DV' => '40GP', '40HC' => '40HC'];
        if (!isset($mapping[$code])) {
            throw new DomainException('MSC: tamaño y tipo de contenedor no soportados.');
        }

        return $mapping[$code];
    }

    protected function loadingPort(string $name): Port
    {
        $matches = Port::where('active', true)->where('name', $name)->get()
            ->filter(fn ($port) => mb_strtoupper(trim($port->name)) === mb_strtoupper(trim($name)));
        if ($matches->count() === 1) {
            return $matches->first();
        }
        $suggestions = [];
        foreach (Port::where('active', true)->get(['code', 'name']) as $port) {
            similar_text(mb_strtoupper($name), mb_strtoupper($port->name), $score);
            $suggestions[$port->code . ' (' . $port->name . ')'] = $score;
        }
        arsort($suggestions);
        throw new DomainException('MSC: puerto de carga inexistente o ambiguo. Revise el catálogo. Sugerencias: '
            . implode(', ', array_slice(array_keys($suggestions), 0, 3)) . '.');
    }

    protected function party(array $header, string $role, Port $port, int $companyId): ?Client
    {
        $name = $this->value($header, $role);
        if ($name === '') {
            return null;
        }
        if (mb_strlen($name) > 255) {
            throw new DomainException('MSC: nombre de parte demasiado extenso.');
        }
        $address = $this->value($header, $role . '´S ADRESS');
        $taxId = $this->resolveTaxId(null, $name, $address);
        $countryId = (int) $port->country_id;
        $documentTypeId = null;
        $type = match (strlen($taxId ?? '')) {
            11 => ['AR', 'CUIT'],
            7, 8, 9 => ['PY', 'RUC'],
            14 => ['BR', 'CNPJ'],
            10 => ['CO', 'NIT'],
            default => null,
        };
        if ($type !== null) {
            $countryId = (int) Country::where('alpha2_code', $type[0])->value('id');
            $documentTypeId = DocumentType::where('country_id', $countryId)->where('code', $type[1])
                ->where('active', true)->value('id');
            if (!$countryId || !$documentTypeId) {
                throw new DomainException('MSC: falta país o tipo documental activo para la identificación fiscal.');
            }
        }
        $resolved = $this->resolveExistingClient($taxId, $name, $countryId, $documentTypeId);
        $this->warnings = array_merge($this->warnings, $resolved['warnings']);
        if ($resolved['client']) {
            return $resolved['client'];
        }
        $client = Client::create([
            'legal_name' => $name, 'commercial_name' => $name, 'tax_id' => $taxId,
            'country_id' => $countryId, 'document_type_id' => $documentTypeId,
            'created_by_company_id' => $companyId, 'status' => 'active', 'verified_at' => now(),
        ]);
        $this->createdClientIds[] = $client->id;
        $this->rememberClientIdentity($client);

        return $client;
    }

    public function parse(string $filePath, array $options = []): ManifestParseResult
    {
        $this->warnings = [];
        $this->createdClientIds = [];
        $this->clientIdentityIndex = null;
        $import = null;
        try {
            if (!$this->canParse($filePath)) {
                throw new DomainException('El contenido no corresponde al formato MSC VESSEL NAME.');
            }
            $content = @file_get_contents($filePath);
            if ($content === false) {
                throw new DomainException('No se pudo leer el archivo MSC.');
            }
            $data = $this->transform($this->extractBills($this->normalizeContent($content)));
            $errors = $this->validate($data);
            if ($errors !== []) {
                return ManifestParseResult::failure($errors);
            }
            $user = auth()->user();
            $companyId = $user?->userable_type === Company::class
                ? (int) $user->userable_id
                : (int) ($user?->userable?->company_id ?? $user?->company_id ?? 0);
            if (!$user || !$companyId) {
                throw new DomainException('MSC requiere un usuario autenticado con empresa asignada.');
            }
            $vessel = Vessel::find($options['vessel_id'] ?? null);
            $header = $data[0]['header'];
            if (!$vessel || (int) $vessel->company_id !== $companyId
                || mb_strtoupper(trim($vessel->name)) !== mb_strtoupper($this->value($header, 'VESSEL NAME'))) {
                throw new DomainException('Seleccione vessel_id de su empresa que coincida con la embarcación del archivo MSC.');
            }
            if ($vessel->cargo_capacity_tons === null || $vessel->container_capacity === null) {
                throw new DomainException('MSC: complete las capacidades de la embarcación seleccionada antes de importar.');
            }
            $origin = $this->loadingPort($this->value($header, 'LOADING PORT NAME'));
            $destination = $this->resolvePortStrict($this->value($header, 'DISCHARGE PORT CODE'));
            $cargoType = CargoType::where('code', 'CON001')->where('active', true)->first();
            if (!$cargoType) {
                throw new DomainException('MSC: falta el tipo de carga CON001 activo en el catálogo.');
            }
            $types = [];
            foreach ($data as $bill) {
                foreach ($bill['items'] as $item) {
                    $code = $this->containerTypeCode($item);
                    $types[$code] ??= ContainerType::where('code', $code)->where('active', true)->first();
                    if (!$types[$code]) {
                        throw new DomainException("MSC: falta el tipo de contenedor {$code} activo en el catálogo.");
                    }
                }
            }
            $voyageNumber = $this->value($header, 'VOYAGE');
            $hash = ManifestImport::generateFileHash($filePath);
            if (ManifestImport::isFileAlreadyImported($hash, $companyId)) {
                throw new DomainException('El archivo ya fue importado. Revise el historial de importaciones.');
            }
            $import = ManifestImport::createForImport([
                'company_id' => $companyId, 'user_id' => $user->id,
                'file_name' => basename($filePath), 'file_format' => 'msc_vessel_name',
                'file_hash' => $hash, 'file_size_bytes' => filesize($filePath),
                'parser_config' => ['parser_class' => self::class, 'vessel_id' => $vessel->id],
            ]);

            return DB::transaction(function () use ($data, $companyId, $vessel, $origin, $destination, $cargoType, $types, $voyageNumber, $import) {
                return Model::withoutEvents(function () use ($data, $companyId, $vessel, $origin, $destination, $cargoType, $types, $voyageNumber, $import) {
                    if (Voyage::where('company_id', $companyId)->where('lead_vessel_id', $vessel->id)
                        ->where('voyage_number', $voyageNumber)->exists()) {
                        throw new DomainException('El viaje ya existe para esta embarcación y empresa. Revise el historial antes de volver a importar.');
                    }
                    $voyage = Voyage::create([
                        'company_id' => $companyId, 'lead_vessel_id' => $vessel->id, 'voyage_number' => $voyageNumber,
                        'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
                        'origin_country_id' => $origin->country_id, 'destination_country_id' => $destination->country_id,
                        'departure_date' => null, 'estimated_arrival_date' => null,
                        'voyage_type' => 'single_vessel', 'status' => 'planning',
                        'cargo_type' => $this->resolveVoyageCargoTypeForCompany($companyId, $origin, $destination),
                        'total_cargo_capacity_tons' => $vessel->cargo_capacity_tons,
                        'total_container_capacity' => $vessel->container_capacity,
                    ]);
                    $shipment = Shipment::create([
                        'voyage_id' => $voyage->id, 'vessel_id' => $vessel->id,
                        'shipment_number' => 'MSC-' . $voyage->id, 'sequence_in_voyage' => 1,
                        'vessel_role' => 'single', 'is_lead_vessel' => true, 'status' => 'planning',
                        'cargo_capacity_tons' => $vessel->cargo_capacity_tons,
                        'container_capacity' => $vessel->container_capacity,
                    ]);
                    $bills = $items = $containers = $createdContainers = [];
                    $totalWeight = 0;
                    foreach ($data as $billIndex => $source) {
                        $h = $source['header'];
                        $shipper = $this->party($h, 'SHIPPER', $origin, $companyId);
                        $consignee = $this->party($h, 'CONSIGNEE', $destination, $companyId);
                        $notify = $this->party($h, 'NOTIFY', $destination, $companyId);
                        $bill = BillOfLading::create([
                            'shipment_id' => $shipment->id, 'bill_number' => $this->value($h, 'BILL OF LADING'),
                            'shipper_id' => $shipper->id, 'consignee_id' => $consignee->id, 'notify_party_id' => $notify?->id,
                            'loading_port_id' => $origin->id, 'discharge_port_id' => $destination->id,
                            'bill_date' => null, 'loading_date' => null, 'freight_terms' => null,
                            'primary_cargo_type_id' => $cargoType->id, 'primary_packaging_type_id' => null,
                            'cargo_description' => implode("\n", array_unique(array_map(fn ($i) => $this->value($i, 'DESCRIPTION'), $source['items']))),
                            'gross_weight_kg' => $this->decimal($this->value($source['totals'], 'TOTAL GROSS WEIGHT')),
                            'total_packages' => (int) $this->decimal($this->value($source['totals'], 'TOTAL NUMBER OUTER PACKAGES')),
                            'net_weight_kg' => null, 'status' => 'draft',
                            'webservice_data' => ['msc' => ['header' => $h, 'totals' => $source['totals']]],
                        ]);
                        $bills[] = $bill;
                        foreach ([['SHIPPER', 'shipper', $shipper], ['CONSIGNEE', 'consignee', $consignee], ['NOTIFY', 'notify_party', $notify]] as [$label, $role, $client]) {
                            if ($client === null) {
                                continue;
                            }
                            $address = $this->value($h, $label . '´S ADRESS');
                            $this->persistClientAddress($client, $address);
                            $specific = $this->resolveSpecificAddress($client, $address, $role);
                            if ($specific !== null) {
                                $bill->specificContacts()->create($specific);
                            }
                        }
                        $billContainerIds = [];
                        $tareSum = 0;
                        $dangerous = false;
                        foreach ($source['items'] as $line => $row) {
                            $number = $this->value($row, 'CONTAINER NR.');
                            $gross = $this->decimal($this->value($row, 'GROSS WEIGHT'));
                            $quantity = (int) $this->decimal($this->value($row, 'NUMBER OUTER PACKAGES'));
                            $tare = $this->decimal($this->value($row, 'TARE'));
                            $seals = array_values(array_filter($row['SEAL NR.'] ?? [], fn ($s) => $s !== ''));
                            $container = $containers[$number] ?? Container::where('container_number', $number)->first();
                            $type = $types[$this->containerTypeCode($row)];
                            if ($container && $container->container_type_id && (int) $container->container_type_id !== (int) $type->id) {
                                throw new DomainException('MSC: el tipo declarado difiere del contenedor existente. Revise el catálogo.');
                            }
                            if (!$container) {
                                $container = Container::create([
                                    'container_number' => $number, 'container_type_id' => $type->id,
                                    'tare_weight_kg' => $tare, 'max_gross_weight_kg' => null,
                                    'condition' => 'L', 'container_condition' => 'P',
                                    'operational_status' => 'loaded', 'active' => true,
                                ]);
                                $createdContainers[] = $container->id;
                            }
                            $containers[$number] = $container;
                            $billContainerIds[$container->id] = true;
                            $isDangerous = $this->value($row, 'IMO') !== '' && $this->value($row, 'IMO') !== 'N';
                            $dangerous = $dangerous || $isDangerous;
                            $item = ShipmentItem::create([
                                'bill_of_lading_id' => $bill->id, 'line_number' => $line + 1,
                                'cargo_type_id' => $cargoType->id, 'packaging_type_id' => null,
                                'package_quantity' => $quantity, 'gross_weight_kg' => $gross, 'net_weight_kg' => null,
                                'item_description' => $this->value($row, 'DESCRIPTION'),
                                'package_type_description' => $this->value($row, 'KIND OUTER PACKAGES'),
                                'cargo_marks' => $this->value($row, 'MARCS & NUMBERS') ?: null,
                                'is_dangerous_goods' => $isDangerous,
                                'un_number' => $this->value($row, 'UNNO') ?: null,
                                'imdg_class' => $this->value($row, 'DG CLASS') ?: ($isDangerous ? $this->value($row, 'IMO') : null),
                                'container_condition' => 'P', 'created_by_user_id' => auth()->id(),
                                'currency_code' => null, 'webservice_data' => ['msc' => $row],
                            ]);
                            $items[] = $item;
                            DB::table('container_shipment_item')->insert([
                                'container_id' => $container->id, 'shipment_item_id' => $item->id,
                                'package_quantity' => $quantity, 'gross_weight_kg' => $gross,
                                'source_seals' => json_encode($seals, JSON_THROW_ON_ERROR),
                                'source_line_numbers' => json_encode([$line + 1], JSON_THROW_ON_ERROR),
                                'container_condition' => 'P', 'status' => 'loaded',
                                'created_by_user_id' => auth()->id(), 'created_date' => now(),
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $tareSum += $tare;
                            $totalWeight += $gross;
                        }
                        $bill->update(['container_count' => count($billContainerIds), 'contains_dangerous_goods' => $dangerous]);
                        if (abs($tareSum - $this->decimal($this->value($source['totals'], 'TOTAL TARE'))) > 0.02) {
                            $this->warnings[] = 'Conocimiento ' . ($billIndex + 1) . ': la tara total difiere de la suma por sección; se conservaron los valores de origen.';
                        }
                    }
                    $shipment->update(['cargo_weight_loaded' => $totalWeight, 'containers_loaded' => count($containers)]);
                    $voyage->update(['total_cargo_weight_loaded' => $totalWeight, 'total_containers_loaded' => count($containers)]);
                    $stats = ['processed_bls' => count($bills), 'processed_items' => count($items),
                        'processed_containers' => count($containers), 'container_associations' => count($items), 'import_id' => $import->id];
                    $import->recordExplicitlyCreatedObjects([
                        'voyage' => [$voyage->id], 'shipment' => [$shipment->id],
                        'bill' => array_map(fn ($b) => $b->id, $bills), 'item' => array_map(fn ($i) => $i->id, $items),
                        'container' => $createdContainers, 'client' => $this->createdClientIds, 'port' => [],
                    ]);
                    $this->warnings = array_values(array_unique($this->warnings));
                    $completion = ['voyage_id' => $voyage->id, 'import_statistics' => $stats,
                        'warnings' => $this->warnings, 'warnings_count' => count($this->warnings)];
                    if ($this->warnings === []) {
                        $import->markAsCompleted($completion);
                    } else {
                        $import->markAsCompletedWithWarnings($completion);
                    }

                    return ManifestParseResult::success($voyage, [$shipment], array_values($containers), $bills, $this->warnings, $stats);
                });
            });
        } catch (Throwable $e) {
            $message = $e instanceof DomainException ? $e->getMessage() : 'No se pudo importar el manifiesto MSC. No se guardaron datos de carga. Revise los catálogos y la configuración.';
            if ($e instanceof QueryException && str_contains($e->getMessage(), 'voyages_voyage_number_unique')) {
                $message = 'El viaje ya existe para esta embarcación y empresa. No se duplicó la carga.';
            }
            if ($import !== null) {
                try {
                    $import->markAsFailed([$message]);
                } catch (Throwable) {
                    return ManifestParseResult::failure([$message, 'No se pudo actualizar el estado del historial de importación.']);
                }
            }

            return ManifestParseResult::failure([$message]);
        }
    }

    public function transform(array $data): array
    {
        return $data;
    }

    public function getFormatInfo(): array
    {
        return ['name' => 'MSC VESSEL NAME', 'description' => 'Manifiesto TXT con cierre ###FIN BL###', 'extensions' => ['txt']];
    }

    public function getDefaultConfig(): array
    {
        return ['required_fields' => ['VESSEL NAME', 'VOYAGE', 'BILL OF LADING'], 'requires_vessel_selection' => true];
    }
}
