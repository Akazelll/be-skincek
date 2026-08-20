<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'data' => [
        'name' => config('app.name'),
        'version' => 'v1',
        'status' => 'ok',
    ],
    'meta' => (object) [],
]));
