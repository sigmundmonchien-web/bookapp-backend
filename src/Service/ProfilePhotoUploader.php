<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

class ProfilePhotoUploader
{
    public function __construct(
        private string $profilePhotosDirectory,
        private int $profilePhotoMaxSize,
        private array $profilePhotoAllowedMimeTypes,
    ) {}

    /**
     * Valide et enregistre une photo de profil.
     * Retourne le nom du fichier stocké (à persister en base).
     *
     * @throws \InvalidArgumentException si le fichier est invalide
     * @throws FileException si l'écriture échoue
     */
    public function upload(UploadedFile $file): string
    {
        // Validation de la taille
        if ($file->getSize() > $this->profilePhotoMaxSize) {
            throw new \InvalidArgumentException(
                sprintf('Le fichier dépasse la taille maximale autorisée (%d Mo).',
                    $this->profilePhotoMaxSize / 1024 / 1024)
            );
        }

        // Validation du type MIME
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->profilePhotoAllowedMimeTypes, true)) {
            throw new \InvalidArgumentException(
                'Type de fichier non autorisé. Formats acceptés : JPG, PNG, WebP.'
            );
        }

        // Génération d'un nom UUID unique
        $extension = $file->guessExtension() ?? 'jpg';
        $filename = Uuid::v4()->toRfc4122() . '.' . $extension;

        // Déplacement du fichier temporaire vers le dossier final
        try {
            $file->move($this->profilePhotosDirectory, $filename);
        } catch (FileException $e) {
            throw new FileException('Impossible d\'enregistrer le fichier.', 0, $e);
        }

        return $filename;
    }

    /**
     * Supprime physiquement un fichier de photo.
     * Silencieux si le fichier n'existe pas (idempotent).
     */
    public function delete(?string $filename): void
    {
        if ($filename === null) {
            return;
        }

        $path = $this->profilePhotosDirectory . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}