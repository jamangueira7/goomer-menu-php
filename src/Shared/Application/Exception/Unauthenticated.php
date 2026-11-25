<?php

declare(strict_types=1);

namespace Goomer\Shared\Application\Exception;

/**
 * Credenciais ou token inválidos, ausentes ou vencidos (HTTP 401).
 */
final class Unauthenticated extends \RuntimeException {}
