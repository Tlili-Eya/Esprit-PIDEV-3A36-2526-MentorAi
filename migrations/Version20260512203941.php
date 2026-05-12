<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512203941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification');
        $this->addSql('ALTER TABLE plan_actions CHANGE sortie_ai_id sortie_ai_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reference_article DROP plan_actions_id');
        $this->addSql('ALTER TABLE utilisateur CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT 100 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, message TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'INFO\' COLLATE `utf8mb4_general_ci`, is_lu TINYINT DEFAULT 0, is_done TINYINT DEFAULT 0, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, auteur_id INT DEFAULT 0, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE plan_actions CHANGE sortie_ai_id sortie_ai_id INT NOT NULL');
        $this->addSql('ALTER TABLE reference_article ADD plan_actions_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT \'100\' NOT NULL');
    }
}
