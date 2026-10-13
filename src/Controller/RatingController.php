<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Entity\User;
use App\Repository\RatingRepository;
use App\Service\BookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/ratings')]
class RatingController extends AbstractController
{
    private const COMMENT_MAX_LENGTH = 500;

    #[Route('', name: 'ratings_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] User $user,
        BookService $bookService,
        RatingRepository $ratingRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['googleBooksId'], $data['score'])) {
            return $this->json(['error' => 'googleBooksId et score sont requis'], 400);
        }

        $score = (int) $data['score'];
        if ($score < 1 || $score > 5) {
            return $this->json(['error' => 'La note doit être comprise entre 1 et 5'], 400);
        }

        // Validation du champ comment (optionnel)
        $commentProvided = array_key_exists('comment', $data);
        $comment = null;
        if ($commentProvided && $data['comment'] !== null) {
            $comment = trim((string) $data['comment']);
            if ($comment === '') {
                $comment = null;
            } elseif (mb_strlen($comment) > self::COMMENT_MAX_LENGTH) {
                return $this->json(
                    ['error' => sprintf('L\'avis ne peut pas dépasser %d caractères', self::COMMENT_MAX_LENGTH)],
                    400
                );
            }
        }

        try {
            $book = $bookService->findOrCreateByGoogleBooksId($data['googleBooksId']);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Impossible de récupérer le livre depuis Google Books'], 502);
        }

        $rating = $ratingRepository->findOneBy(['user' => $user, 'book' => $book]);
        $isNew = $rating === null;

        if ($isNew) {
            $rating = new Rating();
            $rating->setUser($user);
            $rating->setBook($book);
            $rating->setCreatedAt(new \DateTimeImmutable());
        } else {
            $rating->setUpdatedAt(new \DateTimeImmutable());
        }

        $rating->setScore($score);
        if ($commentProvided) {
            $rating->setComment($comment);
        }

        $em->persist($rating);
        $em->flush();

        return $this->json([
            'id' => $rating->getId(),
            'score' => $rating->getScore(),
            'comment' => $rating->getComment(),
            'createdAt' => $rating->getCreatedAt()->format('c'),
            'updatedAt' => $rating->getUpdatedAt()?->format('c'),
            'book' => [
                'id' => $book->getId(),
                'googleBooksId' => $book->getGoogleBooksId(),
                'title' => $book->getTitle(),
            ],
        ], $isNew ? 201 : 200);
    }

    #[Route('', name: 'ratings_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        RatingRepository $ratingRepository
    ): JsonResponse {
        $ratings = $ratingRepository->findAllForUserWithBook($user);

        $data = array_map(fn(Rating $r) => [
            'id' => $r->getId(),
            'score' => $r->getScore(),
            'comment' => $r->getComment(),
            'createdAt' => $r->getCreatedAt()->format('c'),
            'updatedAt' => $r->getUpdatedAt()?->format('c'),
            'book' => [
                'id' => $r->getBook()->getId(),
                'googleBooksId' => $r->getBook()->getGoogleBooksId(),
                'title' => $r->getBook()->getTitle(),
                'author' => $r->getBook()->getAuthor(),
                'coverUrl' => $r->getBook()->getCoverUrl(),
            ],
        ], $ratings);

        return $this->json($data);
    }
}