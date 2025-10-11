<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

/**
 * Export de Manifiesto de Carga a Excel
 * 
 * Genera archivo Excel con formato profesional usando los datos
 * preparados por ManifestReportService
 * 
 * BASADO EN CAMPOS REALES VERIFICADOS
 */
class ManifestExport implements 
    FromCollection, 
    WithHeadings, 
    WithStyles, 
    WithTitle, 
    ShouldAutoSize,
    WithEvents
{
    protected array $data;
    protected int $totalRows = 0;

    /**
     * Constructor
     * 
     * @param array $data Datos preparados por ManifestReportService->prepareData()
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retornar colección de datos para el Excel
     * 
     * @return Collection
     */
    public function collection()
    {
        $rows = new Collection();
        
        // === SECCIÓN 1: INFORMACIÓN DEL VIAJE ===
        $rows->push(['MANIFIESTO DE CARGA']);
        $rows->push(['']);
        $rows->push(['DATOS DEL VIAJE']);
        $rows->push(['Número de Viaje:', $this->data['voyage']['voyage_number']]);
        $rows->push(['Embarcación:', $this->data['voyage']['vessel_name']]);
        $rows->push(['Capitán:', $this->data['voyage']['captain_name']]);
        $rows->push(['Puerto Origen:', $this->data['voyage']['origin_port'] . ' (' . $this->data['voyage']['origin_country'] . ')']);
        $rows->push(['Puerto Destino:', $this->data['voyage']['destination_port'] . ' (' . $this->data['voyage']['destination_country'] . ')']);
        $rows->push(['Fecha Salida:', $this->data['voyage']['departure_date']]);
        $rows->push(['Fecha Arribo Est.:', $this->data['voyage']['estimated_arrival_date']]);
        $rows->push(['Estado:', ucfirst($this->data['voyage']['status'])]);
        $rows->push(['']);
        
        // === SECCIÓN 2: DATOS DE CONOCIMIENTOS ===
        $rows->push(['LISTADO DE CONOCIMIENTOS DE EMBARQUE']);
        $rows->push(['']);
        
        // Headers de la tabla se agregan automáticamente por WithHeadings
        // Aquí van los datos
        foreach ($this->data['bills_of_lading'] as $bill) {
            $rows->push([
                $bill['line_number'],
                $bill['bill_number'],
                $bill['shipper_name'],
                $bill['shipper_tax_id'] ?? '-',
                $bill['consignee_name'],
                $bill['consignee_tax_id'] ?? '-',
                $bill['loading_port'],
                $bill['discharge_port'],
                $bill['total_packages'],
                number_format($bill['gross_weight_kg'], 2, '.', ''),
                number_format($bill['net_weight_kg'], 2, '.', ''),
                number_format($bill['volume_m3'], 2, '.', ''),
                $bill['cargo_description'],
                $bill['contains_dangerous_goods'] ? 'SÍ' : 'NO',
                $bill['requires_refrigeration'] ? 'SÍ' : 'NO',
            ]);
        }
        
        $this->totalRows = $rows->count();
        
        // === SECCIÓN 3: TOTALES ===
        $rows->push(['']); // Fila vacía
        $rows->push([
            'TOTALES',
            '',
            '',
            '',
            '',
            '',
            '',
            'Total BLs:',
            $this->data['totals']['total_bills'],
            number_format($this->data['totals']['total_gross_weight_kg'], 2, '.', ''),
            number_format($this->data['totals']['total_net_weight_kg'], 2, '.', ''),
            number_format($this->data['totals']['total_volume_m3'], 2, '.', ''),
        ]);
        
        $rows->push([
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'Total Bultos:',
            $this->data['totals']['total_packages'],
            'Peso (tons):',
            number_format($this->data['totals']['total_gross_weight_tons'], 3, '.', ''),
        ]);
        
        $rows->push(['']); // Fila vacía
        
        // === SECCIÓN 4: METADATA ===
        $rows->push(['INFORMACIÓN DEL REPORTE']);
        $rows->push(['Generado por:', $this->data['metadata']['generated_by']]);
        $rows->push(['Fecha/Hora:', $this->data['metadata']['generated_at']]);
        $rows->push(['Empresa:', $this->data['metadata']['generated_by_company']]);
        $rows->push(['Tipo:', $this->data['metadata']['report_type']]);
        
        if ($this->data['totals']['bills_with_dangerous_goods'] > 0) {
            $rows->push(['']);
            $rows->push(['⚠️ ATENCIÓN:', 'Este viaje contiene ' . $this->data['totals']['bills_with_dangerous_goods'] . ' conocimiento(s) con mercancías peligrosas']);
        }
        
        return $rows;
    }

    /**
     * Definir encabezados de columnas para la tabla de BLs
     * 
     * @return array
     */
    public function headings(): array
    {
        // Se insertan después de la fila 15 (aproximadamente)
        // donde comienza la tabla de conocimientos
        return [];
    }

    /**
     * Aplicar estilos a la hoja
     * 
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Aplicar estilos se hace mejor en el evento AfterSheet
        // Aquí solo retornamos estilos básicos
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => '1E40AF']
                ],
            ],
        ];
    }

    /**
     * Título de la hoja
     * 
     * @return string
     */
    public function title(): string
    {
        return 'Manifiesto ' . substr($this->data['voyage']['voyage_number'], 0, 25);
    }

    /**
     * Eventos para aplicar formato avanzado
     * 
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // === ESTILO DEL TÍTULO ===
                $sheet->mergeCells('A1:O1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 18,
                        'color' => ['rgb' => '1E40AF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'DBEAFE'],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);
                
                // === ESTILO SECCIÓN "DATOS DEL VIAJE" ===
                $sheet->mergeCells('A3:O3');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563EB'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                    ],
                ]);
                
                // === ESTILO DATOS DEL VIAJE (filas 4-11) ===
                for ($i = 4; $i <= 11; $i++) {
                    $sheet->getStyle("A{$i}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$i}:B{$i}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F3F4F6'],
                        ],
                    ]);
                }
                
                // === ESTILO SECCIÓN "LISTADO DE CONOCIMIENTOS" ===
                $sheet->mergeCells('A13:O13');
                $sheet->getStyle('A13')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2563EB'],
                    ],
                ]);
                
                // === HEADERS DE LA TABLA (fila 15) ===
                $headerRow = 15;
                $headers = [
                    'A' => '#',
                    'B' => 'BL Number',
                    'C' => 'Cargador',
                    'D' => 'CUIT/RUC Cargador',
                    'E' => 'Consignatario',
                    'F' => 'CUIT/RUC Consignatario',
                    'G' => 'Puerto Carga',
                    'H' => 'Puerto Descarga',
                    'I' => 'Bultos',
                    'J' => 'Peso Bruto (kg)',
                    'K' => 'Peso Neto (kg)',
                    'L' => 'Volumen (m³)',
                    'M' => 'Descripción',
                    'N' => 'Peligroso',
                    'O' => 'Refrigerado',
                ];
                
                foreach ($headers as $col => $header) {
                    $sheet->setCellValue("{$col}{$headerRow}", $header);
                }
                
                $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E40AF'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '1E3A8A'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                
                // === ESTILO FILAS DE DATOS ===
                $billsCount = count($this->data['bills_of_lading']);
                $dataStartRow = 16;
                $dataEndRow = $dataStartRow + $billsCount - 1;
                
                if ($billsCount > 0) {
                    // Bordes para todas las celdas de datos
                    $sheet->getStyle("A{$dataStartRow}:O{$dataEndRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                    
                    // Alineación de columnas numéricas
                    $sheet->getStyle("I{$dataStartRow}:L{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    
                    // Zebra striping (filas alternas)
                    for ($i = $dataStartRow; $i <= $dataEndRow; $i++) {
                        if (($i - $dataStartRow) % 2 == 0) {
                            $sheet->getStyle("A{$i}:O{$i}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F9FAFB'],
                                ],
                            ]);
                        }
                    }
                }
                
                // === ESTILO SECCIÓN TOTALES ===
                $totalsRow = $dataEndRow + 2;
                $sheet->getStyle("A{$totalsRow}:O{$totalsRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'DBEAFE'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['rgb' => '2563EB'],
                        ],
                    ],
                ]);
                
                // Segunda fila de totales
                $totalsRow2 = $totalsRow + 1;
                $sheet->getStyle("A{$totalsRow2}:K{$totalsRow2}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'EFF6FF'],
                    ],
                ]);
                
                // === ESTILO SECCIÓN METADATA ===
                $metadataStartRow = $totalsRow + 4;
                $sheet->mergeCells("A{$metadataStartRow}:O{$metadataStartRow}");
                $sheet->getStyle("A{$metadataStartRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => '1F2937'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                ]);
                
                // Siguiente 4 filas de metadata
                for ($i = 1; $i <= 4; $i++) {
                    $row = $metadataStartRow + $i;
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FAFAFA'],
                        ],
                    ]);
                }
                
                // === WARNING MERCANCÍAS PELIGROSAS ===
                if ($this->data['totals']['bills_with_dangerous_goods'] > 0) {
                    $warningRow = $metadataStartRow + 6;
                    $sheet->mergeCells("A{$warningRow}:O{$warningRow}");
                    $sheet->getStyle("A{$warningRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '991B1B'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEE2E2'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }
                
                // === AJUSTAR ANCHOS DE COLUMNA ===
                $sheet->getColumnDimension('A')->setWidth(6);   // #
                $sheet->getColumnDimension('B')->setWidth(18);  // BL Number
                $sheet->getColumnDimension('C')->setWidth(30);  // Cargador
                $sheet->getColumnDimension('D')->setWidth(18);  // CUIT Cargador
                $sheet->getColumnDimension('E')->setWidth(30);  // Consignatario
                $sheet->getColumnDimension('F')->setWidth(18);  // CUIT Consignatario
                $sheet->getColumnDimension('G')->setWidth(20);  // Puerto Carga
                $sheet->getColumnDimension('H')->setWidth(20);  // Puerto Descarga
                $sheet->getColumnDimension('I')->setWidth(10);  // Bultos
                $sheet->getColumnDimension('J')->setWidth(16);  // Peso Bruto
                $sheet->getColumnDimension('K')->setWidth(16);  // Peso Neto
                $sheet->getColumnDimension('L')->setWidth(14);  // Volumen
                $sheet->getColumnDimension('M')->setWidth(40);  // Descripción
                $sheet->getColumnDimension('N')->setWidth(12);  // Peligroso
                $sheet->getColumnDimension('O')->setWidth(12);  // Refrigerado
                
                // Wrap text en descripción
                $sheet->getStyle("M{$dataStartRow}:M{$dataEndRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}