<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain\Exception;

/**
 * Base de todos os erros de regra de negócio. A camada HTTP traduz cada subtipo num status.
 */
abstract class DomainError extends \DomainException {}
