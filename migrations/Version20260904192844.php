<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les colonnes comment et updated_at à ei_rating
 * pour permettre aux utilisateurs de laisser un avis textuel
 * accompagnant leur note.
 */
final class Version20260904192844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs comment et updated_at sur ei_rating (avis textuels)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_rating ADD comment LONGTEXT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_rating DROP comment, DROP updated_at');
    }
}