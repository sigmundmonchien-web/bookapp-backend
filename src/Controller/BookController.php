<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Rating;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\FavoriteRepository;
use App\Repository\RatingRepository;
use App\Repository\ReadingListRepository;
use App\Service\BookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Route('/api/books')]
class BookController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $googleBooksApiKey,
        private BookRepository $bookRepository,
        private RatingRepository $ratingRepository,
        private FavoriteRepository $favoriteRepository,
        private ReadingListRepository $readingListRepository,
        private BookService $bookService,
    ) {}

    #[Route('', name: 'api_books_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (empty($query)) {
            return new JsonResponse(
                ['error' => 'Le paramètre "q" est requis'],
                400
            );
        }

        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/books/v1/volumes', [
                'query' => [
                    'q' => $query,
                    'maxResults' => 20,
                    'langRestrict' => 'fr',
                    'key' => $this->googleBooksApiKey,
                ],
            ]);

            $data = $response->toArray();
        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            return new JsonResponse(
                ['error' => 'Le service de recherche est temporairement indisponible. Réessayez dans quelques instants.'],
                503
            );
        }

        $books = [];
        foreach ($data['items'] ?? [] as $item) {
            $info = $item['volumeInfo'] ?? [];
            $books[] = [
                'googleBooksId' => $item['id'],
                'title' => $info['title'] ?? 'Sans titre',
                'author' => isset($info['authors']) ? implode(', ', $info['authors']) : 'Auteur inconnu',
                'coverUrl' => $this->bookService->forceHttps($info['imageLinks']['thumbnail'] ?? null), 
                'description' => $info['description'] ?? null,
                'genre' => isset($info['categories']) ? $info['categories'][0] : null,
            ];
        }

        return new JsonResponse([
            'total' => count($books),
            'books' => $books,
        ]);
    }

    #[Route('/{id}', name: 'api_books_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        // Sert depuis le cache local si le livre a déjà été consulté/favori/noté :
        // évite un aller-retour vers l'API Google Books à chaque affichage.
        $book = $this->bookRepository->findOneBy(['googleBooksId' => $id]);

        if ($book === null) {
            try {
                $book = $this->bookService->findOrCreateByGoogleBooksId($id);
            } catch (HttpExceptionInterface $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                if ($statusCode === 404) {
                    return new JsonResponse(
                        ['error' => 'Livre non trouvé'],
                        404
                    );
                }

                return new JsonResponse(
                    ['error' => 'Le service est temporairement indisponible.'],
                    503
                );
            } catch (TransportExceptionInterface $e) {
                return new JsonResponse(
                    ['error' => 'Impossible de contacter le service.'],
                    503
                );
            }
        }

        /** @var User $user */
        $user = $this->getUser();

        $userInteractions = $this->getUserInteractions($user, $book);
        $reviews = $this->ratingRepository->findReviewsForBook($book);
        $stats = $this->ratingRepository->getStatsForBook($book);

        return new JsonResponse([
            'googleBooksId' => $book->getGoogleBooksId(),
            'title' => $book->getTitle(),
            'author' => $book->getAuthor() ?? 'Auteur inconnu',
            'coverUrl' => $book->getCoverUrl(),
            'description' => $book->getDescription(),
            'genre' => $book->getGenre(),
            'publishedDate' => $book->getPublishedDate(),
            'pageCount' => $book->getPageCount(),
            'userInteractions' => $userInteractions,
            'stats' => $stats,
            'reviews' => $this->formatReviews($reviews, $user),
        ]);
    }

    /**
     * Récupère les interactions de l'utilisateur avec ce livre (note + avis, favori, liste à lire).
     */
    private function getUserInteractions(User $user, Book $book): array
    {
        $rating = $this->ratingRepository->findOneBy(['user' => $user, 'book' => $book]);
        $favorite = $this->favoriteRepository->findOneBy(['user' => $user, 'book' => $book]);
        $readingList = $this->readingListRepository->findOneBy(['user' => $user, 'book' => $book]);

        return [
            'rating' => $rating !== null ? [
                'id' => $rating->getId(),
                'score' => $rating->getScore(),
                'comment' => $rating->getComment(),
                'createdAt' => $rating->getCreatedAt()->format('c'),
                'updatedAt' => $rating->getUpdatedAt()?->format('c'),
            ] : null,
            'favoriteId' => $favorite?->getId(),
            'readingListId' => $readingList?->getId(),
        ];
    }

    /**
     * Sérialise une liste d'avis pour l'API publique.
     * Ne partage que le pseudo de l'auteur (minimisation des données, RGPD art. 5.1.c).
     * Marque chaque avis d'un flag `isMine` pour que le frontend distingue
     * l'avis de l'utilisateur courant du reste.
     *
     * @param Rating[] $reviews
     * @return array<int, array>
     */
    private function formatReviews(array $reviews, User $currentUser): array
    {
        return array_map(fn(Rating $r) => [
            'id' => $r->getId(),
            'score' => $r->getScore(),
            'comment' => $r->getComment(),
            'createdAt' => $r->getCreatedAt()->format('c'),
            'updatedAt' => $r->getUpdatedAt()?->format('c'),
            'isMine' => $r->getUser()->getId() === $currentUser->getId(),
            'user' => [
                'pseudo' => $r->getUser()->getPseudo(),
            ],
        ], $reviews);
    }
}