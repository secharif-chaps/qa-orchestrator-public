<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Domain\Document\DocumentValidation;
use App\Domain\Document\DocumentValidationGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DocumentValidationDoctrineGateway implements DocumentValidationGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(DocumentValidation $documentValidation): void
    {
        $this->entityManager->persist($documentValidation);
        $this->entityManager->flush();
    }

    /**
     * @param array<int, DocumentValidation> $documentValidations
     */
    public function saveBulk(array $documentValidations): void
    {
        if (empty($documentValidations)) {
            return;
        }

        foreach ($documentValidations as $documentValidation) {
            $this->entityManager->persist($documentValidation);
        }

        $this->entityManager->flush();
    }
}
