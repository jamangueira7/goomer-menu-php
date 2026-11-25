<?php

declare(strict_types=1);

/*
 * Lê o clover.xml do PHPUnit e confere a meta de cobertura de linhas em Domain e Application.
 * Uso: php bin/coverage-check.php coverage/clover.xml [mínimo=90]
 */

$file = $argv[1] ?? 'coverage/clover.xml';
$minimum = (float) ($argv[2] ?? 90);
$xml = @simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Não consegui ler {$file}.\n");
    exit(1);
}

$totals = ['Domain' => [0, 0], 'Application' => [0, 0]];
foreach ($xml->xpath('//file') ?: [] as $node) {
    $name = (string) $node['name'];
    foreach (array_keys($totals) as $layer) {
        if (str_contains($name, "/{$layer}/")) {
            $metrics = $node->metrics;
            $totals[$layer][0] += (int) $metrics['coveredstatements'];
            $totals[$layer][1] += (int) $metrics['statements'];
        }
    }
}

$failed = false;
foreach ($totals as $layer => [$covered, $total]) {
    $percent = $total === 0 ? 100.0 : 100 * $covered / $total;
    printf("%-12s %6.2f%% (%d/%d linhas)\n", $layer, $percent, $covered, $total);
    $failed = $failed || $percent < $minimum;
}

exit($failed ? 1 : 0);
