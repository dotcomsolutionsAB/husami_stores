<?php

namespace App\Imports;

use App\Models\GodownModel;
use App\Models\ProductModel;
use App\Models\ProductStockModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductStockImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    private int $total = 0;
    private int $success = 0;
    private int $failed = 0;

    private array $failedRows = [];

    // Caches for speed (avoid DB queries per row)
    private array $godownMap = []; // lower(name) => id
    private array $skuSet = [];    // lower(sku) => true

    // This helps correct row numbering (since heading row is row 1)
    private int $headingOffset = 1;

    public function __construct()
    {
        // Build caches once
        $this->godownMap = GodownModel::query()
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(fn($g) => [mb_strtolower(trim($g->name)) => (int) $g->id])
            ->all();

        $this->skuSet = ProductModel::query()
            ->select('sku')
            ->get()
            ->mapWithKeys(fn($p) => [mb_strtolower(trim($p->sku)) => true])
            ->all();
    }

    public function chunkSize(): int
    {
        return 500; // adjust as needed
    }

    public function collection(Collection $rows)
    {
        // We will batch insert for performance
        $insertBatch = [];

        foreach ($rows as $index => $row) {
            // $index starts at 0 for this chunk
            // Real excel row number = heading row (1) + current data index + 1
            $rowNumber = $this->headingOffset + $this->total + 2;

            $this->total++;

            // Normalize keys expected from file (heading row)
            // Required file headings:
            // SKU, Godown, qty, ctn, batch_no, rack_no, invoice_no, invoice_date, tc_no, tc_date, remarks
            $sku         = $this->toStr($row['sku'] ?? null);
            $godownName  = $this->toStr($row['godown'] ?? null);
            $qtyRaw      = $row['qty'] ?? null;
            $ctnRaw      = $row['ctn'] ?? null;
            $batchNo     = $this->toStr($row['batch_no'] ?? null);
            $rackNo      = $this->toStr($row['rack_no'] ?? null);
            $invoiceNo   = $this->toStr($row['invoice_no'] ?? null);
            $invoiceDate = $row['invoice_date'] ?? null;
            $tcNo        = $this->toStr($row['tc_no'] ?? null);
            $tcDate      = $row['tc_date'] ?? null;
            $remarks     = $this->toStr($row['remarks'] ?? null);

            $errors = [];

            // ---- required checks (non-nullable columns) ----
            if ($sku === '')        $errors[] = 'SKU is required.';
            if ($godownName === '') $errors[] = 'Godown is required.';
            if ($batchNo === '')    $errors[] = 'batch_no is required.';
            if ($rackNo === '')     $errors[] = 'rack_no is required.';
            if ($invoiceNo === '')  $errors[] = 'invoice_no is required.';
            if ($invoiceDate === null || $this->toStr($invoiceDate) === '') $errors[] = 'invoice_date is required.';
            if ($qtyRaw === null || $this->toStr($qtyRaw) === '') $errors[] = 'qty is required.';
            if ($ctnRaw === null || $this->toStr($ctnRaw) === '') $errors[] = 'ctn is required.';

            // ---- sku must exist in products ----
            if ($sku !== '' && !isset($this->skuSet[mb_strtolower($sku)])) {
                $errors[] = 'Invalid SKU (not found in products).';
            }

            // ---- godown name must exist -> id ----
            $godownId = null;
            if ($godownName !== '') {
                $key = mb_strtolower($godownName);
                if (!isset($this->godownMap[$key])) {
                    $errors[] = 'Invalid godown name (not found in godowns).';
                } else {
                    $godownId = (int) $this->godownMap[$key];
                }
            }

            // ---- qty & ctn numeric/integer ----
            $qty = $this->parseInt($qtyRaw);
            if ($qty === null) $errors[] = 'qty must be a valid integer.';

            $ctn = $this->parseInt($ctnRaw);
            if ($ctn === null) $errors[] = 'ctn must be a valid integer.';

            // ---- dates ----
            $invoiceDateParsed = $this->parseDate($invoiceDate);
            if ($invoiceDateParsed === null) $errors[] = 'invoice_date must be a valid date.';

            $tcDateParsed = null;
            if ($this->toStr($tcDate) !== '') {
                $tcDateParsed = $this->parseDate($tcDate);
                if ($tcDateParsed === null) $errors[] = 'tc_date must be a valid date (or blank).';
            }

            if (!empty($errors)) {
                $this->failed++;
                $this->failedRows[] = [
                    'row'    => $rowNumber,
                    'sku'    => $sku,
                    'godown' => $godownName,
                    'errors' => $errors,
                ];
                continue;
            }

            $insertBatch[] = [
                'sku'          => $sku,
                'godown_id'    => $godownId,
                'quantity'     => $qty,
                'ctn'          => $ctn,
                'sent'         => 0,
                'batch_no'     => $batchNo,
                'rack_no'      => $rackNo,
                'invoice_no'   => $invoiceNo,
                'invoice_date' => $invoiceDateParsed->format('Y-m-d'),
                'tc_no'        => $tcNo !== '' ? $tcNo : null,
                'tc_date'      => $tcDateParsed ? $tcDateParsed->format('Y-m-d') : null,
                'tc_attachment'=> null,
                'remarks'      => $remarks !== '' ? $remarks : null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            // Batch flush every N rows to avoid memory growth
            if (count($insertBatch) >= 1000) {
                $this->flushInsert($insertBatch);
                $insertBatch = [];
            }
        }

        // flush leftovers
        if (!empty($insertBatch)) {
            $this->flushInsert($insertBatch);
        }
    }

    private function flushInsert(array $batch): void
    {
        // If you want: wrap in a transaction per batch
        DB::transaction(function () use ($batch) {
            ProductStockModel::insert($batch);
        });

        $this->success += count($batch);
    }

    public function getResult(): array
    {
        return [
            'total_records'      => $this->total,
            'successful_records' => $this->success,
            'failed_records'     => $this->failed,
            'failed_rows'        => $this->failedRows,
        ];
    }

    private function toStr($v): string
    {
        if ($v === null) return '';
        $s = trim((string) $v);
        return $s;
    }

    private function parseInt($v): ?int
    {
        if ($v === null) return null;

        // Excel might give numeric as float, CSV as string
        if (is_numeric($v)) {
            $n = (int) $v;
            return ($n >= 0) ? $n : null;
        }

        $s = preg_replace('/[,\s]/', '', (string) $v);
        if ($s === '' || !preg_match('/^\d+$/', $s)) return null;

        return (int) $s;
    }

    private function parseDate($v): ?Carbon
    {
        if ($v === null) return null;

        // If already Carbon or DateTime
        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v);
        }

        $s = trim((string) $v);
        if ($s === '') return null;

        // Accept common formats (you can add more if needed)
        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'Y/m/d'];
        foreach ($formats as $f) {
            try {
                $dt = Carbon::createFromFormat($f, $s);
                if ($dt && $dt->format($f) === $s) return $dt;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Last attempt: Carbon parse (handles many strings, but can be risky)
        try {
            return Carbon::parse($s);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
