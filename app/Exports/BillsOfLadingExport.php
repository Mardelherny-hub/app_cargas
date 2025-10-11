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

class BillsOfLadingExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize, WithEvents
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = new Collection();
        
        $rows->push(['LISTADO DE CONOCIMIENTOS DE EMBARQUE']);
        $rows->push(['']);
        $rows->push(['Empresa:', $this->data['company']['legal_name']]);
        $rows->push(['Período:', $this->data['metadata']['period']]);
        if (!empty($this->data['filters'])) {
            $rows->push(['Filtros:', implode(' | ', $this->data['filters'])]);
        }
        $rows->push(['']);
        $rows->push(['CONOCIMIENTOS']);
        $rows->push(['']);
        
        foreach ($this->data['bills_of_lading'] as $bill) {
            $rows->push([
                $bill['line_number'],
                $bill['bill_number'],
                $bill['bill_date'],
                $bill['voyage_number'],
                $bill['vessel_name'],
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
                $bill['status_label'],
                $bill['contains_dangerous_goods'] ? 'SÍ' : 'NO',
            ]);
        }
        
        $rows->push(['']);
        $rows->push([
            'TOTALES',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'Total BLs:',
            $this->data['totals']['total_bills'],
            $this->data['totals']['total_packages'],
            number_format($this->data['totals']['total_gross_weight_kg'], 2, '.', ''),
            number_format($this->data['totals']['total_net_weight_kg'], 2, '.', ''),
            number_format($this->data['totals']['total_volume_m3'], 2, '.', ''),
        ]);
        
        $rows->push(['']);
        $rows->push(['Generado por:', $this->data['metadata']['generated_by']]);
        $rows->push(['Fecha:', $this->data['metadata']['generated_at']]);
        
        return $rows;
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E40AF']]],
        ];
    }

    public function title(): string
    {
        return 'Conocimientos';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $sheet->mergeCells('A1:Q1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1E40AF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                ]);
                
                $headerRow = 9;
                $headers = ['#', 'BL Number', 'Fecha', 'Viaje', 'Embarcación', 'Cargador', 'CUIT Cargador', 
                           'Consignatario', 'CUIT Consignatario', 'Puerto Carga', 'Puerto Descarga', 
                           'Bultos', 'Peso Bruto (kg)', 'Peso Neto (kg)', 'Volumen (m³)', 'Estado', 'Peligroso'];
                
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue("{$col}{$headerRow}", $header);
                    $col++;
                }
                
                $sheet->getStyle("A{$headerRow}:Q{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                
                $billsCount = count($this->data['bills_of_lading']);
                if ($billsCount > 0) {
                    $dataStart = 10;
                    $dataEnd = $dataStart + $billsCount - 1;
                    
                    $sheet->getStyle("A{$dataStart}:Q{$dataEnd}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                    ]);
                    
                    for ($i = $dataStart; $i <= $dataEnd; $i++) {
                        if (($i - $dataStart) % 2 == 0) {
                            $sheet->getStyle("A{$i}:Q{$i}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
                        }
                    }
                    
                    $totalsRow = $dataEnd + 2;
                    $sheet->getStyle("A{$totalsRow}:Q{$totalsRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                    ]);
                }
            },
        ];
    }
}