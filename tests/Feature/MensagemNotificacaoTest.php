<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\NovaMensagem;
use App\Notifications\PedidoAtualizado;
use App\Notifications\PedidoRecebido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CriaDadosDeTeste;
use Tests\TestCase;

class MensagemNotificacaoTest extends TestCase
{
    use CriaDadosDeTeste;
    use RefreshDatabase;

    private User $cliente;

    private User $prestador;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $perfil = $this->prestador();
        $this->prestador = $perfil->user;
        $this->cliente = $this->cliente();
        $this->pedido = $this->pedido($this->cliente, $this->servico($perfil), 'aceito');
    }

    public function test_cliente_e_prestador_conversam_no_pedido(): void
    {
        Sanctum::actingAs($this->cliente);
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Pode ser na terça?'])
            ->assertCreated()
            ->assertJsonPath('minha', true)
            ->assertJsonPath('autor', 'Fernanda Costa');

        Sanctum::actingAs($this->prestador);
        $this->getJson("/api/pedidos/{$this->pedido->id}/mensagens")
            ->assertOk()
            ->assertJsonPath('pode_enviar', true)
            ->assertJsonCount(1, 'mensagens')
            ->assertJsonPath('mensagens.0.texto', 'Pode ser na terça?')
            ->assertJsonPath('mensagens.0.minha', false);
    }

    public function test_quem_nao_participa_nao_le_nem_envia(): void
    {
        Sanctum::actingAs($this->cliente('Gabriel Martins'));

        $this->getJson("/api/pedidos/{$this->pedido->id}/mensagens")->assertForbidden();
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Oi'])->assertForbidden();
    }

    public function test_pedido_encerrado_nao_recebe_mensagens(): void
    {
        $this->pedido->update(['status' => 'cancelado']);
        Sanctum::actingAs($this->cliente);

        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Oi'])->assertUnprocessable();
        $this->getJson("/api/pedidos/{$this->pedido->id}/mensagens")->assertJsonPath('pode_enviar', false);
    }

    public function test_texto_obrigatorio_e_limitado(): void
    {
        Sanctum::actingAs($this->cliente);

        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => ''])
            ->assertJsonValidationErrors('texto');
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => str_repeat('a', 2001)])
            ->assertJsonValidationErrors('texto');
    }

    public function test_nao_lidas_aparecem_na_lista_e_zeram_ao_abrir_a_conversa(): void
    {
        Sanctum::actingAs($this->cliente);
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Primeira']);
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Segunda']);

        // As próprias mensagens não contam como não lidas para quem enviou.
        $this->getJson('/api/meus-pedidos')->assertJsonPath('0.mensagens_nao_lidas', 0);

        Sanctum::actingAs($this->prestador);
        $this->getJson('/api/pedidos-recebidos')->assertJsonPath('0.mensagens_nao_lidas', 2);
        $this->getJson("/api/pedidos/{$this->pedido->id}/mensagens");
        $this->getJson('/api/pedidos-recebidos')->assertJsonPath('0.mensagens_nao_lidas', 0);
    }

    public function test_depois_de_devolve_so_as_novas(): void
    {
        Sanctum::actingAs($this->cliente);
        $primeira = $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Primeira'])->json('id');
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Segunda']);

        $this->getJson("/api/pedidos/{$this->pedido->id}/mensagens?depois_de={$primeira}")
            ->assertJsonCount(1, 'mensagens')
            ->assertJsonPath('mensagens.0.texto', 'Segunda');
    }

    public function test_aviso_de_mensagem_sai_no_maximo_uma_vez_por_intervalo(): void
    {
        Sanctum::actingAs($this->cliente);
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Primeira']);
        $this->postJson("/api/pedidos/{$this->pedido->id}/mensagens", ['texto' => 'Segunda']);

        Notification::assertSentToTimes($this->prestador, NovaMensagem::class, 1);
        Notification::assertNotSentTo($this->cliente, NovaMensagem::class);
    }

    public function test_aviso_de_mensagem_e_descartado_se_ja_foi_lida(): void
    {
        $mensagem = $this->pedido->mensagens()->create(['user_id' => $this->cliente->id, 'texto' => 'Oi']);
        $notificacao = new NovaMensagem($mensagem);

        $this->assertTrue($notificacao->shouldSend($this->prestador, 'mail'));

        $mensagem->update(['lida_em' => now()]);
        $this->assertFalse($notificacao->shouldSend($this->prestador, 'mail'));
    }

    public function test_prestador_e_avisado_de_pedido_novo(): void
    {
        $servico = $this->pedido->servico;
        $outroCliente = $this->cliente('Helena Ribeiro');
        Sanctum::actingAs($outroCliente);

        $this->postJson('/api/pedidos', ['servico_id' => $servico->id, 'descricao_pedido' => 'Preciso para sexta'])
            ->assertSuccessful();

        Notification::assertSentTo($this->prestador, PedidoRecebido::class, function (PedidoRecebido $n) {
            $mail = $n->toMail($this->prestador);

            return $mail->subject === 'Novo pedido: Logotipo profissional'
                && str_contains($mail->actionUrl, '/pedidos-recebidos?pedido=');
        });
    }

    public function test_cliente_e_avisado_quando_o_pedido_e_concluido(): void
    {
        Sanctum::actingAs($this->prestador);
        $this->putJson("/api/pedidos/{$this->pedido->id}/concluir")->assertOk();

        Notification::assertSentTo($this->cliente, PedidoAtualizado::class, function (PedidoAtualizado $n) {
            $mail = $n->toMail($this->cliente);

            return $n->status === 'concluido'
                && $mail->actionText === 'Avaliar serviço'
                && str_contains($mail->actionUrl, "/meus-pedidos?pedido={$this->pedido->id}");
        });
    }

    public function test_prestador_e_avisado_quando_o_cliente_cancela(): void
    {
        Sanctum::actingAs($this->cliente);
        $this->putJson("/api/pedidos/{$this->pedido->id}/cancelar")->assertOk();

        Notification::assertSentTo($this->prestador, PedidoAtualizado::class, fn ($n) => $n->status === 'cancelado');
        Notification::assertNotSentTo($this->cliente, PedidoAtualizado::class);
    }

    public function test_email_sai_em_portugues(): void
    {
        // No envio real, o Laravel aplica o idioma de preferredLocale() do destinatário.
        $this->assertSame('pt_BR', $this->prestador->preferredLocale());

        App::setLocale($this->prestador->preferredLocale());
        $html = (string) (new PedidoRecebido($this->pedido))->toMail($this->prestador)->render();

        $this->assertStringContainsString('Olá, Ana Souza!', $html);
        $this->assertStringContainsString('Até logo,', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }
}
