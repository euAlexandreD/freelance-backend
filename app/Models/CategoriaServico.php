<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'slug'])]
class CategoriaServico extends Model
{
    protected $table = 'categoria_servicos';

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class);
    }
}
