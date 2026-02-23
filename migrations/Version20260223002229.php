<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223002229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_8A8E26E9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE plan_actions_articles (plan_actions_id INT NOT NULL, reference_article_id INT NOT NULL, INDEX IDX_D727B247DC3271E0 (plan_actions_id), INDEX IDX_D727B247268AB3D3 (reference_article_id), PRIMARY KEY (plan_actions_id, reference_article_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE sortie_ai_articles (sortie_ai_id INT NOT NULL, reference_article_id INT NOT NULL, INDEX IDX_917011B42450A84F (sortie_ai_id), INDEX IDX_917011B4268AB3D3 (reference_article_id), PRIMARY KEY (sortie_ai_id, reference_article_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE plan_actions_articles ADD CONSTRAINT FK_D727B247DC3271E0 FOREIGN KEY (plan_actions_id) REFERENCES plan_actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_actions_articles ADD CONSTRAINT FK_D727B247268AB3D3 FOREIGN KEY (reference_article_id) REFERENCES reference_article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sortie_ai_articles ADD CONSTRAINT FK_917011B42450A84F FOREIGN KEY (sortie_ai_id) REFERENCES sortie_ai (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sortie_ai_articles ADD CONSTRAINT FK_917011B4268AB3D3 FOREIGN KEY (reference_article_id) REFERENCES reference_article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objectif ADD titre VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(500) NOT NULL');
        $this->addSql('ALTER TABLE parcours ADD utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE3FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_99B1DEE3FB88E14F ON parcours (utilisateur_id)');
        $this->addSql('ALTER TABLE plan_actions ADD date DATETIME NOT NULL, ADD categorie VARCHAR(255) DEFAULT NULL, ADD feedback_enseignant LONGTEXT DEFAULT NULL, ADD feedback_date DATETIME DEFAULT NULL, ADD etudiant_id INT DEFAULT NULL, ADD feedback_auteur_id INT DEFAULT NULL, ADD auteur_id INT DEFAULT NULL, DROP created_at, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT FK_BBF7B9E7DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT FK_BBF7B9E7DEFF315D FOREIGN KEY (feedback_auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT FK_BBF7B9E760BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_BBF7B9E7DDEAB1A3 ON plan_actions (etudiant_id)');
        $this->addSql('CREATE INDEX IDX_BBF7B9E7DEFF315D ON plan_actions (feedback_auteur_id)');
        $this->addSql('CREATE INDEX IDX_BBF7B9E760BB6FE6 ON plan_actions (auteur_id)');
        $this->addSql('ALTER TABLE programme ADD meilleure_medaille VARCHAR(255) DEFAULT NULL, ADD score_pourcentage INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE ressource CHANGE url_ressource url_ressource VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_ai DROP FOREIGN KEY `FK_7A1A2FE67294869C`');
        $this->addSql('DROP INDEX IDX_7A1A2FE67294869C ON sortie_ai');
        $this->addSql('ALTER TABLE sortie_ai ADD statut VARCHAR(20) DEFAULT \'NOUVEAU\' NOT NULL, ADD contenu LONGTEXT NOT NULL, ADD created_at DATE NOT NULL, ADD updated_at DATE DEFAULT NULL, ADD etudiant_id INT DEFAULT NULL, DROP article_id');
        $this->addSql('ALTER TABLE sortie_ai ADD CONSTRAINT FK_7A1A2FE6DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_7A1A2FE6DDEAB1A3 ON sortie_ai (etudiant_id)');
        $this->addSql('ALTER TABLE tache DROP score, CHANGE medaille titre VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_ordre_per_programme ON tache (programme_id, ordre)');
        $this->addSql('ALTER TABLE utilisateur ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL, ADD status VARCHAR(20) DEFAULT \'actif\' NOT NULL, ADD preferences JSON DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE plan_actions_articles DROP FOREIGN KEY FK_D727B247DC3271E0');
        $this->addSql('ALTER TABLE plan_actions_articles DROP FOREIGN KEY FK_D727B247268AB3D3');
        $this->addSql('ALTER TABLE sortie_ai_articles DROP FOREIGN KEY FK_917011B42450A84F');
        $this->addSql('ALTER TABLE sortie_ai_articles DROP FOREIGN KEY FK_917011B4268AB3D3');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE plan_actions_articles');
        $this->addSql('DROP TABLE sortie_ai_articles');
        $this->addSql('ALTER TABLE objectif DROP titre, CHANGE description description VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE3FB88E14F');
        $this->addSql('DROP INDEX IDX_99B1DEE3FB88E14F ON parcours');
        $this->addSql('ALTER TABLE parcours DROP utilisateur_id');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY FK_BBF7B9E7DDEAB1A3');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY FK_BBF7B9E7DEFF315D');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY FK_BBF7B9E760BB6FE6');
        $this->addSql('DROP INDEX IDX_BBF7B9E7DDEAB1A3 ON plan_actions');
        $this->addSql('DROP INDEX IDX_BBF7B9E7DEFF315D ON plan_actions');
        $this->addSql('DROP INDEX IDX_BBF7B9E760BB6FE6 ON plan_actions');
        $this->addSql('ALTER TABLE plan_actions ADD created_at DATE NOT NULL, DROP date, DROP categorie, DROP feedback_enseignant, DROP feedback_date, DROP etudiant_id, DROP feedback_auteur_id, DROP auteur_id, CHANGE updated_at updated_at DATE DEFAULT NULL, CHANGE statut statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE programme DROP meilleure_medaille, DROP score_pourcentage');
        $this->addSql('ALTER TABLE ressource CHANGE url_ressource url_ressource VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE sortie_ai DROP FOREIGN KEY FK_7A1A2FE6DDEAB1A3');
        $this->addSql('DROP INDEX IDX_7A1A2FE6DDEAB1A3 ON sortie_ai');
        $this->addSql('ALTER TABLE sortie_ai ADD article_id INT NOT NULL, DROP statut, DROP contenu, DROP created_at, DROP updated_at, DROP etudiant_id');
        $this->addSql('ALTER TABLE sortie_ai ADD CONSTRAINT `FK_7A1A2FE67294869C` FOREIGN KEY (article_id) REFERENCES reference_article (id)');
        $this->addSql('CREATE INDEX IDX_7A1A2FE67294869C ON sortie_ai (article_id)');
        $this->addSql('DROP INDEX unique_ordre_per_programme ON tache');
        $this->addSql('ALTER TABLE tache ADD score INT NOT NULL, CHANGE titre medaille VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP reset_token, DROP reset_token_expires_at, DROP status, DROP preferences');
    }
}
