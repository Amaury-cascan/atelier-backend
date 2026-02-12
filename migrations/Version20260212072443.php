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
        // Ajouter la colonne avec valeur par défaut false
        $this->addSql('ALTER TABLE depense_fixe ADD prelevement_passe BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE user_depense_fixe ADD prelevement_passe BOOLEAN DEFAULT false NOT NULL');
        
        // Mettre à jour tous les enregistrements existants à false (au cas où)
        $this->addSql('UPDATE depense_fixe SET prelevement_passe = false WHERE prelevement_passe IS NULL');
        $this->addSql('UPDATE user_depense_fixe SET prelevement_passe = false WHERE prelevement_passe IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_depense_fixe DROP prelevement_passe');
        $this->addSql('ALTER TABLE depense_fixe DROP prelevement_passe');
    }
}
