<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\NovaMensagem;
use App\Notifications\PedidoRecebido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaDadosDeTeste;
use Tests\TestCase;

/** Correções de segurança e de bugs encontradas na revisão de novembro/2026. */
class SegurancaTest extends TestCase
{
    use CriaDadosDeTeste;
    use RefreshDatabase;

    private function dadosCadastro(array $extra = []): array
    {
        return [
            'name' => 'Nova Pessoa',
            'email' => 'nova@example.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
            ...$extra,
        ];
    }

    public function test_cadastro_cria_perfil_de_cliente_por_padrao(): void
    {
        $this->postJson('/api/register', $this->dadosCadastro())->assertCreated();

        $user = User::where('email', 'nova@example.com')->firstOrFail();
        $this->assertNotNull($user->perfilCliente);
        $this->assertNull($user->perfilPrestador);
    }

    public function test_cadastro_como_prestador_cria_perfil_de_prestador(): void
    {
        $this->postJson('/api/register', $this->dadosCadastro(['tipo' => 'prestador']))->assertCreated();

        $this->assertNotNull(User::where('email', 'nova@example.com')->firstOrFail()->perfilPrestador);
    }

    public function test_conta_nova_ja_consegue_pedir_servico(): void
    {
        Notification::fake();
        $servico = $this->servico($this->prestador());

        $token = $this->postJson('/api/register', $this->dadosCadastro())->json('token');

        $this->withToken($token)
            ->postJson('/api/pedidos', ['servico_id' => $servico->id])
            ->assertSuccessful();
    }

    public function test_login_tem_limite_de_tentativas(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'ana@example.com', 'password' => 'errada'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'ana@example.com', 'password' => 'errada'])
            ->assertTooManyRequests();
    }

    public function test_logout_invalida_o_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_link_externo_aceita_so_http_e_https(): void
    {
        $perfil = $this->prestador();
        Sanctum::actingAs($perfil->user);

        $base = ['name' => 'Ana Souza', 'email' => $perfil->user->email, 'tipo' => 'prestador'];

        foreach (['javascript:alert(1)', 'data://text/html,<script>alert(1)</script>', 'ftp://exemplo.com'] as $link) {
            $this->putJson('/api/profile', [...$base, 'link_externo' => $link])
                ->assertJsonValidationErrors('link_externo');
        }

        $this->putJson('/api/profile', [...$base, 'link_externo' => 'https://exemplo.com'])->assertOk();
    }

    public function test_pedido_nao_e_aceito_duas_vezes(): void
    {
        Notification::fake();
        $perfil = $this->prestador();
        $pedido = $this->pedido($this->cliente(), $this->servico($perfil), 'pendente');
        Sanctum::actingAs($perfil->user);

        $this->putJson("/api/pedidos/{$pedido->id}/aceitar")->assertOk()->assertJsonPath('status', 'aceito');
        $this->putJson("/api/pedidos/{$pedido->id}/recusar")->assertUnprocessable();

        $this->assertSame('aceito', $pedido->fresh()->status);
    }

    public function test_preco_nao_pode_ser_apagado_de_servico_com_preco_fixo(): void
    {
        $perfil = $this->prestador();
        $servico = $this->servico($perfil);
        Sanctum::actingAs($perfil->user);

        $this->putJson("/api/servicos/{$servico->id}", ['preco' => null])
            ->assertJsonValidationErrors('preco');
    }

    public function test_texto_de_usuario_nao_vira_link_no_email(): void
    {
        Notification::fake();
        $servico = $this->servico($this->prestador());
        $cliente = $this->cliente('[Clique aqui](https://site-falso.example)');
        Sanctum::actingAs($cliente);

        $this->postJson('/api/pedidos', [
            'servico_id' => $servico->id,
            'descricao_pedido' => "Oi\n[Pague aqui](https://golpe.example)",
        ])->assertSuccessful();

        Notification::assertSentTo(
            $servico->perfilPrestador->user,
            PedidoRecebido::class,
            function (PedidoRecebido $notificacao, array $canais, User $prestador) {
                $html = (string) $notificacao->toMail($prestador)->render();

                return ! str_contains($html, 'href="https://site-falso.example"')
                    && ! str_contains($html, 'href="https://golpe.example"')
                    && str_contains($html, '[Clique aqui]');
            },
        );
    }

    public function test_quem_leu_a_conversa_volta_a_receber_aviso_de_mensagem_nova(): void
    {
        Notification::fake();
        $perfil = $this->prestador();
        $cliente = $this->cliente();
        $pedido = $this->pedido($cliente, $this->servico($perfil), 'aceito');

        Sanctum::actingAs($cliente);
        $this->postJson("/api/pedidos/{$pedido->id}/mensagens", ['texto' => 'Primeira'])->assertCreated();

        Sanctum::actingAs($perfil->user);
        $this->getJson("/api/pedidos/{$pedido->id}/mensagens")->assertOk();

        Sanctum::actingAs($cliente);
        $this->postJson("/api/pedidos/{$pedido->id}/mensagens", ['texto' => 'Segunda'])->assertCreated();

        Notification::assertSentToTimes($perfil->user, NovaMensagem::class, 2);
    }

    public function test_cors_libera_so_o_frontend(): void
    {
        // Com uma origem só, o cabeçalho sempre traz o frontend; o navegador bloqueia qualquer outro site.
        $resposta = $this->withHeaders(['Origin' => 'https://site-malicioso.example'])
            ->getJson('/api/categorias-servico');
        $this->assertNotSame('https://site-malicioso.example', $resposta->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $resposta->headers->get('Access-Control-Allow-Origin'));

        $this->withHeaders(['Origin' => config('app.frontend_url')])
            ->getJson('/api/categorias-servico')
            ->assertHeader('Access-Control-Allow-Origin', config('app.frontend_url'));
    }
}
