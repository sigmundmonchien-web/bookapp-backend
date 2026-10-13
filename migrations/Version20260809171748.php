<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809171748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3BA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id)');
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3B16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id)');
        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750AA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id)');
        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750A16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id)');
        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C81A76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id)');
        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C8116A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id)');
        $this->addSql('ALTER TABLE ei_user ADD photo_filename VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ei_favorite DROP FOREIGN KEY FK_C7FA7B3BA76ED395');
        $this->addSql('ALTER TABLE ei_favorite DROP FOREIGN KEY FK_C7FA7B3B16A2B381');
        $this->addSql('ALTER TABLE ei_rating DROP FOREIGN KEY FK_838F750AA76ED395');
        $this->addSql('ALTER TABLE ei_rating DROP FOREIGN KEY FK_838F750A16A2B381');
        $this->addSql('ALTER TABLE ei_reading_list DROP FOREIGN KEY FK_A2503C81A76ED395');
        $this->addSql('ALTER TABLE ei_reading_list DROP FOREIGN KEY FK_A2503C8116A2B381');
        $this->addSql('ALTER TABLE ei_user DROP photo_filename');
    }
}
