<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain\Exception;

/**
 * Estado conflitante, como janelas de promoção sobrepostas ou e-mail repetido (HTTP 409).
 */
class Conflict extends DomainError {}
