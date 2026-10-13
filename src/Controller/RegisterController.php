<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        // Vérification des champs obligatoires
        if (empty($data['email']) || empty($data['password']) || empty($data['pseudo'])) {
            return $this->json(['error' => 'Email, password et pseudo sont obligatoires'], 400);
        }

        // Vérification du consentement RGPD.
        // Défense en profondeur : le frontend bloque déjà le bouton
        // d'inscription si la case n'est pas cochée, mais un client
        // malveillant peut envoyer une requête directe qui contourne
        // le formulaire. La vérité juridique doit être côté serveur —
        // sans consentement documenté, pas de traitement de données
        // personnelles autorisé par l'article 6.1.a du RGPD.
        // On utilise une comparaison stricte à `true` : ni une chaîne
        // "true", ni 1, ni "on" ne sont acceptés. Le frontend doit
        // envoyer un vrai booléen JSON.
        if (($data['acceptedPrivacyPolicy'] ?? false) !== true) {
            return $this->json(
                ['error' => 'Vous devez accepter la politique de confidentialité pour créer un compte.'],
                400
            );
        }

        // Vérifier si l'email existe déjà
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPseudo($data['pseudo']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'])
        );
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'Utilisateur créé avec succès',
            'pseudo' => $user->getPseudo(),
            'email' => $user->getEmail(),
        ], 201);
    }
}