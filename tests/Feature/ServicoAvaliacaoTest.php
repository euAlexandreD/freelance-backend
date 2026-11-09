<?php

namespace Tests\Feature;

use App\Models\CategoriaServico;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicoAvaliacaoTest extends TestCase
{
    use RefreshDatabase;

    private CategoriaServico $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoria = CategoriaServico::query()->create(['nome' => 'Design', 'slug' => 'design']);
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
            'categoria_servico_id' => $this->categoria->id,
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

    public function test_prestador_cadastra_servico_com_titulo_e_preco(): void
    {
        $perfil = $this->prestador();
        Sanctum::actingAs($perfil->user);

        $this->postJson('/api/servicos', [
            'categoria_servico_id' => $this->categoria->id,
            'titulo' => 'Logotipo profissional',
            'descricao' => 'Criação de logotipo.',
            'tipo_preco' => 'a_partir_de',
            'preco' => 350.5,
        ])
            ->assertCreated()
            ->assertJsonPath('titulo', 'Logotipo profissional')
            ->assertJsonPath('tipo_preco', 'a_partir_de')
            ->assertJsonPath('preco', '350.50');
    }

    public function test_preco_e_obrigatorio_exceto_a_combinar(): void
    {
        $perfil = $this->prestador();
        Sanctum::actingAs($perfil->user);

        $base = [
            'categoria_servico_id' => $this->categoria->id,
            'titulo' => 'Logotipo profissional',
            'descricao' => 'Criação de logotipo.',
        ];

        $this->postJson('/api/servicos', [...$base, 'tipo_preco' => 'fixo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preco');

        $this->postJson('/api/servicos', [...$base, 'tipo_preco' => 'a_combinar', 'preco' => 999])
            ->assertCreated()
            ->assertJsonPath('preco', null);
    }

    public function test_titulo_e_obrigatorio(): void
    {
        $perfil = $this->prestador();
        Sanctum::actingAs($perfil->user);

        $this->postJson('/api/servicos', [
            'categoria_servico_id' => $this->categoria->id,
            'descricao' => 'Criação de logotipo.',
            'tipo_preco' => 'a_combinar',
        ])->assertJsonValidationErrors('titulo');
    }

    public function test_outro_prestador_nao_altera_servico(): void
    {
        $servico = $this->servico($this->prestador());
        Sanctum::actingAs($this->prestador('Bruno Lima')->user);

        $this->putJson("/api/servicos/{$servico->id}", ['titulo' => 'Título alterado'])->assertForbidden();
    }

    public function test_pausar_servico_nao_exige_preco(): void
    {
        $perfil = $this->prestador();
        $servico = $this->servico($perfil);
        Sanctum::actingAs($perfil->user);

        $this->putJson("/api/servicos/{$servico->id}", ['ativo' => false])
            ->assertOk()
            ->assertJsonPath('ativo', false)
            ->assertJsonPath('preco', '500.00');
    }

    public function test_servico_com_pedidos_nao_pode_ser_excluido(): void
    {
        $perfil = $this->prestador();
        $servico = $this->servico($perfil);
        $this->pedido($this->cliente(), $servico, 'pendente');
        Sanctum::actingAs($perfil->user);

        $this->deleteJson("/api/servicos/{$servico->id}")->assertUnprocessable();
        $this->assertModelExists($servico);
    }

    public function test_upload_e_remocao_de_fotos(): void
    {
        Storage::fake('public');
        $perfil = $this->prestador();
        $servico = $this->servico($perfil);
        Sanctum::actingAs($perfil->user);

        $resposta = $this->post("/api/servicos/{$servico->id}/fotos", [
            'fotos' => [$this->imagem('a.png'), $this->imagem('b.png')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(2);

        $foto = $servico->fotos()->first();
        Storage::disk('public')->assertExists($foto->path);
        $this->assertStringContainsString('/storage/servicos/', $resposta->json('0.url'));

        $this->deleteJson("/api/servico-fotos/{$foto->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($foto->path);
    }

    public function test_limite_de_cinco_fotos(): void
    {
        Storage::fake('public');
        $perfil = $this->prestador();
        $servico = $this->servico($perfil);
        Sanctum::actingAs($perfil->user);

        $fotos = array_map(fn ($i) => $this->imagem("{$i}.png"), range(1, 6));

        $this->post("/api/servicos/{$servico->id}/fotos", ['fotos' => $fotos], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fotos');
    }

    public function test_outro_usuario_nao_envia_fotos(): void
    {
        Storage::fake('public');
        $servico = $this->servico($this->prestador());
        Sanctum::actingAs($this->prestador('Bruno Lima')->user);

        $this->post("/api/servicos/{$servico->id}/fotos", [
            'fotos' => [$this->imagem('a.png')],
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_cliente_avalia_pedido_concluido_uma_vez(): void
    {
        $servico = $this->servico($this->prestador());
        $cliente = $this->cliente();
        $pedido = $this->pedido($cliente, $servico);
        Sanctum::actingAs($cliente);

        $this->postJson("/api/pedidos/{$pedido->id}/avaliacao", ['nota' => 5, 'comentario' => 'Ótimo!'])
            ->assertCreated()
            ->assertJsonPath('nota', 5);

        $this->postJson("/api/pedidos/{$pedido->id}/avaliacao", ['nota' => 1])->assertUnprocessable();
    }

    public function test_nao_avalia_pedido_nao_concluido(): void
    {
        $servico = $this->servico($this->prestador());
        $cliente = $this->cliente();
        $pedido = $this->pedido($cliente, $servico, 'aceito');
        Sanctum::actingAs($cliente);

        $this->postJson("/api/pedidos/{$pedido->id}/avaliacao", ['nota' => 5])->assertUnprocessable();
    }

    public function test_nao_avalia_pedido_de_outro_cliente(): void
    {
        $servico = $this->servico($this->prestador());
        $pedido = $this->pedido($this->cliente(), $servico);
        Sanctum::actingAs($this->cliente('Gabriel Martins'));

        $this->postJson("/api/pedidos/{$pedido->id}/avaliacao", ['nota' => 5])->assertForbidden();
    }

    public function test_nota_deve_ser_de_1_a_5(): void
    {
        $servico = $this->servico($this->prestador());
        $cliente = $this->cliente();
        $pedido = $this->pedido($cliente, $servico);
        Sanctum::actingAs($cliente);

        $this->postJson("/api/pedidos/{$pedido->id}/avaliacao", ['nota' => 6])
            ->assertJsonValidationErrors('nota');
    }

    public function test_catalogo_mostra_nota_media_e_esconde_contato_do_prestador(): void
    {
        $servico = $this->servico($this->prestador());
        foreach ([5, 4] as $nota) {
            $pedido = $this->pedido($this->cliente(), $servico);
            $pedido->avaliacao()->create([
                'servico_id' => $servico->id,
                'perfil_prestador_id' => $servico->perfil_prestador_id,
                'nota' => $nota,
            ]);
        }

        $item = $this->getJson('/api/servicos')->assertOk()->json('0');

        $this->assertEquals(4.5, $item['nota_media']);
        $this->assertSame(2, $item['avaliacoes_count']);
        $this->assertArrayNotHasKey('email', $item['perfil_prestador']['user']);
        $this->assertArrayNotHasKey('telefone', $item['perfil_prestador']['user']);
    }

    public function test_perfil_publico_do_prestador(): void
    {
        $perfil = $this->prestador();
        $servico = $this->servico($perfil);
        $this->servico($perfil, ['titulo' => 'Serviço pausado', 'ativo' => false]);
        $pedido = $this->pedido($this->cliente('Fernanda Costa'), $servico);
        $pedido->avaliacao()->create([
            'servico_id' => $servico->id,
            'perfil_prestador_id' => $perfil->id,
            'nota' => 4,
            'comentario' => 'Muito bom.',
        ]);

        $this->getJson("/api/prestadores/{$perfil->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'Ana Souza')
            ->assertJsonMissingPath('user.email')
            ->assertJsonPath('avaliacoes_count', 1)
            ->assertJsonPath('pedidos_concluidos', 1)
            ->assertJsonCount(1, 'servicos')
            ->assertJsonPath('avaliacoes.0.cliente', 'Fernanda C.')
            ->assertJsonPath('avaliacoes.0.servico.titulo', 'Logotipo profissional');
    }
}
