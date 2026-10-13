<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Favorite;
use App\Entity\Rating;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private BookRepository $bookRepository,
        private UserRepository $userRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // --- Utilisateur admin (idempotent : ne crée que s'il n'existe pas déjà) ---
        $adminEmail = 'admin@ei-bookapp.test';
        $admin = $this->userRepository->findOneBy(['email' => $adminEmail]);

        if ($admin === null) {
            $admin = new User();
            $admin->setEmail($adminEmail);
            $admin->setPseudo('admin');
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin_password_2026'));
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($admin);
        }

        // --- Utilisateurs de test pour les affinités (skip si les livres cibles ne sont pas en base) ---
        $googleStory = $this->bookRepository->findOneBy(['googleBooksId' => 'zyTCAlFPjgYC']);
        $pagnol = $this->bookRepository->findOneBy(['googleBooksId' => 'l44kAQAAMAAJ']);
        $camus = $this->bookRepository->findOneBy(['googleBooksId' => '-uyRAAAAIAAJ']);
        $caTome2 = $this->bookRepository->findOneBy(['googleBooksId' => 'tGpSsf4GvToC']);

        if (!$googleStory || !$pagnol || !$camus || !$caTome2) {
            $manager->flush();
            return; // On sort proprement, l'admin est créé, c'est l'essentiel
        }

        $this->createUserIfMissing($manager, 'alice@fixture.test', 'alice_lit', '2026-07-01', function(User $alice) use ($manager, $googleStory, $pagnol, $camus) {
            $this->addFavorite($manager, $alice, $googleStory);
            $this->addFavorite($manager, $alice, $pagnol);
            $this->addRating($manager, $alice, $camus, 5);
        });

        $this->createUserIfMissing($manager, 'marc@fixture.test', 'marc_reads', '2026-07-10', function(User $marc) use ($manager, $googleStory, $caTome2) {
            $this->addFavorite($manager, $marc, $googleStory);
            $this->addFavorite($manager, $marc, $caTome2);
        });

        $this->createUserIfMissing($manager, 'lea@fixture.test', 'lea_pages', '2026-07-20', function(User $lea) use ($manager, $camus) {
            $this->addRating($manager, $lea, $camus, 4);
        });

        $manager->flush();
    }

    private function createUserIfMissing(ObjectManager $manager, string $email, string $pseudo, string $createdAt, callable $onCreate): void
    {
        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            return;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $user->setCreatedAt(new \DateTimeImmutable($createdAt));
        $manager->persist($user);

        $onCreate($user);
    }

    private function addFavorite(ObjectManager $manager, User $user, Book $book): void
    {
        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setBook($book);
        $favorite->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($favorite);
    }

    private function addRating(ObjectManager $manager, User $user, Book $book, int $score): void
    {
        $rating = new Rating();
        $rating->setUser($user);
        $rating->setBook($book);
        $rating->setScore($score);
        $rating->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($rating);
    }
}