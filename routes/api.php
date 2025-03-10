<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\PedidosController;
use App\Http\Controllers\ProductosController;
use App\Http\Middleware\IsUserAuth;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('verify-token', [AuthController::class, 'verifyToken']);
Route::post('reset-password', [AuthController::class, 'resetAccountPass']);
Route::get('/pedidos/{id}/pdf', [PedidosController::class, 'generatePDF']);


//Auth Routes 
Route::middleware([IsUserAuth::class])->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'getUser']);

    Route::prefix('productos')->group(function () {
        Route::get('/', [ProductosController::class, 'getAll']);
        Route::post('/create', [ProductosController::class, 'create']);
        Route::get('/{id}', [ProductosController::class, 'getById']);
        Route::put('/edit', [ProductosController::class, 'edit']);
        Route::delete('/delete', [ProductosController::class, 'delete']);
        Route::get('/search', [ProductosController::class, 'search']);
        Route::get('/productos/total_items', [ProductosController::class, 'getTotalProducts']);
    });

    Route::prefix('pedidos')->group(function () {
        Route::get('/', [PedidosController::class, 'getAll']);
        Route::post('/create', [PedidosController::class, 'create']);
        Route::get('/{id}', [PedidosController::class, 'getById']);
        Route::put('/{id}', [PedidosController::class, 'edit']);
        Route::delete('/{id}', [PedidosController::class, 'delete']);
        Route::get('/total/cost', [PedidosController::class, 'getTotalCost']);
    });
    Route::get('/search/products', [ProductosController::class, 'search']);
    Route::get('/users/search', [AuthController::class, 'searchUsers']);
    
    Route::prefix('kardex')->group(function () {
        Route::get('/producto/{producto_id}', [KardexController::class, 'getMovimientos']);
        Route::post('/movimiento', [KardexController::class, 'registrarMovimiento']);
        Route::get('/reporte/{producto_id}', [KardexController::class, 'generarReporteKardex']);
        Route::get('kardex/producto/movimientos', [KardexController::class, 'getMovimientosPorProducto']);
    });
    
});

