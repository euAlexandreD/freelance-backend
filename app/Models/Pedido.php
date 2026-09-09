<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
