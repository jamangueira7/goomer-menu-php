<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

/**
 * Porta de envio de e-mail. A implementação registra falhas de SMTP no log e não lança exceção,
 * para que forgot-password continue respondendo 202.
 */
interface Mailer
{
    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink): void;
}
