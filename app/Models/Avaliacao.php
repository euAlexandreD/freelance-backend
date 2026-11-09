<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pedido_id', 'servico_id', 'perfil_prestador_id', 'nota', 'comentario'])]
class Avaliacao extends Model
{
    protected $table = 'avaliacoes';

    protected function casts(): array
    {
        return [
            'nota' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function perfilPrestador(): BelongsTo
    {
        return $this->belongsTo(PerfilPrestador::class);
    }
}
