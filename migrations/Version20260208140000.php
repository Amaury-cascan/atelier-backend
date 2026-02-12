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
        // Vérifier si la colonne existe déjà (au cas où la migration a été partiellement exécutée)
        $this->addSql(<<<'SQL'
            DO $$ 
            BEGIN 
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = 'appointment' AND column_name = 'price'
                ) THEN
                    ALTER TABLE appointment ADD COLUMN price INT DEFAULT NULL;
                END IF;
            END $$;
SQL
        );

        $this->addSql(<<<'SQL'
            UPDATE appointment a
            SET price = s.price
            FROM service s
            WHERE a.service_id = s.id AND a.price IS NULL
SQL
        );

        // Vérifier si service_id est NOT NULL avant de le rendre nullable
        $this->addSql(<<<'SQL'
            DO $$ 
            BEGIN 
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = 'appointment' 
                    AND column_name = 'service_id' 
                    AND is_nullable = 'NO'
                ) THEN
                    ALTER TABLE appointment ALTER COLUMN service_id DROP NOT NULL;
                END IF;
            END $$;
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment ALTER COLUMN service_id SET NOT NULL');
        $this->addSql('ALTER TABLE appointment DROP COLUMN price');
    }
}
