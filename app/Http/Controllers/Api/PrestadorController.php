<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use App\Models\PerfilPrestador;
use App\Models\Servico;
use Illuminate\Support\Str;

class PrestadorController extends Controller
{
    /** Perfil público do prestador: dados de apresentação, serviços ativos e avaliações. */
    public function show(PerfilPrestador $perfilPrestador)
    {
        $user = $perfilPrestador->user;

        $avaliacoes = $perfilPrestador->avaliacoes()
            ->with(['servico:id,titulo', 'pedido.perfilCliente.user:id,name,avatar_path'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Avaliacao $avaliacao) => [
                'id' => $avaliacao->id,
                'nota' => $avaliacao->nota,
                'comentario' => $avaliacao->comentario,
                'created_at' => $avaliacao->created_at,
                'servico' => $avaliacao->servico?->only(['id', 'titulo']),
                'cliente' => $this->nomeCurto($avaliacao->pedido->perfilCliente->user->name),
            ]);

        return [
            'id' => $perfilPrestador->id,
            'titulo' => $perfilPrestador->titulo,
            'bio' => $perfilPrestador->bio,
            'link_externo' => $perfilPrestador->link_externo,
            'membro_desde' => $perfilPrestador->created_at,
            'user' => $user->only(['id', 'name', 'avatar_url', 'cidade', 'uf']),
            'nota_media' => $perfilPrestador->avaliacoes()->avg('nota'),
            'avaliacoes_count' => $perfilPrestador->avaliacoes()->count(),
            'pedidos_concluidos' => $perfilPrestador->servicos()
                ->join('pedidos', 'pedidos.servico_id', '=', 'servicos.id')
                ->where('pedidos.status', 'concluido')
                ->count(),
            'servicos' => Servico::query()
                ->comDetalhes()
                ->where('perfil_prestador_id', $perfilPrestador->id)
                ->where('ativo', true)
                ->latest()
                ->get(),
            'avaliacoes' => $avaliacoes,
        ];
    }

    /** "Fernanda Costa" vira "Fernanda C.", para não expor o nome completo do cliente. */
    private function nomeCurto(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome));

        if (count($partes) < 2) {
            return $partes[0];
        }

        return $partes[0].' '.Str::upper(Str::substr(end($partes), 0, 1)).'.';
    }
}
