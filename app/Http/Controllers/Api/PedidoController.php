<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Servico;
use App\Notifications\PedidoAtualizado;
use App\Notifications\PedidoRecebido;
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
            'descricao_pedido' => ['nullable', 'string', 'max:2000'],
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

        $pedido->prestadorUser()->notify(new PedidoRecebido($pedido));

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
            ->comNaoLidas($request->user())
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
            ->comNaoLidas($request->user())
            ->with(['servico.categoriaServico', 'servico.fotos', 'perfilCliente.user', 'avaliacao'])
            ->latest()
            ->get();
    }

    public function aceitar(Request $request, Pedido $pedido)
    {
        $this->authorizePrestador($request, $pedido);

        $this->mudarStatus($pedido, ['pendente'], 'aceito', 'Este pedido já foi respondido.');
        $pedido->clienteUser()->notify(new PedidoAtualizado($pedido));

        return $pedido->load(['servico.categoriaServico', 'perfilCliente.user']);
    }

    public function recusar(Request $request, Pedido $pedido)
    {
        $this->authorizePrestador($request, $pedido);

        $this->mudarStatus($pedido, ['pendente'], 'recusado', 'Este pedido já foi respondido.');
        $pedido->clienteUser()->notify(new PedidoAtualizado($pedido));

        return $pedido->load(['servico.categoriaServico', 'perfilCliente.user']);
    }

    public function concluir(Request $request, Pedido $pedido)
    {
        $this->authorizePrestador($request, $pedido);

        $this->mudarStatus($pedido, ['aceito'], 'concluido', 'Só é possível concluir pedidos aceitos.');
        $pedido->clienteUser()->notify(new PedidoAtualizado($pedido));

        return $pedido->load(['servico.categoriaServico', 'perfilCliente.user']);
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        $perfilCliente = $request->user()->perfilCliente;

        if (! $perfilCliente || $pedido->perfil_cliente_id !== $perfilCliente->id) {
            abort(403, 'Você não tem permissão para cancelar este pedido.');
        }

        $this->mudarStatus($pedido, ['pendente', 'aceito'], 'cancelado', 'Este pedido não pode mais ser cancelado.');
        $pedido->prestadorUser()->notify(new PedidoAtualizado($pedido));

        return $pedido->load(['servico.categoriaServico', 'servico.perfilPrestador.user']);
    }

    /**
     * Troca o status só se o pedido ainda estiver num dos status esperados, numa única consulta.
     * Assim dois cliques (ou cliente e prestador ao mesmo tempo) não aplicam duas mudanças nem mandam dois e-mails.
     */
    private function mudarStatus(Pedido $pedido, array $de, string $para, string $erro): void
    {
        $alterados = Pedido::whereKey($pedido->id)
            ->whereIn('status', $de)
            ->update(['status' => $para, 'updated_at' => now()]);

        abort_if($alterados === 0, 422, $erro);

        $pedido->refresh();
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
