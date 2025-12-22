<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CsvExportService
{
    /**
     * @param string   $folderRelative   e.g. 'exports/clients'
     * @param string   $filePrefix       e.g. 'clients'
     * @param array    $headers          CSV header row
     * @param \Illuminate\Support\Collection|\Traversable $items
     * @param callable $mapRow           function($item){ return [...] }
     * @return array   ['file_name'=>..., 'relative_path'=>..., 'url'=>...]
     */
    public function export(string $folderRelative, string $filePrefix, array $headers, $items, callable $mapRow): array
    {
        $dir = storage_path('app/public/' . trim($folderRelative, '/'));
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $fileName = $filePrefix . '_' . now()->format('YmdHis') . '_' . Str::random(6) . '.csv';
        $fullPath = $dir . '/' . $fileName;

        $file = fopen($fullPath, 'w');

        // headers
        fputcsv($file, $headers);

        // rows
        foreach ($items as $item) {
            fputcsv($file, $mapRow($item));
        }

        fclose($file);

        $relativePath = trim($folderRelative, '/') . '/' . $fileName;

        return [
            'file_name'      => $fileName,
            'relative_path'  => $relativePath,
            'url'            => asset('storage/' . $relativePath),
        ];
    }
}