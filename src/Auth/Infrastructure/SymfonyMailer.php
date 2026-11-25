<?php

declare(strict_types=1);

namespace Goomer\Auth\Infrastructure;

use Goomer\Auth\Application\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envia pelo symfony/mailer. Falha de SMTP vai para o log e não interrompe a resposta 202.
 */
final readonly class SymfonyMailer implements Mailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $from,
    ) {}

    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink): void
    {
        $email = (new Email())
            ->from($this->from)
            ->to(new Address($toEmail, $toName))
            ->subject('Redefinição de senha - Goomer Menu')
            ->text(sprintf(
                "Olá, %s.\n\nPara criar uma nova senha, use o link abaixo (vale 30 minutos e uma única vez):\n\n%s\n\n"
                . "Se você não pediu a redefinição, ignore este e-mail.\n",
                $toName,
                $resetLink,
            ));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            error_log(sprintf('[mailer] falha ao enviar redefinição de senha para %s: %s', $toEmail, $e->getMessage()));
        }
    }
}
