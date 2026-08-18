<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consentements photos (dossier / publication) et données de santé, horodatés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'user' AND column_name = 'consent_photos_internes'
                ) THEN
                    ALTER TABLE "user" ADD COLUMN consent_photos_internes BOOLEAN DEFAULT FALSE NOT NULL;
                    ALTER TABLE "user" ADD COLUMN consent_photos_internes_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
                    ALTER TABLE "user" ADD COLUMN consent_photos_publication BOOLEAN DEFAULT FALSE NOT NULL;
                    ALTER TABLE "user" ADD COLUMN consent_photos_publication_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
                    ALTER TABLE "user" ADD COLUMN consent_donnees_sante BOOLEAN DEFAULT FALSE NOT NULL;
                    ALTER TABLE "user" ADD COLUMN consent_donnees_sante_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
                END IF;
            END $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_internes');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_internes_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_publication');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_publication_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_donnees_sante');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_donnees_sante_at');
    }
}
