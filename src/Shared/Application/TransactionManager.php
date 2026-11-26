<?php

declare(strict_types=1);

namespace Goomer\Shared\Application;

/**
 * Unidade de trabalho: tudo o que roda dentro de `run` é gravado junto ou desfeito junto.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;
}
