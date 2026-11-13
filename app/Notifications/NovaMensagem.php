<?php

namespace App\Notifications;

use App\Models\Mensagem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Avisa sobre uma mensagem nova na conversa do pedido.
 * Sai com alguns minutos de atraso e é descartada se a mensagem já tiver sido lida,
 * para não mandar e-mail a quem está conversando com a tela aberta.
 */
class NovaMensagem extends Notification implements ShouldQueue
{
    use Queueable;

    public const ATRASO_MINUTOS = 2;

    public function __construct(public Mensagem $mensagem)
    {
        $this->afterCommit();
        $this->delay(now()->addMinutes(self::ATRASO_MINUTOS));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->mensagem->fresh()?->lida_em === null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pedido = $this->mensagem->pedido;
        $autor = $this->mensagem->autor->name;
        $autorMd = Markdown::texto($autor);
        $papel = $pedido->clienteUser()->id === $notifiable->id ? 'cliente' : 'prestador';

        return (new MailMessage)
            ->subject("Nova mensagem de {$autor}")
            ->greeting('Olá, '.Markdown::texto($notifiable->name).'!')
            ->line("**{$autorMd}** enviou uma mensagem sobre **".Markdown::texto($pedido->servico->titulo).'**:')
            ->line('> '.Markdown::texto(Str::limit($this->mensagem->texto, 300)))
            ->action('Responder', Links::pedido($pedido, $papel));
    }
}
