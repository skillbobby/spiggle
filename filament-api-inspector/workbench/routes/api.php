<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/orders', function () {
    return response()->json([
        'data' => [
            ['id' => 1, 'reference' => 'ORD-1042', 'status' => 'paid', 'total' => 12900],
            ['id' => 2, 'reference' => 'ORD-1043', 'status' => 'pending', 'total' => 24900],
        ],
        'meta' => ['count' => 2, 'source' => 'workbench'],
    ]);
});

Route::post('/orders', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'id' => 1044,
        'status' => 'created',
        'received' => $request->all(),
    ], 201);
});

Route::get('/orders/{order}', function (string $order) {
    return response()->json([
        'id' => (int) $order,
        'status' => 'paid',
        'items' => [
            ['sku' => 'SPG-1', 'qty' => 2],
        ],
    ]);
});

Route::get('/reports/revenue', function () {
    return response()->json([
        'currency' => 'USD',
        'revenue' => 184250,
        'period' => '2026-10',
    ]);
});
