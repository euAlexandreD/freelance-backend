<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;

class AvaliacaoController extends Controller
{
    public function store(Request $request, Pedido $pedido)
    {
        $perfilCliente = $request->user()->perfilCliente;

        if (! $perfilCliente || $pedido->perfil_cliente_id !== $perfilCliente->id) {
            abort(403, 'Você não tem permissão para avaliar este pedido.');
        }

        abort_unless($pedido->status === 'concluido', 422, 'Só é possível avaliar pedidos concluídos.');
        abort_if($pedido->avaliacao()->exists(), 422, 'Este pedido já foi avaliado.');

        $data = $request->validate([
            'nota' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $avaliacao = $pedido->avaliacao()->create([
            ...$data,
            'servico_id' => $pedido->servico_id,
            'perfil_prestador_id' => $pedido->servico->perfil_prestador_id,
        ]);

        return response()->json($avaliacao, 201);
    }
}
