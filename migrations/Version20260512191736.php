<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512191736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_message (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(50) NOT NULL, contenu LONGTEXT NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, start_interaction_time DATETIME DEFAULT NULL, learning_duration_seconds INT DEFAULT NULL, profil_apprentissage_id INT NOT NULL, INDEX IDX_FAB3FC1669664E32 (profil_apprentissage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_FAB3FC1669664E32 FOREIGN KEY (profil_apprentissage_id) REFERENCES profil_apprentissage (id)');
        $this->addSql('ALTER TABLE feedback CHANGE traitement_id traitement_id INT NOT NULL, CHANGE utilisateur_id utilisateur_id INT NOT NULL');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE motivation DROP FOREIGN KEY `FK_E06073ED62BB7AEE`');
        $this->addSql('ALTER TABLE motivation CHANGE programme_id programme_id INT NOT NULL');
        $this->addSql('ALTER TABLE motivation ADD CONSTRAINT FK_E06073ED62BB7AEE FOREIGN KEY (programme_id) REFERENCES programme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objectif DROP FOREIGN KEY `FK_E2F86851FB88E14F`');
        $this->addSql('ALTER TABLE objectif CHANGE statut statut VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE objectif ADD CONSTRAINT FK_E2F86851FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reference_article DROP plan_actions_id');
        $this->addSql('ALTER TABLE ressource DROP FOREIGN KEY `FK_939F4544C18272`');
        $this->addSql('ALTER TABLE ressource CHANGE projet_id projet_id INT NOT NULL');
        $this->addSql('ALTER TABLE ressource ADD CONSTRAINT FK_939F4544C18272 FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tache DROP FOREIGN KEY `FK_9387207562BB7AEE`');
        $this->addSql('ALTER TABLE tache CHANGE programme_id programme_id INT NOT NULL');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_9387207562BB7AEE FOREIGN KEY (programme_id) REFERENCES programme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT 100 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_FAB3FC1669664E32');
        $this->addSql('DROP TABLE chat_message');
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458FB88E14F');
        $this->addSql('ALTER TABLE feedback CHANGE traitement_id traitement_id INT DEFAULT NULL, CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE motivation DROP FOREIGN KEY FK_E06073ED62BB7AEE');
        $this->addSql('ALTER TABLE motivation CHANGE programme_id programme_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE motivation ADD CONSTRAINT `FK_E06073ED62BB7AEE` FOREIGN KEY (programme_id) REFERENCES programme (id)');
        $this->addSql('ALTER TABLE objectif DROP FOREIGN KEY FK_E2F86851FB88E14F');
        $this->addSql('ALTER TABLE objectif CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE objectif ADD CONSTRAINT `FK_E2F86851FB88E14F` FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE reference_article ADD plan_actions_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ressource DROP FOREIGN KEY FK_939F4544C18272');
        $this->addSql('ALTER TABLE ressource CHANGE projet_id projet_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ressource ADD CONSTRAINT `FK_939F4544C18272` FOREIGN KEY (projet_id) REFERENCES projet (id)');
        $this->addSql('ALTER TABLE tache DROP FOREIGN KEY FK_9387207562BB7AEE');
        $this->addSql('ALTER TABLE tache CHANGE programme_id programme_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT `FK_9387207562BB7AEE` FOREIGN KEY (programme_id) REFERENCES programme (id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT \'100\' NOT NULL');
    }
}
