<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaServicoController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ServicoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/categorias-servico', [CategoriaServicoController::class, 'index']);
Route::get('/servicos', [ServicoController::class, 'index']);
Route::get('/servicos/{servico}', [ServicoController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/meus-servicos', [ServicoController::class, 'meus']);
    Route::post('/servicos', [ServicoController::class, 'store']);
    Route::put('/servicos/{servico}', [ServicoController::class, 'update']);
    Route::delete('/servicos/{servico}', [ServicoController::class, 'destroy']);
});
