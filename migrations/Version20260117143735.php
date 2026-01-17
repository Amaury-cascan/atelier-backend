<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117143735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE depense_fixe_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE enveloppe_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE exercice_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_depense_fixe_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_mois_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE depense_fixe (id INT NOT NULL, exercice_id INT NOT NULL, nom VARCHAR(255) NOT NULL, montant INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E8516A8489D40298 ON depense_fixe (exercice_id)');
        $this->addSql('CREATE TABLE enveloppe (id INT NOT NULL, user_mois_id INT NOT NULL, nom VARCHAR(255) NOT NULL, montant INT NOT NULL, pourcentage INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9B8CE05CA20A57B2 ON enveloppe (user_mois_id)');
        $this->addSql('CREATE TABLE exercice (id INT NOT NULL, mois TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, montant_total INT DEFAULT NULL, montant_aide INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE user_depense_fixe (id INT NOT NULL, user_mois_id INT NOT NULL, nom VARCHAR(255) NOT NULL, montant INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_689D1686A20A57B2 ON user_depense_fixe (user_mois_id)');
        $this->addSql('CREATE TABLE user_mois (id INT NOT NULL, current_user_id INT NOT NULL, exercice_id INT NOT NULL, salaire INT DEFAULT NULL, taux_enveloppe INT DEFAULT NULL, epargne INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AC31C07ED635610 ON user_mois (current_user_id)');
        $this->addSql('CREATE INDEX IDX_AC31C07E89D40298 ON user_mois (exercice_id)');
        $this->addSql('ALTER TABLE depense_fixe ADD CONSTRAINT FK_E8516A8489D40298 FOREIGN KEY (exercice_id) REFERENCES exercice (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE enveloppe ADD CONSTRAINT FK_9B8CE05CA20A57B2 FOREIGN KEY (user_mois_id) REFERENCES user_mois (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_depense_fixe ADD CONSTRAINT FK_689D1686A20A57B2 FOREIGN KEY (user_mois_id) REFERENCES user_mois (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_mois ADD CONSTRAINT FK_AC31C07ED635610 FOREIGN KEY (current_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_mois ADD CONSTRAINT FK_AC31C07E89D40298 FOREIGN KEY (exercice_id) REFERENCES exercice (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE depense_fixe_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE enveloppe_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE exercice_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_depense_fixe_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_mois_id_seq CASCADE');
        $this->addSql('ALTER TABLE depense_fixe DROP CONSTRAINT FK_E8516A8489D40298');
        $this->addSql('ALTER TABLE enveloppe DROP CONSTRAINT FK_9B8CE05CA20A57B2');
        $this->addSql('ALTER TABLE user_depense_fixe DROP CONSTRAINT FK_689D1686A20A57B2');
        $this->addSql('ALTER TABLE user_mois DROP CONSTRAINT FK_AC31C07ED635610');
        $this->addSql('ALTER TABLE user_mois DROP CONSTRAINT FK_AC31C07E89D40298');
        $this->addSql('DROP TABLE depense_fixe');
        $this->addSql('DROP TABLE enveloppe');
        $this->addSql('DROP TABLE exercice');
        $this->addSql('DROP TABLE user_depense_fixe');
        $this->addSql('DROP TABLE user_mois');
    }
}
