<?php

namespace App\Notifications;

/**
 * Prepara texto digitado por usuários (nomes, títulos, mensagens) para entrar no Markdown dos e-mails.
 * Sem isso, um nome como "[Clique aqui](https://site-falso)" vira um link de verdade no e-mail.
 */
class Markdown
{
    /**
     * Escapa os caracteres que o Markdown interpreta e junta as linhas.
     * `<`, `>` e `&` ficam de fora: o Blade já converte esses em entidades HTML.
     */
    public static function texto(?string $texto): string
    {
        $umaLinha = preg_replace('/\s+/u', ' ', trim((string) $texto));

        return preg_replace('/([\\\\`*_\[\]()#!|~+\-])/', '\\\\$1', $umaLinha);
    }
}
