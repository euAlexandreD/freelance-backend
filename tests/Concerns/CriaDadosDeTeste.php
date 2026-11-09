<?php

namespace Tests\Concerns;

use App\Models\CategoriaServico;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/** Atalhos para montar prestadores, clientes, serviços e pedidos nos testes. */
trait CriaDadosDeTeste
{
    private ?CategoriaServico $categoria = null;

    private function categoria(): CategoriaServico
    {
        return $this->categoria ??= CategoriaServico::query()->create(['nome' => 'Design', 'slug' => 'design']);
    }

    private function prestador(string $nome = 'Ana Souza'): PerfilPrestador
    {
        $user = User::factory()->create(['name' => $nome, 'telefone' => '11999999999']);

        return $user->perfilPrestador()->create(['titulo' => 'Designer']);
    }

    private function cliente(string $nome = 'Fernanda Costa'): User
    {
        $user = User::factory()->create(['name' => $nome]);
        $user->perfilCliente()->create();

        return $user;
    }

    private function servico(PerfilPrestador $perfil, array $dados = []): Servico
    {
        return $perfil->servicos()->create([
            'categoria_servico_id' => $this->categoria()->id,
            'titulo' => 'Logotipo profissional',
            'descricao' => 'Criação de logotipo.',
            'tipo_preco' => 'fixo',
            'preco' => 500,
            ...$dados,
        ]);
    }

    /** PNG real de 1x1 pixel: não depende da extensão GD para gerar imagens falsas. */
    private function imagem(string $nome): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $nome,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        );
    }

    private function pedido(User $cliente, Servico $servico, string $status = 'concluido'): Pedido
    {
        return $cliente->perfilCliente->pedidos()->create([
            'servico_id' => $servico->id,
            'status' => $status,
        ]);
    }
}
