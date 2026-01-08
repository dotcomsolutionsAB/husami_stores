<?php

namespace App\Exports;

use App\Models\BrandModel;
use App\Models\GodownModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProductStockExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    private array $params = [];

    private array $brandNameById = [];
    private array $godownNameById = [];

    public function __construct(Request $request)
    {
        // same params your fetch uses
        $this->params = [
            'search'         => trim((string)$request->input('search', '')),
            'brand'          => trim((string)$request->input('brand', '')),
            'specifications' => trim((string)$request->input('specifications', '')),
            'item'           => trim((string)$request->input('item', '')),
            'size'           => trim((string)$request->input('size', '')),
            'finish'         => trim((string)$request->input('finish', '')),
            'rack_no'        => trim((string)$request->input('rack_no', '')),
            'grade'          => trim((string)$request->input('grade', '')),
            'godown'         => trim((string)$request->input('godown', '')),
        ];
    }

    public function collection(): Collection
    {
        $toArray = function ($value) {
            return collect(explode(',', (string)$value))
                ->map(fn($v) => trim($v))
                ->filter()
                ->values()
                ->all();
        };

        $brandArr  = $toArray($this->params['brand']);
        $gradeArr  = $toArray($this->params['grade']);
        $finishArr = $toArray($this->params['finish']);
        $godownArr = $toArray($this->params['godown']);

        $q = DB::table('t_product_stocks as s')
            ->join('t_products as p', 'p.sku', '=', 's.sku')
            ->select(
                // product
                'p.sku',
                'p.grade_no',
                'p.item_name',
                'p.size as product_size',
                'p.brand as brand_id',
                'p.finish_type',
                'p.specifications',

                // stock
                's.id',
                's.godown_id',
                's.quantity',
                's.ctn',
                's.sent',
                's.batch_no',
                's.rack_no',
                's.invoice_no',
                's.invoice_date',
                's.tc_no',
                's.tc_date',
                's.tc_attachment',
                's.remarks',
                's.created_at',
                's.updated_at'
            )
            ->orderBy('s.id', 'desc');

        // ✅ tokenized search (same as fetch)
        $this->applyTokenSearch($q, $this->params['search']);

        if ($this->params['rack_no'] !== '') {
            $q->where('s.rack_no', 'like', "%{$this->params['rack_no']}%");
        }

        if (!empty($brandArr)) {
            $q->whereIn('p.brand', array_map('intval', $brandArr));
        }

        if (!empty($gradeArr)) {
            $q->whereIn(DB::raw('CAST(p.grade_no AS CHAR)'), $gradeArr);
        }

        if (!empty($finishArr)) {
            $q->whereIn('p.finish_type', $finishArr);
        }

        if (!empty($godownArr)) {
            $q->whereIn('s.godown_id', array_map('intval', $godownArr));
        }

        if ($this->params['item'] !== '') {
            $q->where('p.item_name', 'like', "%{$this->params['item']}%");
        }

        if ($this->params['size'] !== '') {
            $q->where('p.size', $this->params['size']);
        }

        if ($this->params['specifications'] !== '') {
            $q->where('p.specifications', 'like', "%{$this->params['specifications']}%");
        }

        $items = $q->get();

        // ✅ Build brand/godown name maps (fast)
        $brandIds = collect($items)->pluck('brand_id')->filter()->unique()->values()->all();
        $godownIds = collect($items)->pluck('godown_id')->filter()->unique()->values()->all();

        $this->brandNameById = empty($brandIds)
            ? []
            : BrandModel::whereIn('id', $brandIds)->pluck('name', 'id')->all();

        $this->godownNameById = empty($godownIds)
            ? []
            : GodownModel::whereIn('id', $godownIds)->pluck('name', 'id')->all();

        return collect($items);
    }

    // ✅ same “tokenized search” idea as your controller
    // If you already have applyTokenSearch() in controller, copy that logic here.
    private function applyTokenSearch($q, string $search): void
    {
        $s = trim($search);
        if ($s === '') return;

        $tokens = preg_split('/\s+/', $s) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens)));

        foreach ($tokens as $t) {
            $q->where(function ($w) use ($t) {
                $w->where('s.rack_no', 'like', "%{$t}%")
                  ->orWhere('s.batch_no', 'like', "%{$t}%")
                  ->orWhere('s.invoice_no', 'like', "%{$t}%")
                  ->orWhere('s.tc_no', 'like', "%{$t}%")
                  ->orWhere('p.sku', 'like', "%{$t}%")
                  ->orWhere('p.item_name', 'like', "%{$t}%")
                  ->orWhere('p.grade_no', 'like', "%{$t}%")
                  ->orWhere('p.size', 'like', "%{$t}%");
            });
        }
    }

    public function headings(): array
    {
        return [
            // product
            'SKU',
            'Grade No',
            'Item Name',
            'Product Size',
            'Brand',
            'Finish Type',
            'Specifications',

            // stock
            'Stock ID',
            'Godown',
            'Quantity',
            'CTN',
            'Sent',
            'Batch No',
            'Rack No',
            'Invoice No',
            'Invoice Date',
            'TC No',
            'TC Date',
            'Remarks',
            'Created At',
            'Updated At',
        ];
    }

    public function map($it): array
    {
        $brandName  = $it->brand_id ? ($this->brandNameById[$it->brand_id] ?? '') : '';
        $godownName = $it->godown_id ? ($this->godownNameById[$it->godown_id] ?? '') : '';

        return [
            (string)($it->sku ?? ''),
            (string)($it->grade_no ?? ''),
            (string)($it->item_name ?? ''),
            (string)($it->product_size ?? ''),
            (string)($brandName),
            (string)($it->finish_type ?? ''),
            (string)($it->specifications ?? ''),

            (string)($it->id ?? ''),
            (string)($godownName),
            (string)($it->quantity ?? ''),
            (string)($it->ctn ?? ''),
            (string)($it->sent ?? ''),
            (string)($it->batch_no ?? ''),
            (string)($it->rack_no ?? ''),
            (string)($it->invoice_no ?? ''),
            (string)($it->invoice_date ?? ''),
            (string)($it->tc_no ?? ''),
            (string)($it->tc_date ?? ''),
            (string)($it->remarks ?? ''),
            (string)($it->created_at ?? ''),
            (string)($it->updated_at ?? ''),
        ];
    }

    // ✅ headings bold + centered
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

    // ✅ borders + wrap text for used range
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn();
                $range      = "A1:{$highestCol}{$highestRow}";

                // wrap text everywhere
                $sheet->getStyle($range)->getAlignment()->setWrapText(true);

                // borders
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // row height for header
                $sheet->getRowDimension(1)->setRowHeight(20);

                // optional: freeze header row
                $sheet->freezePane('A2');
            },
        ];
    }
}
