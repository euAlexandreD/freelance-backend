<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['perfil_cliente_id', 'servico_id', 'status', 'descricao_pedido'])]
class Pedido extends Model
{
    protected $table = 'pedidos';

    public function perfilCliente(): BelongsTo
    {
        return $this->belongsTo(PerfilCliente::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function avaliacao(): HasOne
    {
        return $this->hasOne(Avaliacao::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class);
    }

    /** Usuário que fez o pedido. */
    public function clienteUser(): User
    {
        return $this->loadMissing('perfilCliente.user')->perfilCliente->user;
    }

    /** Usuário dono do serviço pedido. */
    public function prestadorUser(): User
    {
        return $this->loadMissing('servico.perfilPrestador.user')->servico->perfilPrestador->user;
    }

    /** O cliente e o prestador do pedido são os únicos que conversam nele. */
    public function participa(User $user): bool
    {
        return in_array($user->id, [$this->clienteUser()->id, $this->prestadorUser()->id], true);
    }

    /** Quem está do outro lado da conversa para o usuário informado. */
    public function outraParte(User $user): User
    {
        return $this->clienteUser()->id === $user->id ? $this->prestadorUser() : $this->clienteUser();
    }

    /** Conta as mensagens da outra parte que o usuário ainda não leu (atributo mensagens_nao_lidas). */
    public function scopeComNaoLidas(Builder $query, User $user): void
    {
        $query->withCount(['mensagens as mensagens_nao_lidas' => fn (Builder $q) => $q
            ->where('user_id', '!=', $user->id)
            ->whereNull('lida_em'),
        ]);
    }
}
