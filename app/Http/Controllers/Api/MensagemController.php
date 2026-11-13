<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensagem;
use App\Models\Pedido;
use App\Models\User;
use App\Notifications\NovaMensagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class MensagemController extends Controller
{
    /** Intervalo mínimo entre dois e-mails de "nova mensagem" para a mesma pessoa no mesmo pedido. */
    public const INTERVALO_AVISO_MINUTOS = 15;

    /** Status em que a conversa fica só para leitura. */
    public const STATUS_ENCERRADOS = ['recusado', 'cancelado'];

    /**
     * Lista as mensagens do pedido e marca como lidas as que vieram da outra parte.
     * Com `depois_de`, devolve só as mensagens novas (usado pelo frontend para atualizar a conversa).
     */
    public function index(Request $request, Pedido $pedido)
    {
        Gate::authorize('conversar', $pedido);

        $user = $request->user();

        $pedido->mensagens()
            ->where('user_id', '!=', $user->id)
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);

        // Quem leu a conversa volta a poder receber o aviso por e-mail da próxima mensagem.
        // Sem isso, uma mensagem nova até 15 min depois de outra já lida nunca gerava e-mail.
        Cache::forget(self::chaveAviso($pedido, $user));

        $mensagens = $pedido->mensagens()
            ->with('autor:id,name')
            ->when($request->filled('depois_de'), fn ($q) => $q->where('id', '>', $request->integer('depois_de')))
            ->orderBy('id')
            ->get()
            ->map(fn (Mensagem $mensagem) => $this->formatar($mensagem, $user->id));

        return [
            'mensagens' => $mensagens,
            'pode_enviar' => ! in_array($pedido->status, self::STATUS_ENCERRADOS, true),
        ];
    }

    public function store(Request $request, Pedido $pedido)
    {
        Gate::authorize('conversar', $pedido);

        abort_if(
            in_array($pedido->status, self::STATUS_ENCERRADOS, true),
            422,
            'Este pedido foi encerrado e não recebe mais mensagens.',
        );

        $data = $request->validate([
            'texto' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $mensagem = $pedido->mensagens()->create([
            'user_id' => $user->id,
            'texto' => trim($data['texto']),
        ]);

        $destinatario = $pedido->outraParte($user);

        // Cache::add só grava se a chave não existir: no máximo um e-mail por intervalo.
        if (Cache::add(self::chaveAviso($pedido, $destinatario), true, now()->addMinutes(self::INTERVALO_AVISO_MINUTOS))) {
            $destinatario->notify(new NovaMensagem($mensagem));
        }

        return response()->json($this->formatar($mensagem->load('autor:id,name'), $user->id), 201);
    }

    private static function chaveAviso(Pedido $pedido, User $destinatario): string
    {
        return "aviso-mensagem:{$pedido->id}:{$destinatario->id}";
    }

    private function formatar(Mensagem $mensagem, int $userId): array
    {
        return [
            'id' => $mensagem->id,
            'texto' => $mensagem->texto,
            'created_at' => $mensagem->created_at,
            'lida_em' => $mensagem->lida_em,
            'minha' => $mensagem->user_id === $userId,
            'autor' => $mensagem->autor->name,
        ];
    }
}
