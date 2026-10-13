<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Repository\RatingRepository;
use App\Repository\ReadingListRepository;
use App\Service\ProfilePhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MeController extends AbstractController
{
    public function __construct(
        private FavoriteRepository $favoriteRepository,
        private RatingRepository $ratingRepository,
        private ReadingListRepository $readingListRepository,
        private EntityManagerInterface $entityManager,
        private ProfilePhotoUploader $photoUploader,
        private string $profilePhotosPublicPath,
    ) {}

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse($this->buildProfilePayload($user));
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Corps de requête invalide.'], 400);
        }

        // Seul le pseudo est modifiable pour l'instant.
        // La structure PATCH permet d'ajouter d'autres champs
        // (email, préférences...) sans casser l'API existante.
        if (array_key_exists('pseudo', $data)) {
            $pseudo = trim((string) $data['pseudo']);

            if (strlen($pseudo) < 3 || strlen($pseudo) > 30) {
                return new JsonResponse(
                    ['error' => 'Le pseudo doit contenir entre 3 et 30 caractères.'],
                    400
                );
            }

            $user->setPseudo($pseudo);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->buildProfilePayload($user));
    }

    #[Route('/api/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function deleteAccount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Suppression du fichier photo si présent.
        // Le ON DELETE CASCADE en base ne concerne que les enregistrements
        // (ratings, favoris, reading list) ; les fichiers sur disque
        // sont hors du périmètre de Doctrine et doivent être gérés à la main.
        $this->photoUploader->delete($user->getPhotoFilename());

        // Suppression du user.
        // Grâce au ON DELETE CASCADE mis en place lors de la migration
        // Version20260904202512, toutes les interactions liées (ratings,
        // favoris, reading list) partent automatiquement avec le user
        // au niveau base — atomicité garantie par le moteur InnoDB.
        // C'est la matérialisation technique du droit à l'effacement
        // prévu à l'article 17 du RGPD.
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        // 204 No Content : opération réussie, aucun corps de réponse.
        // Le frontend doit invalider son JWT en local et rediriger
        // vers /login — sinon le token pointerait vers un user inexistant
        // et les requêtes suivantes échoueraient avec un 401.
        return new JsonResponse(null, 204);
    }

    #[Route('/api/me/photo', name: 'api_me_photo_upload', methods: ['POST'])]
    public function uploadPhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $file = $request->files->get('photo');

        if ($file === null) {
            return new JsonResponse(
                ['error' => 'Aucun fichier reçu. Envoyez un fichier avec la clé "photo".'],
                400
            );
        }

        try {
            $newFilename = $this->photoUploader->upload($file);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (FileException $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de l\'enregistrement du fichier.'],
                500
            );
        }

        // Suppression de l'ancienne photo si elle existe
        $this->photoUploader->delete($user->getPhotoFilename());

        // Mise à jour de la base
        $user->setPhotoFilename($newFilename);
        $this->entityManager->flush();

        return new JsonResponse([
            'photoUrl' => $this->buildPhotoUrl($newFilename),
        ]);
    }

    #[Route('/api/me/photo', name: 'api_me_photo_delete', methods: ['DELETE'])]
    public function deletePhoto(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->photoUploader->delete($user->getPhotoFilename());
        $user->setPhotoFilename(null);
        $this->entityManager->flush();

        return new JsonResponse(null, 204);
    }

    private function buildProfilePayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'pseudo' => $user->getPseudo(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'photoUrl' => $this->buildPhotoUrl($user->getPhotoFilename()),
            'counts' => [
                'favorites' => $this->favoriteRepository->count(['user' => $user]),
                'ratings' => $this->ratingRepository->count(['user' => $user]),
                'readingList' => $this->readingListRepository->count(['user' => $user]),
            ],
        ];
    }

    private function buildPhotoUrl(?string $filename): ?string
    {
        if ($filename === null) {
            return null;
        }
        return $this->profilePhotosPublicPath . '/' . $filename;
    }
}