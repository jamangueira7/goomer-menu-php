<?php

declare(strict_types=1);

namespace Goomer\Tests\Integration;

use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Product\Infrastructure\PdoProductRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\AuditEntry;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Domain\DateRange;
use Goomer\Shared\Domain\Timezone;
use Goomer\Shared\Infrastructure\Audit\PdoAuditLog;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\PdoTransactionManager;
use Goomer\Tests\Support\FixedClock;
use Goomer\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

final class AuditLogRepositoryTest extends TestCase
{
    private Database $db;
    private PdoAuditLog $log;

    protected function setUp(): void
    {
        $this->db = TestDatabase::reset();
        $this->log = new PdoAuditLog($this->db);
    }

    private function entry(string $at, int $actor = 7, int $restaurant = 1, string $email = 'ana@bar.com'): AuditEntry
    {
        return AuditEntry::by(
            new Actor($actor, $email, $restaurant, '172.18.0.1'),
            'product.updated',
            'product',
            42,
            ['before' => ['price' => 1400], 'after' => ['price' => 1900, 'name' => 'Chopp "gelado"']],
            new \DateTimeImmutable($at),
        );
    }

    private function saoPaulo(string $start, ?string $end): DateRange
    {
        return DateRange::ofLocalDays($start, $end, Timezone::fromString('America/Sao_Paulo'), new \DateTimeImmutable('2026-11-27T12:00:00Z'));
    }

    public function test_round_trip_keeps_changes_ip_and_instant(): void
    {
        $this->log->record($this->entry('2026-11-25T21:31:04.250Z'));
        $this->log->record(AuditEntry::by(new Actor(7, 'ana@bar.com', 1), 'product.reordered', 'product', null, [], new \DateTimeImmutable('2026-11-25T21:32:00Z')));

        $result = $this->log->ofActor(1, 7, $this->saoPaulo('2026-11-25', null), 1, 50);
        self::assertSame(2, $result['total']);
        [$reordered, $updated] = $result['items'];

        self::assertNull($reordered->entityId);
        self::assertNull($reordered->ip);
        self::assertSame('{}', $reordered->changesJson());

        self::assertIsInt($updated->id);
        self::assertSame(['product.updated', 'product', 42, '172.18.0.1', 'ana@bar.com'], [$updated->action, $updated->entityType, $updated->entityId, $updated->ip, $updated->actorEmail]);
        self::assertEquals(['before' => ['price' => 1400], 'after' => ['price' => 1900, 'name' => 'Chopp "gelado"']], $updated->changes);
        self::assertSame('2026-11-25T21:31:04.250', $updated->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v'));
    }

    public function test_period_is_filtered_in_restaurant_timezone(): void
    {
        $this->log->record($this->entry('2026-11-25T02:59:59Z')); // 24/11 23:59:59 em São Paulo: fora
        $this->log->record($this->entry('2026-11-25T03:00:00Z')); // 25/11 00:00: dentro
        $this->log->record($this->entry('2026-11-27T02:59:59Z')); // 26/11 23:59:59: dentro
        $this->log->record($this->entry('2026-11-27T03:00:00Z')); // 27/11 00:00: fora
        $this->log->record($this->entry('2026-11-25T12:00:00Z', actor: 8)); // outro ator
        $this->log->record($this->entry('2026-11-25T12:00:00Z', restaurant: 2)); // outro restaurante

        $result = $this->log->ofActor(1, 7, $this->saoPaulo('2026-11-25', '2026-11-26'), 1, 50);
        self::assertSame(2, $result['total']);
        self::assertSame(
            ['2026-11-27T02:59:59Z', '2026-11-25T03:00:00Z'],
            array_map(static fn(AuditEntry $e): string => $e->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), $result['items']),
            'mais recentes primeiro',
        );

        $page = $this->log->ofActor(1, 7, $this->saoPaulo('2026-11-24', null), 2, 2);
        self::assertSame(4, $page['total']);
        self::assertCount(2, $page['items']);
    }

    public function test_last_email_of_actor(): void
    {
        self::assertNull($this->log->lastEmailOf(1, 7));
        $this->log->record($this->entry('2026-11-25T10:00:00Z'));
        $this->log->record($this->entry('2026-11-25T11:00:00Z', email: 'ana.nova@bar.com'));

        self::assertSame('ana.nova@bar.com', $this->log->lastEmailOf(1, 7));
        self::assertNull($this->log->lastEmailOf(2, 7));
    }

    public function test_write_is_rolled_back_when_log_insert_fails(): void
    {
        $products = new PdoProductRepository($this->db);
        $create = new CreateProductUseCase(
            $products,
            new PdoTransactionManager($this->db),
            new Auditor($this->log, new FixedClock()),
        );

        $create->execute(new Actor(7, 'ana@bar.com', 1, '10.0.0.1'), new ProductData('Chopp', 1400, 'bebidas'));
        self::assertSame(1, $this->db->fetchInt('SELECT count(*) FROM products'));
        self::assertSame(1, $this->db->fetchInt('SELECT count(*) FROM audit_logs'));

        try {
            // IP fora do formato: o INSERT do log falha no cast para inet, depois de o produto já ter sido gravado.
            $create->execute(new Actor(7, 'ana@bar.com', 1, 'não-é-ip'), new ProductData('Água', 500, 'bebidas'));
            self::fail('Era para o log falhar');
        } catch (\PDOException) {
            self::assertSame(1, $this->db->fetchInt('SELECT count(*) FROM products'), 'produto desfeito junto');
            self::assertSame(1, $this->db->fetchInt('SELECT count(*) FROM audit_logs'));
        }
    }
}
