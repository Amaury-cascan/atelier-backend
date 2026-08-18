<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Horodatage de l\'acceptation de la politique de confidentialité à l\'inscription.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'user' AND column_name = 'privacy_policy_accepted_at'
                ) THEN
                    ALTER TABLE "user" ADD COLUMN privacy_policy_accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
                END IF;
            END $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS privacy_policy_accepted_at');
    }
}
