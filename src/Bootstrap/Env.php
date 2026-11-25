<?php

declare(strict_types=1);

namespace Goomer\Bootstrap;

/**
 * Leitura das variáveis de ambiente (12-factor; os segredos nunca vão para o Git).
 */
final class Env
{
    public static function get(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public static function required(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Variável de ambiente %s não definida.', $name));
        }

        return $value;
    }
}
