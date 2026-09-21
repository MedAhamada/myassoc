<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921170250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: admin, adherant, adhesion';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adherant (id BINARY(16) NOT NULL, uuid BINARY(16) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(255) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_naissance DATE DEFAULT NULL, profession VARCHAR(255) DEFAULT NULL, commentaires LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_adherant_statut (statut), UNIQUE INDEX uniq_adherant_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE adhesion (id BINARY(16) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, adherant_id BINARY(16) NOT NULL, created_by BINARY(16) DEFAULT NULL, INDEX idx_adhesion_statut (statut), INDEX IDX_C50CA65ABE612E45 (adherant_id), INDEX IDX_C50CA65ADE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `admin` (id BINARY(16) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, roles JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_admin_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE adhesion ADD CONSTRAINT FK_C50CA65ABE612E45 FOREIGN KEY (adherant_id) REFERENCES adherant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE adhesion ADD CONSTRAINT FK_C50CA65ADE12AB56 FOREIGN KEY (created_by) REFERENCES `admin` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adhesion DROP FOREIGN KEY FK_C50CA65ABE612E45');
        $this->addSql('ALTER TABLE adhesion DROP FOREIGN KEY FK_C50CA65ADE12AB56');
        $this->addSql('DROP TABLE adherant');
        $this->addSql('DROP TABLE adhesion');
        $this->addSql('DROP TABLE `admin`');
    }
}
