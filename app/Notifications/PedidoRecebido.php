<?php

namespace App\Notifications;

use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Avisa o prestador que um cliente solicitou um dos seus serviços. */
class PedidoRecebido extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Pedido $pedido)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $servico = $this->pedido->servico;
        $cliente = $this->pedido->clienteUser();

        $mail = (new MailMessage)
            ->subject("Novo pedido: {$servico->titulo}")
            ->greeting('Olá, '.Markdown::texto($notifiable->name).'!')
            ->line('**'.Markdown::texto($cliente->name).'** solicitou o seu serviço **'.Markdown::texto($servico->titulo).'**.');

        if ($this->pedido->descricao_pedido) {
            $mail->line('Mensagem do cliente:')->line('> '.Markdown::texto($this->pedido->descricao_pedido));
        }

        return $mail
            ->action('Ver pedido', Links::pedido($this->pedido, 'prestador'))
            ->line('Responda logo: clientes costumam fechar com quem responde primeiro.');
    }
}
