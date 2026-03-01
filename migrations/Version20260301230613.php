<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260301230613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_action (id INT AUTO_INCREMENT NOT NULL, decision VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, date DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, statut VARCHAR(255) NOT NULL, categorie VARCHAR(255) DEFAULT NULL, feedback_enseignant LONGTEXT DEFAULT NULL, feedback_date DATETIME DEFAULT NULL, etudiant_id INT DEFAULT NULL, sortie_ai_id INT NOT NULL, feedback_auteur_id INT DEFAULT NULL, auteur_id INT DEFAULT NULL, INDEX IDX_F39494E0DDEAB1A3 (etudiant_id), INDEX IDX_F39494E02450A84F (sortie_ai_id), INDEX IDX_F39494E0DEFF315D (feedback_auteur_id), INDEX IDX_F39494E060BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE plan_action ADD CONSTRAINT FK_F39494E0DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_action ADD CONSTRAINT FK_F39494E02450A84F FOREIGN KEY (sortie_ai_id) REFERENCES sortie_ai (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_action ADD CONSTRAINT FK_F39494E0DEFF315D FOREIGN KEY (feedback_auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_action ADD CONSTRAINT FK_F39494E060BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY `FK_BBF7B9E72450A84F`');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY `FK_BBF7B9E760BB6FE6`');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY `FK_BBF7B9E7DDEAB1A3`');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY `FK_BBF7B9E7DEFF315D`');
        $this->addSql('DROP TABLE plan_actions');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `FK_B6BD307F9AC0396`');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objectif CHANGE utilisateur_id utilisateur_id INT NOT NULL');
        $this->addSql('ALTER TABLE plan_actions_articles DROP FOREIGN KEY `FK_D727B247DC3271E0`');
        $this->addSql('ALTER TABLE plan_actions_articles ADD CONSTRAINT FK_D727B247DC3271E0 FOREIGN KEY (plan_actions_id) REFERENCES plan_action (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sortie_ai CHANGE statut statut VARCHAR(20) DEFAULT \'Nouveau\' NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT 100 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_actions (id INT AUTO_INCREMENT NOT NULL, decision VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, date DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, statut VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, sortie_ai_id INT DEFAULT NULL, categorie VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, feedback_enseignant LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, feedback_date DATETIME DEFAULT NULL, feedback_auteur_id INT DEFAULT NULL, etudiant_id INT DEFAULT NULL, auteur_id INT DEFAULT NULL, INDEX IDX_BBF7B9E760BB6FE6 (auteur_id), INDEX IDX_BBF7B9E72450A84F (sortie_ai_id), INDEX IDX_BBF7B9E7DEFF315D (feedback_auteur_id), INDEX IDX_BBF7B9E7DDEAB1A3 (etudiant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT `FK_BBF7B9E72450A84F` FOREIGN KEY (sortie_ai_id) REFERENCES sortie_ai (id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT `FK_BBF7B9E760BB6FE6` FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT `FK_BBF7B9E7DDEAB1A3` FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT `FK_BBF7B9E7DEFF315D` FOREIGN KEY (feedback_auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_action DROP FOREIGN KEY FK_F39494E0DDEAB1A3');
        $this->addSql('ALTER TABLE plan_action DROP FOREIGN KEY FK_F39494E02450A84F');
        $this->addSql('ALTER TABLE plan_action DROP FOREIGN KEY FK_F39494E0DEFF315D');
        $this->addSql('ALTER TABLE plan_action DROP FOREIGN KEY FK_F39494E060BB6FE6');
        $this->addSql('DROP TABLE plan_action');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `FK_B6BD307F9AC0396` FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE objectif CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE plan_actions_articles DROP FOREIGN KEY FK_D727B247DC3271E0');
        $this->addSql('ALTER TABLE plan_actions_articles ADD CONSTRAINT `FK_D727B247DC3271E0` FOREIGN KEY (plan_actions_id) REFERENCES plan_actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sortie_ai CHANGE statut statut VARCHAR(20) DEFAULT \'NOUVEAU\' NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT \'100\' NOT NULL');
    }
}
