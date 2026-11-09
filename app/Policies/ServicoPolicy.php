<?php

namespace App\Policies;

use App\Models\Servico;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServicoPolicy
{
    /** Só o prestador dono do serviço pode alterá-lo, excluí-lo ou mexer nas fotos. */
    public function update(User $user, Servico $servico): Response
    {
        $perfilPrestador = $user->perfilPrestador;

        return $perfilPrestador && $servico->perfil_prestador_id === $perfilPrestador->id
            ? Response::allow()
            : Response::deny('Você não tem permissão para alterar este serviço.');
    }
}
