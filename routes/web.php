<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Weight Log API',
        'version' => '1.0.0'
    ]);
});
