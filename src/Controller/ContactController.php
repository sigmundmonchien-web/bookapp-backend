<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private MailerInterface $mailer,
        private string $contactEmail,
    ) {}

    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Corps de requête invalide.'], 400);
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($subject === '' || $message === '') {
            return new JsonResponse(
                ['error' => 'Le sujet et le message sont obligatoires.'],
                400
            );
        }

        if (mb_strlen($subject) > 100) {
            return new JsonResponse(
                ['error' => 'Le sujet ne peut pas dépasser 100 caractères.'],
                400
            );
        }

        if (mb_strlen($message) > 2000) {
            return new JsonResponse(
                ['error' => 'Le message ne peut pas dépasser 2000 caractères.'],
                400
            );
        }

        // L'identité de l'expéditeur vient du JWT, jamais du corps de la requête.
        // Un utilisateur ne peut donc pas se faire passer pour quelqu'un d'autre
        // en manipulant le formulaire — la route étant protégée par le firewall,
        // seul un compte authentifié peut écrire.
        $body = sprintf(
            "Message envoyé depuis EI BookApp\n\n"
            . "De : %s (%s)\n"
            . "ID utilisateur : %d\n"
            . "Date : %s\n\n"
            . "---\n\n"
            . "Sujet : %s\n\n"
            . "%s\n",
            $user->getPseudo(),
            $user->getEmail(),
            $user->getId(),
            (new \DateTimeImmutable())->format('d/m/Y H:i'),
            $subject,
            $message
        );

        $email = (new Email())
            ->to($this->contactEmail)
            // replyTo et non from : l'expéditeur SMTP doit rester l'adresse
            // authentifiée du serveur, sinon Alwaysdata refuse l'envoi.
            // replyTo permet malgré tout de répondre directement à l'utilisateur
            // d'un clic depuis le webmail.
            ->replyTo($user->getEmail())
            ->subject('[BookApp] ' . $subject)
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // On ne renvoie jamais le détail de l'erreur SMTP au client :
            // il pourrait révéler la configuration du serveur de mail.
            return new JsonResponse(
                ['error' => "L'envoi a échoué. Réessaie dans quelques instants."],
                503
            );
        }

        return new JsonResponse([
            'message' => 'Message envoyé. Je te répondrai dès que possible.',
        ], 200);
    }
}