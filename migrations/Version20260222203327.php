<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222203327 extends AbstractMigration
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
        $this->addSql('ALTER TABLE carnet DROP attachments');
        $this->addSql('ALTER TABLE planning_etude DROP couleur_activite');
        $this->addSql('CREATE UNIQUE INDEX unique_ordre_per_programme ON tache (programme_id, ordre)');
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
        $this->addSql('ALTER TABLE carnet ADD attachments JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE planning_etude ADD couleur_activite VARCHAR(20) DEFAULT NULL');
        $this->addSql('DROP INDEX unique_ordre_per_programme ON tache');
    }
}
