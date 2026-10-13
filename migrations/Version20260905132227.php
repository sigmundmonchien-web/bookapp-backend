<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de la colonne roles sur ei_user (support ROLE_ADMIN).
 *
 * L'ajout se fait en 3 temps parce que MySQL/MariaDB n'autorise pas
 * de valeur DEFAULT sur les colonnes JSON :
 *   1. Ajout nullable pour ne pas bloquer sur les lignes existantes
 *   2. Initialisation manuelle des utilisateurs déjà en base à '[]'
 *   3. Passage en NOT NULL une fois toutes les lignes remplies
 */
final class Version20260905132227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne roles sur ei_user (support ROLE_ADMIN)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_user ADD roles JSON DEFAULT NULL');
        $this->addSql("UPDATE ei_user SET roles = '[]' WHERE roles IS NULL");
        $this->addSql('ALTER TABLE ei_user MODIFY roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_user DROP roles');
    }
}