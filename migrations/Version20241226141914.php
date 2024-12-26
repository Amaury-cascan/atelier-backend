<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241226141914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD connu VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD etat VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD note TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "user" DROP connu');
        $this->addSql('ALTER TABLE "user" DROP etat');
        $this->addSql('ALTER TABLE "user" DROP note');
    }
}
