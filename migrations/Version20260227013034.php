<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227013034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur ADD trust_score DOUBLE PRECISION DEFAULT 100 NOT NULL, ADD risk_level VARCHAR(10) DEFAULT \'LOW\' NOT NULL, ADD flagged_duplicate TINYINT DEFAULT 0 NOT NULL, ADD login_attempts INT DEFAULT 0 NOT NULL, ADD last_login DATETIME DEFAULT NULL, ADD registration_ip VARCHAR(45) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur DROP trust_score, DROP risk_level, DROP flagged_duplicate, DROP login_attempts, DROP last_login, DROP registration_ip');
    }
}
