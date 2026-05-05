<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Doctrine;

use Doctrine\ORM\EntityRepository;

/**
 * Test double for {@see EntityRepository}: bypasses the parent constructor
 * (which requires `EntityManager` + `ClassMetadata`) and answers `findBy()`
 * with a caller-provided list of entities. Records every `findBy()` call
 * so tests can assert on the criteria passed.
 *
 * @template T of object
 *
 * @extends EntityRepository<T>
 */
class NullEntityRepository extends EntityRepository
{
    /** @var list<array<string, mixed>> */
    private array $findByCriteriaCalls = [];

    /**
     * @param list<T> $entities
     */
    public function __construct(
        private array $entities = [],
        private ?\Throwable $findByThrows = null,
    ) {
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $this->findByCriteriaCalls[] = $criteria;

        if (null !== $this->findByThrows) {
            throw $this->findByThrows;
        }

        return $this->entities;
    }

    /** @return list<array<string, mixed>> */
    public function getFindByCriteriaCalls(): array
    {
        return $this->findByCriteriaCalls;
    }
}
