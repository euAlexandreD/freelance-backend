<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = Servico::with(['categoriaServico', 'perfilPrestador.user'])
            ->where('ativo', true);

        if ($request->filled('categoria_servico_id')) {
            $query->where('categoria_servico_id', $request->integer('categoria_servico_id'));
        }

        if ($request->filled('perfil_prestador_id')) {
            $query->where('perfil_prestador_id', $request->integer('perfil_prestador_id'));
        }

        return $query->latest()->get();
    }

    public function show(Servico $servico)
    {
        return $servico->load(['categoriaServico', 'perfilPrestador.user']);
    }

    public function meus(Request $request)
    {
        $perfilPrestador = $request->user()->perfilPrestador;

        if (! $perfilPrestador) {
            return response()->json([
                'message' => 'Somente prestadores possuem serviços cadastrados.',
            ], 403);
        }

        return $perfilPrestador->servicos()
            ->with('categoriaServico')
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $perfilPrestador = $request->user()->perfilPrestador;

        if (! $perfilPrestador) {
            return response()->json([
                'message' => 'Somente prestadores podem cadastrar serviços.',
            ], 403);
        }

        $data = $request->validate([
            'categoria_servico_id' => ['required', 'exists:categoria_servicos,id'],
            'descricao' => ['required', 'string'],
            'ativo' => ['boolean'],
        ]);

        $servico = $perfilPrestador->servicos()->create($data);

        return $servico->load(['categoriaServico', 'perfilPrestador.user']);
    }

    public function update(Request $request, Servico $servico)
    {
        $this->authorizeOwner($request, $servico);

        $data = $request->validate([
            'categoria_servico_id' => ['sometimes', 'exists:categoria_servicos,id'],
            'descricao' => ['sometimes', 'string'],
            'ativo' => ['boolean'],
        ]);

        $servico->update($data);

        return $servico->load(['categoriaServico', 'perfilPrestador.user']);
    }

    public function destroy(Request $request, Servico $servico)
    {
        $this->authorizeOwner($request, $servico);

        $servico->delete();

        return response()->json(['message' => 'Serviço removido com sucesso.']);
    }

    private function authorizeOwner(Request $request, Servico $servico): void
    {
        $perfilPrestador = $request->user()->perfilPrestador;

        if (! $perfilPrestador || $servico->perfil_prestador_id !== $perfilPrestador->id) {
            abort(403, 'Você não tem permissão para alterar este serviço.');
        }
    }
}
