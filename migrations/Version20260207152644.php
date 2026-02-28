<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207152644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sortie_ai_reference_article DROP FOREIGN KEY `FK_15D5AD5D2450A84F`');
        $this->addSql('ALTER TABLE sortie_ai_reference_article DROP FOREIGN KEY `FK_15D5AD5D268AB3D3`');
        $this->addSql('DROP TABLE sortie_ai_reference_article');
        $this->addSql('ALTER TABLE sortie_ai ADD article_id INT NOT NULL');
        $this->addSql('ALTER TABLE sortie_ai ADD CONSTRAINT FK_7A1A2FE67294869C FOREIGN KEY (article_id) REFERENCES reference_article (id)');
        $this->addSql('CREATE INDEX IDX_7A1A2FE67294869C ON sortie_ai (article_id)');
        $this->addSql('ALTER TABLE utilisateur ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sortie_ai_reference_article (sortie_ai_id INT NOT NULL, reference_article_id INT NOT NULL, INDEX IDX_15D5AD5D2450A84F (sortie_ai_id), INDEX IDX_15D5AD5D268AB3D3 (reference_article_id), PRIMARY KEY (sortie_ai_id, reference_article_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE sortie_ai_reference_article ADD CONSTRAINT `FK_15D5AD5D2450A84F` FOREIGN KEY (sortie_ai_id) REFERENCES sortie_ai (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sortie_ai_reference_article ADD CONSTRAINT `FK_15D5AD5D268AB3D3` FOREIGN KEY (reference_article_id) REFERENCES reference_article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sortie_ai DROP FOREIGN KEY FK_7A1A2FE67294869C');
        $this->addSql('DROP INDEX IDX_7A1A2FE67294869C ON sortie_ai');
        $this->addSql('ALTER TABLE sortie_ai DROP article_id');
        $this->addSql('DROP INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP reset_token, DROP reset_token_expires_at');
    }
}
