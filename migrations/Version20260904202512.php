<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertit les tables applicatives du moteur MyISAM au moteur InnoDB
 * et normalise leur charset en utf8mb4 pour supporter l'ensemble des
 * caractères Unicode (dont les emojis, potentiellement présents dans
 * les avis textuels des utilisateurs).
 *
 * Cette conversion est un prérequis technique pour toute contrainte
 * d'intégrité référentielle : le moteur MyISAM accepte syntaxiquement
 * les clés étrangères mais les ignore silencieusement à l'exécution,
 * ce qui explique que la migration Version20260904200440 avait pu
 * être marquée comme exécutée sans que les FK ne soient réellement
 * créées dans la base.
 *
 * Cette migration recrée donc effectivement les 6 clés étrangères
 * avec ON DELETE CASCADE, préparant ainsi la suppression de compte
 * utilisateur exigée par le droit à l'oubli (RGPD, article 17).
 */
final class Version20260904202512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conversion MyISAM -> InnoDB + utf8mb4, création effective des FK';
    }

    public function up(Schema $schema): void
    {
        // 1. Conversion moteur + charset des 5 tables métier
        $this->addSql('ALTER TABLE ei_user CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, ENGINE=InnoDB');
        $this->addSql('ALTER TABLE ei_book CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, ENGINE=InnoDB');
        $this->addSql('ALTER TABLE ei_favorite CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, ENGINE=InnoDB');
        $this->addSql('ALTER TABLE ei_rating CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, ENGINE=InnoDB');
        $this->addSql('ALTER TABLE ei_reading_list CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, ENGINE=InnoDB');

        // 2. Création effective des 6 FK avec ON DELETE CASCADE
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3BA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ei_favorite ADD CONSTRAINT FK_C7FA7B3B16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750AA76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ei_rating ADD CONSTRAINT FK_838F750A16A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C81A76ED395 FOREIGN KEY (user_id) REFERENCES ei_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ei_reading_list ADD CONSTRAINT FK_A2503C8116A2B381 FOREIGN KEY (book_id) REFERENCES ei_book (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // On supprime les FK d'abord (obligatoire avant de changer de moteur)
        $this->addSql('ALTER TABLE ei_favorite DROP FOREIGN KEY FK_C7FA7B3BA76ED395');
        $this->addSql('ALTER TABLE ei_favorite DROP FOREIGN KEY FK_C7FA7B3B16A2B381');
        $this->addSql('ALTER TABLE ei_rating DROP FOREIGN KEY FK_838F750AA76ED395');
        $this->addSql('ALTER TABLE ei_rating DROP FOREIGN KEY FK_838F750A16A2B381');
        $this->addSql('ALTER TABLE ei_reading_list DROP FOREIGN KEY FK_A2503C81A76ED395');
        $this->addSql('ALTER TABLE ei_reading_list DROP FOREIGN KEY FK_A2503C8116A2B381');

        // Puis retour à MyISAM
        $this->addSql('ALTER TABLE ei_user ENGINE=MyISAM');
        $this->addSql('ALTER TABLE ei_book ENGINE=MyISAM');
        $this->addSql('ALTER TABLE ei_favorite ENGINE=MyISAM');
        $this->addSql('ALTER TABLE ei_rating ENGINE=MyISAM');
        $this->addSql('ALTER TABLE ei_reading_list ENGINE=MyISAM');
    }
}