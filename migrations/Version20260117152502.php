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
        // Vérifier si la colonne existe déjà avant de l'ajouter
        $this->addSql(<<<'SQL'
            DO $$ 
            BEGIN 
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = 'user_depense_fixe' AND column_name = 'is_depense_commune'
                ) THEN
                    ALTER TABLE user_depense_fixe ADD COLUMN is_depense_commune BOOLEAN DEFAULT 'f';
                    UPDATE user_depense_fixe SET is_depense_commune = 'f' WHERE is_depense_commune IS NULL;
                    ALTER TABLE user_depense_fixe ALTER COLUMN is_depense_commune SET NOT NULL;
                END IF;
            END $$;
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_depense_fixe DROP COLUMN is_depense_commune');
    }
}
