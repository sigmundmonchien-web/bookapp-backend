<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Service\BookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/favorites')]
class FavoriteController extends AbstractController
{
    #[Route('', name: 'favorites_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] User $user,
        BookService $bookService,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['googleBooksId'])) {
            return $this->json(['error' => 'googleBooksId est requis'], 400);
        }

        try {
            $book = $bookService->findOrCreateByGoogleBooksId($data['googleBooksId']);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Impossible de récupérer le livre depuis Google Books'], 502);
        }

        $existing = $favoriteRepository->findOneBy(['user' => $user, 'book' => $book]);
        if ($existing !== null) {
            return $this->json(['error' => 'Ce livre est déjà dans vos favoris'], 409);
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setBook($book);
        $favorite->setCreatedAt(new \DateTimeImmutable());

        $em->persist($favorite);
        $em->flush();

        return $this->json([
            'id' => $favorite->getId(),
            'createdAt' => $favorite->getCreatedAt()->format('c'),
            'book' => [
                'id' => $book->getId(),
                'googleBooksId' => $book->getGoogleBooksId(),
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'coverUrl' => $book->getCoverUrl(),
            ],
        ], 201);
    }

    #[Route('', name: 'favorites_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        FavoriteRepository $favoriteRepository
    ): JsonResponse {
        $favorites = $favoriteRepository->findAllForUserWithBook($user);

        $data = array_map(fn(Favorite $f) => [
            'id' => $f->getId(),
            'createdAt' => $f->getCreatedAt()->format('c'),
            'book' => [
                'id' => $f->getBook()->getId(),
                'googleBooksId' => $f->getBook()->getGoogleBooksId(),
                'title' => $f->getBook()->getTitle(),
                'author' => $f->getBook()->getAuthor(),
                'coverUrl' => $f->getBook()->getCoverUrl(),
            ],
        ], $favorites);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'favorites_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        #[CurrentUser] User $user,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $favorite = $favoriteRepository->find($id);

        if ($favorite === null) {
            return $this->json(['error' => 'Favori non trouvé'], 404);
        }

        if ($favorite->getUser() !== $user) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $em->remove($favorite);
        $em->flush();

        return $this->json(null, 204);
    }
}