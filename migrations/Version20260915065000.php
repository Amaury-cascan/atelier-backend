<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915065000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Répare les rendez-vous dont la fin précède le début, et interdit désormais ce cas en base.';
    }

    public function up(Schema $schema): void
    {
        // Ces rendez-vous forment une plage vide : ils n'entrent en collision avec
        // aucun créneau, donc le calendrier de réservation les affiche comme libres.
        // On rétablit la durée de la prestation associée.
        $this->addSql(<<<'SQL'
            UPDATE appointment AS a
            SET end_date = a.date + (GREATEST(COALESCE(s.duration, 60), 5) * INTERVAL '1 minute')
            FROM service AS s
            WHERE a.service_id = s.id
              AND a.end_date <= a.date
            SQL);

        // Rendez-vous sans prestation rattachée : durée par défaut, comme ailleurs dans l'application.
        $this->addSql(<<<'SQL'
            UPDATE appointment
            SET end_date = date + INTERVAL '60 minutes'
            WHERE end_date <= date
            SQL);

        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT chk_appointment_time_range CHECK (end_date > date)');

        // Sert la recherche de chevauchement exécutée à chaque réservation.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_appointment_date_end_date ON appointment (date, end_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_appointment_date_end_date');
        $this->addSql('ALTER TABLE appointment DROP CONSTRAINT IF EXISTS chk_appointment_time_range');
    }
}
