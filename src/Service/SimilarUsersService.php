<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Entity\User;
use App\Repository\BookRepository;

class SimilarUsersService
{
    public function __construct(
        private UserRepository $userRepository,
        private BookRepository $bookRepository
    ) {
    }

    public function findSimilarUsers(User $currentUser, int $limit = 5): array
    {
      $myBookIds = $this->userRepository->findLikedBookIds($currentUser->getId());

      if (empty($myBookIds)) {
          return [];
      } 

      $matches = $this->userRepository->findLikeMatchesForBooks($myBookIds, $currentUser->getId());

       $groupedMatches = [];
       foreach ($matches as $match) {
           $userId = (int) $match['userId'];
           $bookId = (int) $match['bookId'];

           if (!isset($groupedMatches[$userId])) {
               $groupedMatches[$userId] = [];
           }
           
           if (!in_array($bookId, $groupedMatches[$userId], true)) {
               $groupedMatches[$userId][] = $bookId;
           }        
       }

       $rankedMatches = [];
       foreach ($groupedMatches as $userId => $bookIds) {
           $rankedMatches[] = [
               'userId' => $userId,
               'commonBookCount' => count($bookIds),
               'commonBookIds' => $bookIds,
           ];
       }    

       usort($rankedMatches, fn($a, $b) => $b['commonBookCount'] <=> $a['commonBookCount']);

       $topMatches = array_slice($rankedMatches, 0, $limit);    
       $result = [];
       foreach ($topMatches as $match) {
           $user = $this->userRepository->find($match['userId']);
           if ($user === null) {
                continue;   
           }

           $books = $this->bookRepository->findBy(['id' => $match['commonBookIds']]);
           $result[] = [
               'user' => $user,
               'commonBooks' => $books,
               'commonBookCount' => $match['commonBookCount'],
           ];
       }
       return $result;
    }   
}