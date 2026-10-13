<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Book;
use App\Service\SimilarUsersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SimilarUserController extends AbstractController
{
    #[Route('/api/similar-users', name: 'similar_users_list', methods: ['GET'])]
    public function listSimilarUsers(
        #[CurrentUser] User $user,
        SimilarUsersService $similarUsersService
    ): JsonResponse {
        $similarUsers = $similarUsersService->findSimilarUsers($user, 5);

        $data = array_map(fn(array $similarUser) => [
            'user' => [
                'id' => $similarUser['user']->getId(),
                'pseudo' => $similarUser['user']->getPseudo(),
            ],
            'commonBooks' => array_map(fn(Book $book) => [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'googleBooksId' => $book->getGoogleBooksId(),
            ], $similarUser['commonBooks']),
        ], $similarUsers);

        return $this->json($data);
    }
}
