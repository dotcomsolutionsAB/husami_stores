<?php

namespace App\Imports;

use App\Models\BrandModel;
use App\Models\ProductModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    private int $total = 0;
    private int $success = 0;
    private int $failed = 0;

    private array $failedRows = [];

    // caches
    private array $brandMap = [];     // lower(brand_name) => id
    private array $existingSku = [];  // lower(sku) => true

    public function __construct()
    {
        $this->brandMap = BrandModel::query()
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(fn($b) => [mb_strtolower(trim($b->name)) => (int) $b->id])
            ->all();

        // If you want to SKIP duplicates, keep this.
        // If you want to UPSERT, tell me and I’ll change logic.
        $this->existingSku = ProductModel::query()
            ->select('sku')
            ->get()
            ->mapWithKeys(fn($p) => [mb_strtolower(trim($p->sku)) => true])
            ->all();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $this->total + 2; // heading row is 1
            $this->total++;

            // expected headings in file:
            // SKU, Grade, Item, Size, Brand, Unit, list_price, hsn, tax, low_stock_level

            $sku            = $this->toStr($row['sku'] ?? null);
            $grade          = $this->toStr($row['grade'] ?? null);
            $item           = $this->toStr($row['item'] ?? null);
            $size           = $this->toStr($row['size'] ?? null);
            $brandName      = $this->toStr($row['brand'] ?? null);
            $unitRaw        = $row['unit'] ?? null; // in DB it's 'units' column (id or numeric for now)
            $listPriceRaw   = $row['list_price'] ?? null;
            $hsn            = $this->toStr($row['hsn'] ?? null);
            $taxRaw         = $row['tax'] ?? null;
            $lowStockRaw    = $row['low_stock_level'] ?? null;

            $errors = [];

            // REQUIRED (not nullable): sku, item_name
            if ($sku === '')  $errors[] = 'SKU is required.';
            if ($item === '') $errors[] = 'Item is required.';

            // SKU unique check
            if ($sku !== '' && isset($this->existingSku[mb_strtolower($sku)])) {
                $errors[] = 'Duplicate SKU (already exists).';
            }

            // Brand name -> id (brand column is nullable, but you said if invalid brand name then error)
            $brandId = null;
            if ($brandName !== '') {
                $key = mb_strtolower($brandName);
                if (!isset($this->brandMap[$key])) {
                    $errors[] = 'Invalid brand name (not found in brands).';
                } else {
                    $brandId = (int) $this->brandMap[$key];
                }
            }

            // Numeric validations
            // units is nullable in DB but you asked: if provided and not numeric -> error
            $units = null;
            if ($this->toStr($unitRaw) !== '') {
                $units = $this->parseInt($unitRaw);
                if ($units === null) $errors[] = 'Unit must be a valid integer.';
            }

            // list_price default 0: if provided and invalid -> error
            $listPrice = $this->parseDecimal($listPriceRaw);
            if ($listPrice === null) $errors[] = 'list_price must be a valid number.';

            // tax default 0: if provided and invalid -> error
            $tax = $this->parseDecimal($taxRaw);
            if ($tax === null) $errors[] = 'tax must be a valid number.';

            // low_stock_level default 0: if provided and invalid -> error
            $lowStock = $this->parseInt($lowStockRaw);
            if ($lowStock === null) $errors[] = 'low_stock_level must be a valid integer.';

            if (!empty($errors)) {
                $this->failed++;
                $this->failedRows[] = [
                    'row'    => $rowNumber,
                    'sku'    => $sku,
                    'errors' => $errors,
                ];
                continue;
            }

            $batch[] = [
                'sku'             => $sku,
                'grade_no'        => $grade !== '' ? $grade : null,
                'item_name'       => $item,
                'size'            => $size !== '' ? $size : null,
                'brand'           => $brandId,         // brand id
                'units'           => $units,           // numeric (or null)
                'list_price'      => $listPrice,       // decimal string/float ok
                'hsn'             => $hsn !== '' ? $hsn : null,
                'tax'             => $tax,
                'low_stock_level' => $lowStock,
                'finish_type'     => null,
                'specifications'  => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            if (count($batch) >= 1000) {
                $this->flushInsert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->flushInsert($batch);
        }
    }

    private function flushInsert(array $batch): void
    {
        DB::transaction(function () use ($batch) {
            ProductModel::insert($batch);
        });

        $this->success += count($batch);

        // update sku cache so duplicates within same file are caught
        foreach ($batch as $r) {
            $this->existingSku[mb_strtolower($r['sku'])] = true;
        }
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
        return trim((string) $v);
    }

    private function parseInt($v): ?int
    {
        if ($v === null) return null;

        if (is_numeric($v)) {
            $n = (int) $v;
            return ($n >= 0) ? $n : null;
        }

        $s = preg_replace('/[,\s]/', '', (string) $v);
        if ($s === '' || !preg_match('/^\d+$/', $s)) return null;

        return (int) $s;
    }

    private function parseDecimal($v): ?string
    {
        // Return string to avoid float issues; DB decimal handles it well
        if ($v === null || trim((string) $v) === '') return "0.00";

        if (is_numeric($v)) {
            return number_format((float) $v, 2, '.', '');
        }

        $s = str_replace([',', ' '], '', (string) $v);
        if ($s === '' || !preg_match('/^\d+(\.\d+)?$/', $s)) return null;

        return number_format((float) $s, 2, '.', '');
    }
}
