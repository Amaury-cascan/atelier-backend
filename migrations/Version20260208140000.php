<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Appointment: ajout price ; service_id nullable.
 */
final class Version20260208140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Appointment: price; service_id nullable.';
    }

    public function up(Schema $schema): void
    {
        // Compatible PostgreSQL 9
        $this->addSql('ALTER TABLE appointment ADD COLUMN price INT DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE appointment a
            SET price = s.price
            FROM service s
            WHERE a.service_id = s.id
SQL
        );

        $this->addSql('ALTER TABLE appointment ALTER COLUMN service_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment ALTER COLUMN service_id SET NOT NULL');
        $this->addSql('ALTER TABLE appointment DROP COLUMN price');
    }
}
