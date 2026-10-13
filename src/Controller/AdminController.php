<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\FavoriteRepository;
use App\Repository\RatingRepository;
use App\Repository\ReadingListRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private BookRepository $bookRepository,
        private RatingRepository $ratingRepository,
        private FavoriteRepository $favoriteRepository,
        private ReadingListRepository $readingListRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Statistiques globales de la plateforme.
     */
    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $usersCount = $this->userRepository->count([]);
        $booksCount = $this->bookRepository->count([]);
        $favoritesCount = $this->favoriteRepository->count([]);
        $readingListCount = $this->readingListRepository->count([]);
        $ratingsCount = $this->ratingRepository->count([]);

        // Nombre de ratings ayant un avis textuel
        $reviewsCount = (int) $this->em->createQuery(
            'SELECT COUNT(r.id) FROM App\Entity\Rating r
             WHERE r.comment IS NOT NULL AND r.comment <> \'\''
        )->getSingleScalarResult();

        // Top 5 des livres par nombre de notes
        $topBooks = $this->em->createQuery(
            'SELECT b.id, b.title, b.author,
                    COUNT(r.id) as ratingsCount,
                    AVG(r.score) as averageScore
             FROM App\Entity\Rating r
             JOIN r.book b
             GROUP BY b.id
             ORDER BY ratingsCount DESC'
        )->setMaxResults(5)->getArrayResult();

        $topBooks = array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'ratingsCount' => (int) $row['ratingsCount'],
            'averageScore' => round((float) $row['averageScore'], 1),
        ], $topBooks);

        return $this->json([
            'usersCount' => $usersCount,
            'booksCount' => $booksCount,
            'ratingsCount' => $ratingsCount,
            'reviewsCount' => $reviewsCount,
            'favoritesCount' => $favoritesCount,
            'readingListCount' => $readingListCount,
            'topBooks' => $topBooks,
        ]);
    }

    /**
     * Liste des utilisateurs avec leurs compteurs d'interactions.
     */
    #[Route('/users', name: 'admin_users_list', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = $this->userRepository->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'pseudo' => $u->getPseudo(),
            'roles' => $u->getRoles(),
            'createdAt' => $u->getCreatedAt()->format('c'),
            'photoFilename' => $u->getPhotoFilename(),
            'counts' => [
                'ratings' => $this->ratingRepository->count(['user' => $u]),
                'favorites' => $this->favoriteRepository->count(['user' => $u]),
                'readingList' => $this->readingListRepository->count(['user' => $u]),
            ],
        ], $users);

        return $this->json($data);
    }

    /**
     * Détail d'un utilisateur avec toutes ses interactions.
     */
    #[Route('/users/{id}', name: 'admin_user_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function userDetail(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $ratings = $this->ratingRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $favorites = $this->favoriteRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $readingList = $this->readingListRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'pseudo' => $user->getPseudo(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('c'),
            'photoFilename' => $user->getPhotoFilename(),
            'ratings' => array_map(fn(Rating $r) => [
                'id' => $r->getId(),
                'score' => $r->getScore(),
                'comment' => $r->getComment(),
                'createdAt' => $r->getCreatedAt()->format('c'),
                'updatedAt' => $r->getUpdatedAt()?->format('c'),
                'book' => $this->summarizeBook($r->getBook()),
            ], $ratings),
            'favorites' => array_map(fn($f) => [
                'id' => $f->getId(),
                'createdAt' => $f->getCreatedAt()->format('c'),
                'book' => $this->summarizeBook($f->getBook()),
            ], $favorites),
            'readingList' => array_map(fn($rl) => [
                'id' => $rl->getId(),
                'createdAt' => $rl->getCreatedAt()->format('c'),
                'book' => $this->summarizeBook($rl->getBook()),
            ], $readingList),
        ]);
    }

    /**
     * Suppression d'un utilisateur.
     * Le CASCADE sur les FK supprime automatiquement ses ratings, favoris et reading list.
     */
    #[Route('/users/{id}', name: 'admin_user_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUser(int $id, #[CurrentUser] User $currentAdmin): JsonResponse
    {
        // Garde-fou : un admin ne peut pas se supprimer lui-même
        if ($id === $currentAdmin->getId()) {
            return $this->json(
                ['error' => 'Un administrateur ne peut pas supprimer son propre compte depuis cette interface.'],
                400
            );
        }

        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(null, 204);
    }

    /**
     * Suppression d'un avis textuel (modération).
     * Efface uniquement le champ comment, la note (score) est conservée.
     */
    #[Route('/ratings/{id}/comment', name: 'admin_review_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteReviewComment(int $id): JsonResponse
    {
        $rating = $this->ratingRepository->find($id);
        if ($rating === null) {
            return $this->json(['error' => 'Note introuvable'], 404);
        }

        if ($rating->getComment() === null) {
            return $this->json(['error' => 'Cette note ne comporte pas d\'avis textuel.'], 400);
        }

        $rating->setComment(null);
        $rating->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(null, 204);
    }

    /**
     * Représentation compacte d'un livre pour les listes.
     */
    private function summarizeBook($book): array
    {
        return [
            'id' => $book->getId(),
            'googleBooksId' => $book->getGoogleBooksId(),
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'coverUrl' => $book->getCoverUrl(),
        ];
    }
}