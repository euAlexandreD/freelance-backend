<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['perfil_prestador_id', 'categoria_servico_id', 'descricao', 'ativo'])]
class Servico extends Model
{
    protected $table = 'servicos';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function perfilPrestador(): BelongsTo
    {
        return $this->belongsTo(PerfilPrestador::class);
    }

    public function categoriaServico(): BelongsTo
    {
        return $this->belongsTo(CategoriaServico::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
