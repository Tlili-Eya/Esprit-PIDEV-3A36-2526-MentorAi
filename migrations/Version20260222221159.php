<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222221159 extends AbstractMigration
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
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_FAB3FC1669664E32');
        $this->addSql('DROP TABLE chat_message');
    }
}
