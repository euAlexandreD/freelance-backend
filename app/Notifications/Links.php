<?php

namespace App\Notifications;

use App\Models\Pedido;

/** Monta os links do frontend usados nos e-mails. */
class Links
{
    public static function frontend(string $caminho): string
    {
        return rtrim(config('app.frontend_url'), '/').$caminho;
    }

    /** Abre o pedido (e sua conversa) na tela certa para cada lado. */
    public static function pedido(Pedido $pedido, string $papel): string
    {
        $tela = $papel === 'cliente' ? '/meus-pedidos' : '/pedidos-recebidos';

        return self::frontend("{$tela}?pedido={$pedido->id}");
    }
}
