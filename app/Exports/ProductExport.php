<?php

namespace App\Exports;

use App\Models\ProductModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProductExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $search = trim((string)($this->filters['search'] ?? ''));
        $item   = trim((string)($this->filters['item'] ?? ''));
        $brand  = trim((string)($this->filters['brand'] ?? ''));

        $q = ProductModel::query()
            ->orderBy('id', 'desc');

        // Same search logic as your fetch()
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('sku', 'like', "%{$search}%")
                  ->orWhere('grade_no', 'like', "%{$search}%")
                  ->orWhere('size', 'like', "%{$search}%");
            });
        }

        if ($item !== '') {
            $q->where('item_name', 'like', "%{$item}%");
        }

        if ($brand !== '') {
            $q->where('brand', (int) $brand);
        }

        // select only needed columns (fast)
        return $q->get([
            'sku',
            'grade_no',
            'item_name',
            'size',
            'brand',
            'units',
            'list_price',
            'hsn',
            'tax',
            'low_stock_level',
        ]);
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Grade',
            'Item',
            'Size',
            'Brand',
            'Unit',
            'List Price',
            'HSN',
            'Tax',
            'Low Stock Level',
        ];
    }

    public function map($p): array
    {
        // NOTE: your ProductModel has brandRef relation
        // If you want brand NAME, load relation (but it’s slower unless eager-loaded)
        // For now exporting brand ID to match your DB column.
        return [
            (string) $p->sku,
            (string) ($p->grade_no ?? ''),
            (string) ($p->item_name ?? ''),
            (string) ($p->size ?? ''),
            (string) ($p->brand ?? ''),     // brand id (or you can output brand name)
            (string) ($p->units ?? ''),
            (string) number_format((float)($p->list_price ?? 0), 2, '.', ''),
            (string) ($p->hsn ?? ''),
            (string) number_format((float)($p->tax ?? 0), 2, '.', ''),
            (string) ($p->low_stock_level ?? 0),
        ];
    }

    // Heading row styling (bold + center)
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                // Determine used range
                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn(); // e.g. "J"
                $range      = "A1:{$highestCol}{$highestRow}";

                // Wrap text for all cells (helps long item names)
                $sheet->getStyle($range)->getAlignment()->setWrapText(true);

                // Apply borders to all filled cells
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Center headings already, but set row height a bit better
                $sheet->getRowDimension(1)->setRowHeight(20);

                // OPTIONAL: center some columns (SKU/Grade/Size/Brand/Unit/Tax etc.)
                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E2:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F2:F{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("I2:I{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("J2:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
