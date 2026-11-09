<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['perfil_prestador_id', 'categoria_servico_id', 'titulo', 'descricao', 'tipo_preco', 'preco', 'ativo'])]
class Servico extends Model
{
    public const TIPOS_PRECO = ['fixo', 'a_partir_de', 'por_hora', 'a_combinar'];

    public const MAX_FOTOS = 5;

    /** Dados do prestador que podem aparecer em telas públicas (sem e-mail e telefone). */
    public const PRESTADOR_PUBLICO = 'perfilPrestador.user:id,name,avatar_path,cidade,uf';

    protected $table = 'servicos';

    protected static function booted(): void
    {
        // Apaga as fotos uma a uma para os arquivos também saírem do disco.
        static::deleting(fn (Servico $servico) => $servico->fotos->each->delete());
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'preco' => 'decimal:2',
        ];
    }

    /** Carrega o que as listagens precisam: categoria, prestador, fotos e nota média. */
    public function scopeComDetalhes(Builder $query): void
    {
        $query->with(['categoriaServico', self::PRESTADOR_PUBLICO, 'fotos'])
            ->withAvg('avaliacoes as nota_media', 'nota')
            ->withCount('avaliacoes');
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

    public function fotos(): HasMany
    {
        return $this->hasMany(ServicoFoto::class)->orderBy('ordem')->orderBy('id');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class);
    }
}
