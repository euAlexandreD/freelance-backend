<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServicoController extends Controller
{
    public function index(Request $request)
    {
        $query = Servico::query()->comDetalhes()->where('ativo', true);

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
        abort_unless($servico->ativo, 404);

        return Servico::query()->comDetalhes()->findOrFail($servico->id);
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
            ->comDetalhes()
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

        $data = $this->validatedData($request, required: true);

        $servico = $perfilPrestador->servicos()->create($data);

        return response()->json(Servico::query()->comDetalhes()->findOrFail($servico->id), 201);
    }

    public function update(Request $request, Servico $servico)
    {
        Gate::authorize('update', $servico);

        $servico->update($this->validatedData($request, required: false, atual: $servico));

        return Servico::query()->comDetalhes()->findOrFail($servico->id);
    }

    public function destroy(Request $request, Servico $servico)
    {
        Gate::authorize('update', $servico);

        if ($servico->pedidos()->exists()) {
            return response()->json([
                'message' => 'Este serviço já tem pedidos e não pode ser excluído. Pause-o para que não apareça mais no catálogo.',
            ], 422);
        }

        $servico->delete();

        return response()->json(['message' => 'Serviço removido com sucesso.']);
    }

    /**
     * Valida os campos do serviço e mantém preço e tipo de preço coerentes:
     * "a combinar" nunca tem preço, e os outros tipos sempre têm.
     */
    private function validatedData(Request $request, bool $required, ?Servico $atual = null): array
    {
        $presence = $required ? 'required' : 'sometimes';
        $tipoPreco = $request->input('tipo_preco', $atual?->tipo_preco ?? 'a_combinar');

        $data = $request->validate([
            'categoria_servico_id' => [$presence, 'exists:categoria_servicos,id'],
            'titulo' => [$presence, 'string', 'min:5', 'max:120'],
            'descricao' => [$presence, 'string', 'max:5000'],
            'tipo_preco' => [$presence, Rule::in(Servico::TIPOS_PRECO)],
            'preco' => [
                Rule::requiredIf($tipoPreco !== 'a_combinar' && ($required || $request->has('tipo_preco'))),
                'nullable', 'numeric', 'min:1', 'max:9999999',
            ],
            'ativo' => ['boolean'],
        ], [
            'preco.required' => 'Informe o preço ou escolha "a combinar".',
        ]);

        if ($tipoPreco === 'a_combinar') {
            $data['preco'] = null;
        }

        return $data;
    }
}
