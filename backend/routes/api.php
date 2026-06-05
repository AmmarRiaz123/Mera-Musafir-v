<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/ping', function () {
    return response()->json([
        'message' => 'Mera Musafir API is alive',
        'status'  => 'ok'
    ]);
});