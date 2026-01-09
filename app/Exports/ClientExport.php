<?php

namespace App\Exports;

use App\Models\ClientModel;
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

class ClientExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $search = trim((string)($this->filters['search'] ?? ''));

        $q = ClientModel::query()->orderBy('id', 'desc');

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

    public function map($c): array
    {
        return [
            (string)($c->id ?? ''),
            (string)($c->name ?? ''),
            (string)($c->mobile ?? ''),
            (string)($c->email ?? ''),
            (string)($c->address_line_1 ?? ''),
            (string)($c->address_line_2 ?? ''),
            (string)($c->city ?? ''),
            (string)($c->pincode ?? ''),
            (string)($c->state ?? ''),
            (string)($c->country ?? ''),
            (string)($c->gstin ?? ''),
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

                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn(); // should be "K"
                $range      = "A1:{$highestCol}{$highestRow}";

                // wrap text
                $sheet->getStyle($range)->getAlignment()->setWrapText(true);

                // borders
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(20);

                // align columns
                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ID
                $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Name
                $sheet->getStyle("C2:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Mobile
                $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Email
                $sheet->getStyle("E2:F{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Address lines
                $sheet->getStyle("G2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // City
                $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Pincode
                $sheet->getStyle("I2:I{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // State
                $sheet->getStyle("J2:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Country
                $sheet->getStyle("K2:K{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // GSTIN

                $sheet->freezePane('A2');
            },
        ];
    }
}
