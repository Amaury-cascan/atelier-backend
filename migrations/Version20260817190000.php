<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Arrêt du stockage des fiches de suivi, photos clientes et consentements associés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM picture');
        $this->addSql('DELETE FROM client_information');
        $this->addSql('UPDATE "user" SET etat = NULL, note = NULL');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_internes');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_internes_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_publication');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_photos_publication_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_donnees_sante');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS consent_donnees_sante_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS consent_photos_internes BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS consent_photos_internes_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS consent_photos_publication BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS consent_photos_publication_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS consent_donnees_sante BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS consent_donnees_sante_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
