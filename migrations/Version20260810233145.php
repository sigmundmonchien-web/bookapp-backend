<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810233145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publishedDate and pageCount to ei_book so book detail can be served from cache';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_book ADD published_date VARCHAR(50) DEFAULT NULL, ADD page_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ei_book DROP published_date, DROP page_count');
    }
}
