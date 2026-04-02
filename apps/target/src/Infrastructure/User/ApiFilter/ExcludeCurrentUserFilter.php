<?php

declare(strict_types=1);

namespace App\Infrastructure\User\ApiFilter;

use ApiPlatform\Doctrine\Common\Filter\BooleanFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Domain\User\User;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class ExcludeCurrentUserFilter extends AbstractFilter
{
    use BooleanFilterTrait;

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
     * @param bool|int|float|string $value
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
        if ('excludeCurrentUser' !== $property) {
            return;
        }

        $value = $this->normalizeValue($value, $property);
        if (null === $value) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('This filter can only be applied when a user is authenticated.');
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere($queryBuilder->expr()->neq("$alias.id", ':excludeUser'))
            ->setParameter('excludeUser', $user);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'excludeCurrentUser' => [
                'type' => 'bool',
                'required' => false,
                'openapi' => new Parameter(
                    'excludeCurrentUser',
                    'query',
                    'Exclude the current authenticated user from the results.',
                    schema: [
                        'type' => 'boolean',
                        'default' => false,
                    ]
                ),
            ],
        ];
    }
}
