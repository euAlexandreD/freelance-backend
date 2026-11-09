<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PedidoPolicy
{
    /** Ler a conversa do pedido: só o cliente e o prestador envolvidos. */
    public function conversar(User $user, Pedido $pedido): Response
    {
        return $pedido->participa($user)
            ? Response::allow()
            : Response::deny('Você não participa deste pedido.');
    }
}
