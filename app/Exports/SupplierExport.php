<?php

namespace App\Exports;

use App\Models\SupplierModel;
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

class SupplierExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $search = trim((string)($this->filters['search'] ?? ''));

        $q = SupplierModel::query()->orderBy('id', 'desc');

        // same filter as fetch()
        if ($search !== '') {
            $q->where('name', 'like', "%{$search}%");
        }

        return $q->get([
            'id',
            'name',
            'mobile',
            'email',
            'address_line_1',
            'address_line_2',
            'city',
            'pincode',
            'state',
            'country',
            'gstin',
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Mobile',
            'Email',
            'Address Line 1',
            'Address Line 2',
            'City',
            'Pincode',
            'State',
            'Country',
            'GSTIN',
        ];
    }

    public function map($s): array
    {
        return [
            (string)($s->id ?? ''),
            (string)($s->name ?? ''),
            (string)($s->mobile ?? ''),
            (string)($s->email ?? ''),
            (string)($s->address_line_1 ?? ''),
            (string)($s->address_line_2 ?? ''),
            (string)($s->city ?? ''),
            (string)($s->pincode ?? ''),
            (string)($s->state ?? ''),
            (string)($s->country ?? ''),
            (string)($s->gstin ?? ''),
        ];
    }

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

                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn(); // should be "K"
                $range      = "A1:{$highestCol}{$highestRow}";

                $sheet->getStyle($range)->getAlignment()->setWrapText(true);

                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(20);

                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("C2:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("E2:F{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("G2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I2:I{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("J2:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("K2:K{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->freezePane('A2');
            },
        ];
    }
}
