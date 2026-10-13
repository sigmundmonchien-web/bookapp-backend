<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Rating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    /**
     * @return Rating[]
     */
    public function findAllForUserWithBook(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('b')
            ->innerJoin('r.book', 'b')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les avis textuels d'un livre, ordonnés du plus récent au plus ancien.
     * Un "avis" est un Rating dont le champ comment est non-null et non-vide.
     * Inclut l'avis de l'utilisateur courant : le frontend le distinguera via un flag.
     *
     * @return Rating[]
     */
    public function findReviewsForBook(Book $book): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('u')
            ->innerJoin('r.user', 'u')
            ->andWhere('r.book = :book')
            ->andWhere('r.comment IS NOT NULL')
            ->andWhere("r.comment <> ''")
            ->setParameter('book', $book)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la moyenne des notes et le nombre total de notes d'un livre.
     * La moyenne compte TOUTES les notes (avec ou sans avis textuel).
     * averageScore est arrondi à 1 décimale, ou null si le livre n'a aucune note.
     *
     * @return array{averageScore: float|null, ratingsCount: int}
     */
    public function getStatsForBook(Book $book): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) AS averageScore, COUNT(r.id) AS ratingsCount')
            ->andWhere('r.book = :book')
            ->setParameter('book', $book)
            ->getQuery()
            ->getSingleResult();

        return [
            'averageScore' => $result['averageScore'] !== null
                ? round((float) $result['averageScore'], 1)
                : null,
            'ratingsCount' => (int) $result['ratingsCount'],
        ];
    }
}