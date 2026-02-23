<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add color for planning activities.
 */
final class Version20260208120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add couleur_activite column to planning_etude.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_etude ADD couleur_activite VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_etude DROP couleur_activite');
    }
}
