<?php

namespace Database\Seeders;

use App\Models\CategoriaServico;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriaServicoSeeder extends Seeder
{
    public const CATEGORIAS = [
        'Desenvolvimento Web',
        'Design Gráfico',
        'Redação e Tradução',
        'Marketing Digital',
        'Fotografia e Vídeo',
        'Consultoria',
        'Aulas Particulares',
        'Reformas e Manutenção',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIAS as $nome) {
            CategoriaServico::query()->updateOrCreate(
                ['slug' => Str::slug($nome)],
                ['nome' => $nome],
            );
        }
    }
}
