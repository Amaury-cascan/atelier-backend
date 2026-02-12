<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117152502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Pour PostgreSQL 9, on ajoute d'abord la colonne nullable avec valeur par défaut
        $this->addSql('ALTER TABLE user_depense_fixe ADD COLUMN is_depense_commune BOOLEAN DEFAULT false');
        
        // Mettre à jour tous les enregistrements existants à false (au cas où)
        $this->addSql('UPDATE user_depense_fixe SET is_depense_commune = false WHERE is_depense_commune IS NULL');
        
        // Ensuite rendre la colonne NOT NULL
        $this->addSql('ALTER TABLE user_depense_fixe ALTER COLUMN is_depense_commune SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_depense_fixe DROP COLUMN is_depense_commune');
    }
}
