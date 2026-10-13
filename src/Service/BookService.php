<?php

namespace App\Service;

use App\Entity\Book;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BookService
{
    public function __construct(
        private BookRepository $bookRepository,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private string $googleBooksApiKey
    ) {}

    public function findOrCreateByGoogleBooksId(string $googleBooksId): Book
    {
        $book = $this->bookRepository->findOneBy(['googleBooksId' => $googleBooksId]);
        if ($book !== null) {
            return $book;
        }

        $response = $this->httpClient->request(
            'GET',
            'https://www.googleapis.com/books/v1/volumes/' . $googleBooksId,
            ['query' => ['key' => $this->googleBooksApiKey]]
        );
        $data = $response->toArray();
        $info = $data['volumeInfo'] ?? [];

        $book = new Book();
        $book->setGoogleBooksId($googleBooksId);
        $book->setTitle($info['title'] ?? 'Sans titre');
        $book->setAuthor(isset($info['authors']) ? implode(', ', $info['authors']) : null);
        $book->setCoverUrl($this->forceHttps($info['imageLinks']['thumbnail'] ?? null));
        $book->setDescription($info['description'] ?? null);
        $book->setGenre(isset($info['categories'][0]) ? $info['categories'][0] : null);
        $book->setPublishedDate($info['publishedDate'] ?? null);
        $book->setPageCount($info['pageCount'] ?? null);

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    /**
     * L'API Google Books renvoie ses URL de miniatures en HTTP.
     * Servies dans une page HTTPS, elles déclenchent un avertissement
     * Mixed Content : les navigateurs récents surclassent la requête
     * automatiquement, les plus anciens bloquent purement l'image.
     * On normalise donc le protocole à l'enregistrement plutôt qu'à
     * l'affichage — la donnée est stockée propre une fois pour toutes,
     * sans traitement à répéter côté frontend.
     */
    public function forceHttps(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return str_replace('http://', 'https://', $url);
    }
}