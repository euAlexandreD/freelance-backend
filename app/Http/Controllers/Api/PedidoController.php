<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Servico;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    public function store(Request $request)
    {
        $perfilCliente = $request->user()->perfilCliente;

        if (! $perfilCliente) {
            return response()->json([
                'message' => 'Somente clientes podem solicitar serviços.',
            ], 403);
        }

        $data = $request->validate([
            'servico_id' => ['required', Rule::exists('servicos', 'id')->where('ativo', true)],
            'descricao_pedido' => ['nullable', 'string'],
        ]);

        $servico = Servico::findOrFail($data['servico_id']);
        $perfilPrestador = $request->user()->perfilPrestador;

        if ($perfilPrestador && $servico->perfil_prestador_id === $perfilPrestador->id) {
            return response()->json([
                'message' => 'Você não pode solicitar seu próprio serviço.',
            ], 422);
        }

        $pedido = $perfilCliente->pedidos()->create([
            'servico_id' => $data['servico_id'],
            'descricao_pedido' => $data['descricao_pedido'] ?? null,
        ]);

        return $pedido->load(['servico.categoriaServico', 'servico.perfilPrestador.user']);
    }

    public function meus(Request $request)
    {
        $perfilCliente = $request->user()->perfilCliente;

        if (! $perfilCliente) {
            return response()->json([
                'message' => 'Somente clientes possuem pedidos.',
            ], 403);
        }

        return $perfilCliente->pedidos()
            ->with(['servico.categoriaServico', 'servico.perfilPrestador.user', 'servico.fotos', 'avaliacao'])
            ->latest()
            ->get();
    }

    public function recebidos(Request $request)
    {
        $perfilPrestador = $request->user()->perfilPrestador;

        if (! $perfilPrestador) {
            return response()->json([
                'message' => 'Somente prestadores recebem pedidos.',
            ], 403);
        }

        return Pedido::whereHas('servico', function ($query) use ($perfilPrestador) {
            $query->where('perfil_prestador_id', $perfilPrestador->id);
        })
            ->with(['servico.categoriaServico', 'servico.fotos', 'perfilCliente.user', 'avaliacao'])
            ->latest()
            ->get();
    }

    public function aceitar(Request $request, Pedido $pedido)
    {
        $this->authorizePrestador($request, $pedido);

        abort_unless($pedido->status === 'pendente', 422, 'Este pedido já foi respondido.');

        $pedido->update(['status' => 'aceito']);

        return $pedido->load(['servico.categoriaServico', 'perfilCliente.user']);
    }

    public function recusar(Request $request, Pedido $pedido)
    {
        $this->authorizePrestador($request, $pedido);

        abort_unless($pedido->status === 'pendente', 422, 'Este pedido já foi respondido.');

        $pedido->update(['status' => 'recusado']);

        return $pedido->load(['servico.categoriaServico', 'perfilCliente.user']);
    }

    public function concluir(Request $request, Pedido $pedido)
    {
        $this->authorizePrestador($request, $pedido);

        abort_unless($pedido->status === 'aceito', 422, 'Só é possível concluir pedidos aceitos.');

        $pedido->update(['status' => 'concluido']);

        return $pedido->load(['servico.categoriaServico', 'perfilCliente.user']);
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        $perfilCliente = $request->user()->perfilCliente;

        if (! $perfilCliente || $pedido->perfil_cliente_id !== $perfilCliente->id) {
            abort(403, 'Você não tem permissão para cancelar este pedido.');
        }

        abort_unless(in_array($pedido->status, ['pendente', 'aceito'], true), 422, 'Este pedido não pode mais ser cancelado.');

        $pedido->update(['status' => 'cancelado']);

        return $pedido->load(['servico.categoriaServico', 'servico.perfilPrestador.user']);
    }

    private function authorizePrestador(Request $request, Pedido $pedido): void
    {
        $perfilPrestador = $request->user()->perfilPrestador;
        $pedido->loadMissing('servico');

        if (! $perfilPrestador || $pedido->servico->perfil_prestador_id !== $perfilPrestador->id) {
            abort(403, 'Você não tem permissão para alterar este pedido.');
        }
    }
}
