<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-log', function (Request $request) {
    // Optional security: use a secret key in URL (e.g., /clear-log?key=56789)
    if ($request->query('key') !== '56789') {
        abort(403, 'Unauthorized access.');
    }

    try {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, ''); // Clear contents
            return response()->json([
                'status'  => true,
                'message' => 'Laravel log file cleared successfully!',
            ], 200);
        } else {
            return response()->json([
                'status'  => false,
                'message' => 'No log file found.',
            ], 404);
        }

    } catch (\Throwable $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Error while clearing log file: ' . $e->getMessage(),
        ], 500);
    }
});