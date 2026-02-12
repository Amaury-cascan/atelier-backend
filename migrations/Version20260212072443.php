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
        // Vérifier si les colonnes existent déjà avant de les ajouter
        $this->addSql(<<<'SQL'
            DO $$ 
            BEGIN 
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = 'depense_fixe' AND column_name = 'prelevement_passe'
                ) THEN
                    ALTER TABLE depense_fixe ADD COLUMN prelevement_passe BOOLEAN DEFAULT 'f';
                    UPDATE depense_fixe SET prelevement_passe = 'f' WHERE prelevement_passe IS NULL;
                    ALTER TABLE depense_fixe ALTER COLUMN prelevement_passe SET NOT NULL;
                END IF;
            END $$;
SQL
        );
        
        $this->addSql(<<<'SQL'
            DO $$ 
            BEGIN 
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = 'user_depense_fixe' AND column_name = 'prelevement_passe'
                ) THEN
                    ALTER TABLE user_depense_fixe ADD COLUMN prelevement_passe BOOLEAN DEFAULT 'f';
                    UPDATE user_depense_fixe SET prelevement_passe = 'f' WHERE prelevement_passe IS NULL;
                    ALTER TABLE user_depense_fixe ALTER COLUMN prelevement_passe SET NOT NULL;
                END IF;
            END $$;
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_depense_fixe DROP COLUMN prelevement_passe');
        $this->addSql('ALTER TABLE depense_fixe DROP COLUMN prelevement_passe');
    }
}
