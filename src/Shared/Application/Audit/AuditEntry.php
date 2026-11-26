<?php

declare(strict_types=1);

namespace Goomer\Shared\Application\Audit;

/**
 * Uma linha do log de auditoria: o que foi feito (ação, entidade, de/para), quem fez e quando.
 * Nunca leva senha, hash, token ou JWT.
 */
final readonly class AuditEntry
{
    /**
     * @param array<string, mixed> $changes
     */
    public function __construct(
        public int $restaurantId,
        public int $actorUserId,
        public string $actorEmail,
        public string $action,
        public string $entityType,
        public ?int $entityId,
        public array $changes,
        public ?string $ip,
        public \DateTimeImmutable $createdAt,
        public ?int $id = null,
    ) {}

    /**
     * @param array<string, mixed> $changes
     */
    public static function by(
        Actor $actor,
        string $action,
        string $entityType,
        ?int $entityId,
        array $changes,
        \DateTimeImmutable $at,
    ): self {
        return new self(
            $actor->restaurantId,
            $actor->userId,
            $actor->email,
            $action,
            $entityType,
            $entityId,
            $changes,
            $actor->ip,
            $at,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $this->restaurantId,
            $this->actorUserId,
            $this->actorEmail,
            $this->action,
            $this->entityType,
            $this->entityId,
            $this->changes,
            $this->ip,
            $this->createdAt,
            $id,
        );
    }

    /**
     * Os de/para como objeto JSON: `before` antes de `after` (o jsonb reordena as chaves) e `{}` (não `[]`)
     * quando vazios.
     */
    public function changesAsObject(): \stdClass
    {
        $object = new \stdClass();
        $ordered = array_merge(array_intersect_key(['before' => null, 'after' => null], $this->changes), $this->changes);
        foreach ($ordered as $key => $value) {
            $object->{$key} = $value === [] ? new \stdClass() : $value;
        }

        return $object;
    }

    public function changesJson(): string
    {
        return json_encode($this->changesAsObject(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
