<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'Face Skin Predict API',
        'version' => '1.0',
        'status' => 'ok',
    ]);
});
