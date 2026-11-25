<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain\Exception;

/**
 * Recurso inexistente ou de outro restaurante (HTTP 404, para não revelar que existe).
 */
class NotFound extends DomainError {}
