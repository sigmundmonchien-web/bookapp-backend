<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les contraintes de clés étrangères manquantes sur les tables
 * d'interaction utilisateur (ei_favorite, ei_rating, ei_reading_list).
 *
 * Les FK sont créées avec ON DELETE CASCADE pour garantir l'intégrité
 * référentielle et faciliter la suppression de compte utilisateur
 * conformément au droit à l'oubli (RGPD, article 17).
 */
final class Version20260904200440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des FK manquantes sur ei_favorite, ei_rating, ei_reading_list (ON DELETE CASCADE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3BA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3B16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750AA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750A16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C81A76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C8116A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_favorite DROP FOREIGN KEY FK_C7FA7B3BA76ED395');
        $this->addSql('ALTER TABLE ei_favorite DROP FOREIGN KEY FK_C7FA7B3B16A2B381');

        $this->addSql('ALTER TABLE ei_rating DROP FOREIGN KEY FK_838F750AA76ED395');
        $this->addSql('ALTER TABLE ei_rating DROP FOREIGN KEY FK_838F750A16A2B381');

        $this->addSql('ALTER TABLE ei_reading_list DROP FOREIGN KEY FK_A2503C81A76ED395');
        $this->addSql('ALTER TABLE ei_reading_list DROP FOREIGN KEY FK_A2503C8116A2B381');
    }
}