<?php

namespace App\Controller;

use App\Entity\ReadingList;
use App\Entity\User;
use App\Repository\ReadingListRepository;
use App\Service\BookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/reading-list')]
final class ReadingListController extends AbstractController
{
    #[Route('', name: 'reading_list_create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] User $user,
        BookService $bookService,
        ReadingListRepository $readingListRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['googleBooksId'])) {
            return $this->json(['error' => 'googleBooksId est requis'], 400);
        }

        try {
            $book = $bookService->findOrCreateByGoogleBooksId($data['googleBooksId']);
        } catch (\Throwable) {
            return $this->json(['error' => 'Impossible de récupérer le livre depuis Google Books'], 502);
        }

        $existingEntry = $readingListRepository->findOneBy(['user' => $user, 'book' => $book]);
        if ($existingEntry !== null) {
            return $this->json(['error' => 'Le livre est déjà dans la liste de lecture'], 400);
        }

        $entry = new ReadingList();
        $entry->setUser($user);
        $entry->setBook($book);
        $entry->setCreatedAt(new \DateTimeImmutable());

        $em->persist($entry);
        $em->flush();

        return $this->json([
            'id' => $entry->getId(),
            'createdAt' => $entry->getCreatedAt()->format('c'),
            'book' => [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'description' => $book->getDescription(),
                'coverUrl' => $book->getCoverUrl(),
            ],
        ], 201);
    }

    #[Route('', name: 'reading_list_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        ReadingListRepository $readingListRepository
    ): JsonResponse {
        $entries = $readingListRepository->findAllForUserWithBook($user);

        $data = array_map(function (ReadingList $entry) {
            return [
                'id' => $entry->getId(),
                'createdAt' => $entry->getCreatedAt()->format('c'),
                'book' => [
                    'id' => $entry->getBook()->getId(),
                    'title' => $entry->getBook()->getTitle(),
                    'author' => $entry->getBook()->getAuthor(),
                    'description' => $entry->getBook()->getDescription(),
                    'coverUrl' => $entry->getBook()->getCoverUrl(),
                ],
            ];
        }, $entries);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'reading_list_delete', methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
        ReadingListRepository $readingListRepository,
        EntityManagerInterface $em,
        int $id
    ): JsonResponse {
        $entry = $readingListRepository->find($id);

        if (!$entry || $entry->getUser() !== $user) {
            return $this->json(['error' => 'Entrée non trouvée ou accès refusé'], 404);
        }

        $em->remove($entry);
        $em->flush();

        return $this->json(['message' => 'Entrée supprimée avec succès'], 200);
    }
}