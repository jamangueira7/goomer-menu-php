<?php

declare(strict_types=1);

namespace Goomer\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Log de auditoria: uma linha por escrita de negócio, gravada na mesma transação da escrita.
 * `actor_user_id` não tem FK de propósito: o log sobrevive à exclusão do usuário.
 */
final class Version20261126000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Log de auditoria (audit_logs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (
                id BIGSERIAL PRIMARY KEY,
                restaurant_id BIGINT NOT NULL REFERENCES restaurants (id) ON DELETE CASCADE,
                actor_user_id BIGINT NOT NULL,
                actor_email TEXT NOT NULL,
                action TEXT NOT NULL CHECK (length(btrim(action)) > 0),
                entity_type TEXT NOT NULL CHECK (length(btrim(entity_type)) > 0),
                entity_id BIGINT NULL,
                changes JSONB NOT NULL DEFAULT '{}' CHECK (jsonb_typeof(changes) = 'object'),
                ip INET NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);
        $this->addSql('CREATE INDEX audit_logs_actor_idx ON audit_logs (restaurant_id, actor_user_id, created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
    }
}
