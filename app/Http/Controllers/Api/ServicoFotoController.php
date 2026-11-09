<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use App\Models\ServicoFoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServicoFotoController extends Controller
{
    public function store(Request $request, Servico $servico)
    {
        Gate::authorize('update', $servico);

        $vagas = Servico::MAX_FOTOS - $servico->fotos()->count();

        $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:'.max($vagas, 0)],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'fotos.max' => 'Cada serviço pode ter no máximo '.Servico::MAX_FOTOS.' fotos.',
            'fotos.*.max' => 'Cada foto pode ter no máximo 4 MB.',
        ]);

        $ordem = (int) $servico->fotos()->max('ordem');

        foreach ($request->file('fotos') as $arquivo) {
            $servico->fotos()->create([
                'path' => $arquivo->store("servicos/{$servico->id}", 'public'),
                'ordem' => ++$ordem,
            ]);
        }

        return response()->json($servico->fotos()->get(), 201);
    }

    public function destroy(ServicoFoto $servicoFoto)
    {
        Gate::authorize('update', $servicoFoto->servico);

        $servicoFoto->delete();

        return response()->noContent();
    }
}
