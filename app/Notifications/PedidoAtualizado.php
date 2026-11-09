<?php

namespace App\Notifications;

use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a outra parte quando o status do pedido muda:
 * aceito, recusado e concluído vão para o cliente; cancelado vai para o prestador.
 */
class PedidoAtualizado extends Notification implements ShouldQueue
{
    use Queueable;

    /** Guarda o status do momento da mudança, porque o pedido pode mudar de novo antes do envio. */
    public string $status;

    public function __construct(public Pedido $pedido)
    {
        $this->status = $pedido->status;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $titulo = $this->pedido->servico->titulo;
        $prestador = $this->pedido->prestadorUser()->name;
        $cliente = $this->pedido->clienteUser()->name;

        $mail = (new MailMessage)->greeting("Olá, {$notifiable->name}!");

        return match ($this->status) {
            'aceito' => $mail
                ->subject("Pedido aceito: {$titulo}")
                ->line("**{$prestador}** aceitou o seu pedido de **{$titulo}**.")
                ->line('Combine os detalhes pela conversa do pedido.')
                ->action('Abrir conversa', Links::pedido($this->pedido, 'cliente')),
            'recusado' => $mail
                ->subject("Pedido recusado: {$titulo}")
                ->line("**{$prestador}** não pode atender o seu pedido de **{$titulo}** agora.")
                ->action('Ver outros serviços', Links::frontend('/servicos')),
            'concluido' => $mail
                ->subject("Pedido concluído: {$titulo}")
                ->line("**{$prestador}** marcou o seu pedido de **{$titulo}** como concluído.")
                ->line('Conte como foi: sua avaliação ajuda outros clientes a escolher.')
                ->action('Avaliar serviço', Links::pedido($this->pedido, 'cliente')),
            'cancelado' => $mail
                ->subject("Pedido cancelado: {$titulo}")
                ->line("**{$cliente}** cancelou o pedido de **{$titulo}**.")
                ->action('Ver pedidos recebidos', Links::pedido($this->pedido, 'prestador')),
            default => $mail
                ->subject("Pedido atualizado: {$titulo}")
                ->action('Ver pedido', Links::frontend('/')),
        };
    }
}
