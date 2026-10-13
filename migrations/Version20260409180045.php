<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409180045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ei_book (id INT AUTO_INCREMENT NOT NULL, google_books_id VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, author VARCHAR(255) DEFAULT NULL, cover_url VARCHAR(500) DEFAULT NULL, description LONGTEXT DEFAULT NULL, genre VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ei_favorite (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, book_id INT NOT NULL, INDEX IDX_C7FA7B3BA76ED395 (user_id), INDEX IDX_C7FA7B3B16A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ei_rating (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, book_id INT NOT NULL, INDEX IDX_838F750AA76ED395 (user_id), INDEX IDX_838F750A16A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ei_reading_list (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, book_id INT NOT NULL, INDEX IDX_A2503C81A76ED395 (user_id), INDEX IDX_A2503C8116A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ei_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7D8DB3E1E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3BA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id)');
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3B16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id)');
        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750AA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id)');
        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750A16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id)');
        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C81A76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id)');
        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C8116A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id)');
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
        $this->addSql('DROP TABLE ei_book');
        $this->addSql('DROP TABLE ei_favorite');
        $this->addSql('DROP TABLE ei_rating');
        $this->addSql('DROP TABLE ei_reading_list');
        $this->addSql('DROP TABLE ei_user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
