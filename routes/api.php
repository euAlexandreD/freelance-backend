<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvaliacaoController;
use App\Http\Controllers\Api\CategoriaServicoController;
use App\Http\Controllers\Api\MensagemController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\PrestadorController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ServicoController;
use App\Http\Controllers\Api\ServicoFotoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Limita tentativas por IP para dificultar força bruta de senha e criação de contas em massa.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/categorias-servico', [CategoriaServicoController::class, 'index']);
Route::get('/servicos', [ServicoController::class, 'index']);
Route::get('/servicos/{servico}', [ServicoController::class, 'show']);
Route::get('/prestadores/{perfilPrestador}', [PrestadorController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('throttle:20,1');

    Route::get('/meus-servicos', [ServicoController::class, 'meus']);
    Route::post('/servicos', [ServicoController::class, 'store']);
    Route::put('/servicos/{servico}', [ServicoController::class, 'update']);
    Route::delete('/servicos/{servico}', [ServicoController::class, 'destroy']);
    Route::post('/servicos/{servico}/fotos', [ServicoFotoController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('/servico-fotos/{servicoFoto}', [ServicoFotoController::class, 'destroy']);

    // Cada pedido dispara um e-mail para o prestador: o limite evita usar a API para spam.
    Route::post('/pedidos', [PedidoController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/meus-pedidos', [PedidoController::class, 'meus']);
    Route::get('/pedidos-recebidos', [PedidoController::class, 'recebidos']);
    Route::put('/pedidos/{pedido}/aceitar', [PedidoController::class, 'aceitar']);
    Route::put('/pedidos/{pedido}/recusar', [PedidoController::class, 'recusar']);
    Route::put('/pedidos/{pedido}/concluir', [PedidoController::class, 'concluir']);
    Route::put('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar']);
    Route::post('/pedidos/{pedido}/avaliacao', [AvaliacaoController::class, 'store']);
    Route::get('/pedidos/{pedido}/mensagens', [MensagemController::class, 'index']);
    Route::post('/pedidos/{pedido}/mensagens', [MensagemController::class, 'store'])->middleware('throttle:30,1');
});
