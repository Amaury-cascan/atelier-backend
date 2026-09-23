<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Horaires configurables avec versions datées (ex. grille octobre, grille novembre).
 * Seed = horaires actuels du salon.
 */
final class Version20260923120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables schedule_version, weekly_opening, schedule_exception, blocked_slot + seed horaires actuels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE schedule_version (
                id SERIAL NOT NULL,
                effective_from DATE NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_schedule_version_effective_from ON schedule_version (effective_from)');

        $this->addSql(<<<'SQL'
            CREATE TABLE weekly_opening (
                id SERIAL NOT NULL,
                version_id INT NOT NULL,
                weekday SMALLINT NOT NULL,
                kind VARCHAR(16) NOT NULL,
                ranges JSON NOT NULL,
                label VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_weekly_opening_version FOREIGN KEY (version_id)
                    REFERENCES schedule_version (id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_weekly_opening_version_weekday ON weekly_opening (version_id, weekday)');

        $this->addSql(<<<'SQL'
            CREATE TABLE schedule_exception (
                id SERIAL NOT NULL,
                date DATE NOT NULL,
                kind VARCHAR(16) NOT NULL,
                ranges JSON NOT NULL,
                label VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_schedule_exception_date ON schedule_exception (date)');

        $this->addSql(<<<'SQL'
            CREATE TABLE blocked_slot (
                id SERIAL NOT NULL,
                start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                end_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT chk_blocked_slot_time_range CHECK (end_at > start_at)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_blocked_slot_start_end ON blocked_slot (start_at, end_at)');

        // Version initiale : applicable depuis toujours, = grille actuelle du site
        $this->addSql(<<<'SQL'
            INSERT INTO schedule_version (effective_from, name)
            VALUES ('2000-01-01', 'Horaires actuels')
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO weekly_opening (version_id, weekday, kind, ranges, label)
            SELECT v.id, d.weekday, d.kind, d.ranges::json, d.label
            FROM schedule_version v
            CROSS JOIN (
                VALUES
                    (0, 'closed', '[]', 'Fermé'),
                    (1, 'open', '[{"startMin":570,"endMin":960},{"startMin":1110,"endMin":1200}]', '09h30 – 16h00 · 18h30 – 20h00'),
                    (2, 'external', '[]', 'Prestation extérieure'),
                    (3, 'closed', '[]', 'Fermé'),
                    (4, 'external', '[]', 'Prestation extérieure'),
                    (5, 'open', '[{"startMin":570,"endMin":1200}]', '09h30 – 20h00'),
                    (6, 'open', '[{"startMin":570,"endMin":1200}]', '09h30 – 20h00')
            ) AS d(weekday, kind, ranges, label)
            WHERE v.effective_from = '2000-01-01'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS blocked_slot');
        $this->addSql('DROP TABLE IF EXISTS schedule_exception');
        $this->addSql('DROP TABLE IF EXISTS weekly_opening');
        $this->addSql('DROP TABLE IF EXISTS schedule_version');
    }
}
