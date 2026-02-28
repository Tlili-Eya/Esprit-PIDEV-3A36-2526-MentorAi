<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228025216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_actions_articles (plan_actions_id INT NOT NULL, reference_article_id INT NOT NULL, INDEX IDX_D727B247DC3271E0 (plan_actions_id), INDEX IDX_D727B247268AB3D3 (reference_article_id), PRIMARY KEY (plan_actions_id, reference_article_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE plan_actions_articles ADD CONSTRAINT FK_D727B247DC3271E0 FOREIGN KEY (plan_actions_id) REFERENCES plan_actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plan_actions_articles ADD CONSTRAINT FK_D727B247268AB3D3 FOREIGN KEY (reference_article_id) REFERENCES reference_article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objectif ADD titre VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(500) NOT NULL');
        $this->addSql('ALTER TABLE parcours ADD utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE3FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_99B1DEE3FB88E14F ON parcours (utilisateur_id)');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY `FK_BBF7B9E7DDEAB1A3`');
        $this->addSql('ALTER TABLE plan_actions ADD date DATETIME NOT NULL, ADD categorie VARCHAR(255) DEFAULT NULL, ADD feedback_enseignant LONGTEXT DEFAULT NULL, ADD feedback_date DATETIME DEFAULT NULL, ADD feedback_auteur_id INT DEFAULT NULL, DROP created_at, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT FK_BBF7B9E7DEFF315D FOREIGN KEY (feedback_auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT FK_BBF7B9E760BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_BBF7B9E7DEFF315D ON plan_actions (feedback_auteur_id)');
        $this->addSql('CREATE INDEX IDX_BBF7B9E760BB6FE6 ON plan_actions (auteur_id)');
        $this->addSql('DROP INDEX fk_bbf7b9e7ddeab1a3 ON plan_actions');
        $this->addSql('CREATE INDEX IDX_BBF7B9E7DDEAB1A3 ON plan_actions (etudiant_id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT `FK_BBF7B9E7DDEAB1A3` FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE programme ADD meilleure_medaille VARCHAR(255) DEFAULT NULL, ADD score_pourcentage INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE ressource CHANGE url_ressource url_ressource VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sortie_ai DROP FOREIGN KEY `FK_7A1A2FE67294869C`');
        $this->addSql('DROP INDEX IDX_7A1A2FE67294869C ON sortie_ai');
        $this->addSql('ALTER TABLE sortie_ai ADD statut VARCHAR(20) DEFAULT \'NOUVEAU\' NOT NULL, ADD contenu LONGTEXT NOT NULL, ADD created_at DATE NOT NULL, ADD updated_at DATE DEFAULT NULL, ADD etudiant_id INT DEFAULT NULL, DROP article_id');
        $this->addSql('ALTER TABLE sortie_ai ADD CONSTRAINT FK_7A1A2FE6DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX IDX_7A1A2FE6DDEAB1A3 ON sortie_ai (etudiant_id)');
        $this->addSql('ALTER TABLE tache DROP score, CHANGE medaille titre VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_ordre_per_programme ON tache (programme_id, ordre)');
        $this->addSql('ALTER TABLE utilisateur ADD preferences JSON DEFAULT NULL, CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT 100 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_actions_articles DROP FOREIGN KEY FK_D727B247DC3271E0');
        $this->addSql('ALTER TABLE plan_actions_articles DROP FOREIGN KEY FK_D727B247268AB3D3');
        $this->addSql('DROP TABLE plan_actions_articles');
        $this->addSql('ALTER TABLE objectif DROP titre, CHANGE description description VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE3FB88E14F');
        $this->addSql('DROP INDEX IDX_99B1DEE3FB88E14F ON parcours');
        $this->addSql('ALTER TABLE parcours DROP utilisateur_id');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY FK_BBF7B9E7DEFF315D');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY FK_BBF7B9E760BB6FE6');
        $this->addSql('DROP INDEX IDX_BBF7B9E7DEFF315D ON plan_actions');
        $this->addSql('DROP INDEX IDX_BBF7B9E760BB6FE6 ON plan_actions');
        $this->addSql('ALTER TABLE plan_actions DROP FOREIGN KEY FK_BBF7B9E7DDEAB1A3');
        $this->addSql('ALTER TABLE plan_actions ADD created_at DATE NOT NULL, DROP date, DROP categorie, DROP feedback_enseignant, DROP feedback_date, DROP feedback_auteur_id, CHANGE updated_at updated_at DATE DEFAULT NULL, CHANGE statut statut VARCHAR(50) NOT NULL');
        $this->addSql('DROP INDEX idx_bbf7b9e7ddeab1a3 ON plan_actions');
        $this->addSql('CREATE INDEX FK_BBF7B9E7DDEAB1A3 ON plan_actions (etudiant_id)');
        $this->addSql('ALTER TABLE plan_actions ADD CONSTRAINT FK_BBF7B9E7DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE programme DROP meilleure_medaille, DROP score_pourcentage');
        $this->addSql('ALTER TABLE ressource CHANGE url_ressource url_ressource VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE sortie_ai DROP FOREIGN KEY FK_7A1A2FE6DDEAB1A3');
        $this->addSql('DROP INDEX IDX_7A1A2FE6DDEAB1A3 ON sortie_ai');
        $this->addSql('ALTER TABLE sortie_ai ADD article_id INT NOT NULL, DROP statut, DROP contenu, DROP created_at, DROP updated_at, DROP etudiant_id');
        $this->addSql('ALTER TABLE sortie_ai ADD CONSTRAINT `FK_7A1A2FE67294869C` FOREIGN KEY (article_id) REFERENCES reference_article (id)');
        $this->addSql('CREATE INDEX IDX_7A1A2FE67294869C ON sortie_ai (article_id)');
        $this->addSql('DROP INDEX unique_ordre_per_programme ON tache');
        $this->addSql('ALTER TABLE tache ADD score INT NOT NULL, CHANGE titre medaille VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE utilisateur DROP preferences, CHANGE trust_score trust_score DOUBLE PRECISION DEFAULT \'100\' NOT NULL');
    }
}
