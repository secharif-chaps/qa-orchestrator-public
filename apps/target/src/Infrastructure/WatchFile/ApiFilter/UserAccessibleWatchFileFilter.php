<?php

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class UserAccessibleWatchFileFilter extends AbstractFilter
{
    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        private readonly Security $security,
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $loggedUser = $this->security->getUser();
        if (!$loggedUser instanceof User) {
            return;
        }

        if (WatchFile::class !== $resourceClass) {
            return;
        }

        // Admin users have unrestricted access to all watch files
        $roles = $loggedUser->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('admin', $roles, true)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->innerJoin(\sprintf('%s.watchFileUsers', $alias), 'fu')
            ->andWhere('fu.user = :user')
            ->setParameter('user', $loggedUser);
    }

    /**
     * @param string               $value
     * @param array<string, mixed> $context
     */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        throw new \LogicException(
            'This method should not be called as this is a global filter only meant to be applied once'
        );
    }

    public function getDescription(string $resourceClass): array
    {
        return []; // No documentation needed for this filter
    }
}
