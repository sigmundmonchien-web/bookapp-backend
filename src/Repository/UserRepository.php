<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findLikedBookIds(int $userId): array
    {
        $em = $this->getEntityManager();

       $favBooks = $em->createQuery(
            'SELECT DISTINCT IDENTITY(f.book) AS bookId
            FROM App\Entity\Favorite f
            WHERE f.user = :userId')  
            ->setParameter('userId', $userId)->getScalarResult();

        $ratedBooks = $em->createQuery(
            'SELECT DISTINCT IDENTITY(r.book) AS bookId
            FROM App\Entity\Rating r
            WHERE r.user = :userId AND r.score >= 4')
            ->setParameter('userId', $userId)
            ->getScalarResult();    

        $ids = array_merge(
            array_map(fn($row) => (int) $row['bookId'], $favBooks),
            array_map(fn($row) => (int) $row['bookId'], $ratedBooks)
        );
        return array_values(array_unique($ids));
    }   
      
    public function findLikeMatchesForBooks(array $booksIds, int $excludeUserId): array
{
    if (empty($booksIds)) {
        return [];
    }
    $em = $this->getEntityManager();

    $favMatches = $em->createQuery(
        'SELECT DISTINCT IDENTITY(f.user) AS userId, IDENTITY(f.book) AS bookId
        FROM App\Entity\Favorite f
        WHERE f.book IN (:booksIds) AND f.user != :excludeUserId')
        ->setParameter('booksIds', $booksIds)
        ->setParameter('excludeUserId', $excludeUserId)
        ->getScalarResult();

    $ratedMatches = $em->createQuery(
        'SELECT DISTINCT IDENTITY(r.user) AS userId, IDENTITY(r.book) AS bookId
        FROM App\Entity\Rating r
        WHERE r.book IN (:booksIds) AND r.user != :excludeUserId AND r.score >= 4')
        ->setParameter('booksIds', $booksIds)
        ->setParameter('excludeUserId', $excludeUserId)
        ->getScalarResult();

    return array_merge($favMatches, $ratedMatches);
}
}