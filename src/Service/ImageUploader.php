<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageUploader
{
    public function __construct(
        private readonly string $targetDirectory,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?: 'bin';
        $fileName = uniqid('', true) . '.' . $extension;

        try {
            $file->move($this->targetDirectory, $fileName);
        } catch (FileException $exception) {
            throw new \RuntimeException('Échec de l\'upload du fichier.', 0, $exception);
        }

        return $fileName;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
