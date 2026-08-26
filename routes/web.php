<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/storage/signatures/{filename}', function ($filename) {
    $path = 'signatures/' . $filename;

    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['error' => 'Signature file not found'], 404);
    }

    $file = Storage::disk('public')->get($path);
    $type = Storage::disk('public')->mimeType($path);

    return response()->json([
        'data' => 'data:' . $type . ';base64,' . base64_encode($file),
    ])->header('Access-Control-Allow-Origin', '*')
      ->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
});