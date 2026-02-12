<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212072443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Pour PostgreSQL 9, on ajoute d'abord la colonne nullable avec valeur par défaut
        $this->addSql('ALTER TABLE depense_fixe ADD COLUMN prelevement_passe BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE user_depense_fixe ADD COLUMN prelevement_passe BOOLEAN DEFAULT false');
        
        // Mettre à jour tous les enregistrements existants à false (au cas où)
        $this->addSql('UPDATE depense_fixe SET prelevement_passe = false WHERE prelevement_passe IS NULL');
        $this->addSql('UPDATE user_depense_fixe SET prelevement_passe = false WHERE prelevement_passe IS NULL');
        
        // Ensuite rendre la colonne NOT NULL
        $this->addSql('ALTER TABLE depense_fixe ALTER COLUMN prelevement_passe SET NOT NULL');
        $this->addSql('ALTER TABLE user_depense_fixe ALTER COLUMN prelevement_passe SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_depense_fixe DROP COLUMN prelevement_passe');
        $this->addSql('ALTER TABLE depense_fixe DROP COLUMN prelevement_passe');
    }
}
