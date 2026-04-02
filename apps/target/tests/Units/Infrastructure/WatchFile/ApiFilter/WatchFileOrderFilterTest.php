<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\ApiFilter\WatchFileOrderFilter;
use App\Tests\Utils\EntityUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class WatchFileOrderFilterTest extends TestCase
{
    use EntityUtilsTrait;
    private Security $security;
    private ManagerRegistry&Stub $managerRegistry;
    private QueryBuilder $queryBuilder;
    private QueryNameGeneratorInterface&Stub $queryNameGenerator;
    private EntityManagerInterface&Stub $entityManager;
    private WatchFileOrderFilter $filter;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->managerRegistry = $this->createStub(ManagerRegistry::class);
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata
            ->method('hasField')
            ->willReturnCallback(fn ($field) => \in_array($field, ['name', 'status', 'updatedAt'], true));
        $classMetadata
            ->method('hasAssociation')
            ->willReturn(false);
        $classMetadata
            ->method('getFieldNames')
            ->willReturn(['id', 'name', 'status', 'userObjective', 'updatedAt']);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')
            ->willReturn($classMetadata);
        $this->managerRegistry
            ->method('getManagerForClass')
            ->willReturn($this->entityManager);
        $this->queryBuilder = new QueryBuilder($this->entityManager);
        $this->queryBuilder->from(WatchFile::class, 'w');
        $this->queryBuilder->select('w');
        $this->queryNameGenerator = $this->createStub(QueryNameGeneratorInterface::class);
        $this->buildFilter();
    }

    private function buildFilter(): void
    {
        $this->filter = new WatchFileOrderFilter($this->security, $this->managerRegistry);
    }

    public function testApplyDoesNothingWhenResourceClassIsNotWatchFile(): void
    {
        $context = [
            'filters' => [
                'sort' => [
                    'name' => 'asc',
                ],
            ],
        ];

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            'App\Domain\User\User',
            new Get(),
            $context,
        );

        $this->assertEmpty($this->queryBuilder->getDQLPart('orderBy'));
    }

    public function testApplyDoesNothingWhenNoFiltersInContext(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $context = [];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context,
        );

        // Default ordering should be applied
        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount(2, $orderByPart);
        $this->assertStringContainsString('ORDER BY normalized_name ASC, w.id ASC', $this->queryBuilder->getDQL());
    }

    public function testApplyDoesNothingWhenSortIsNotArray(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $context = [
            'filters' => [
                'sort' => 'invalid',
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context,
        );

        // Default ordering should be applied
        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount(2, $orderByPart);
        $this->assertStringContainsString('ORDER BY normalized_name ASC, w.id ASC', $this->queryBuilder->getDQL());
    }

    #[DataProvider('provideDataSingleSort')]
    public function testApplyBasicOrderSingleParameter(string $field, string $direction, string $orderExpected): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $context = [
            'filters' => [
                'sort' => [
                    $field => $direction,
                ],
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context,
        );

        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount(1, $orderByPart);
        $this->assertStringContainsString($orderExpected, $this->queryBuilder->getDQL());
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function provideDataSingleSort(): array
    {
        return [
            ['name', 'asc', 'ORDER BY w.name ASC'],
            ['name', 'desc', 'ORDER BY w.name DESC'],
            ['status', 'asc', 'ORDER BY w.status ASC'],
            ['status', 'desc', 'ORDER BY w.status DESC'],
            ['updatedAt', 'asc', 'ORDER BY w.updatedAt ASC'],
            ['updatedAt', 'desc', 'ORDER BY w.updatedAt DESC'],
        ];
    }

    /**
     * @param array<string, string> $sortProperties
     */
    #[DataProvider('provideDataCombinedSort')]
    public function testApplyBasicOrderCombinedParameter(
        array $sortProperties,
        int $countOrderExpected,
        string $orderExpected,
    ): void {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $context = [
            'filters' => [
                'sort' => $sortProperties,
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context
        );

        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount($countOrderExpected, $orderByPart);
        $this->assertStringContainsString($orderExpected, $this->queryBuilder->getDQL());
    }

    /**
     * @return list<array{0: array<string, string>, 1: int, 2: string}>
     */
    public static function provideDataCombinedSort(): array
    {
        return [
            // name + status
            [
                [
                    'name' => 'asc',
                    'status' => 'asc',
                ],
                2,
                'ORDER BY w.name ASC, w.status ASC',
            ],
            [
                [
                    'name' => 'desc',
                    'status' => 'desc',
                ],
                2,
                'ORDER BY w.name DESC, w.status DESC',
            ],
            [
                [
                    'name' => 'asc',
                    'status' => 'desc',
                ],
                2,
                'ORDER BY w.name ASC, w.status DESC',
            ],
            [
                [
                    'status' => 'asc',
                    'name' => 'desc',
                ],
                2,
                'ORDER BY w.status ASC, w.name DESC',
            ],
            // name + countAccess
            [
                [
                    'name' => 'asc',
                    'countAccess' => 'asc',
                ],
                2,
                'ORDER BY w.name ASC, count_access ASC',
            ],
            [
                [
                    'name' => 'desc',
                    'countAccess' => 'desc',
                ],
                2,
                'ORDER BY w.name DESC, count_access DESC',
            ],
            [
                [
                    'name' => 'asc',
                    'countAccess' => 'desc',
                ],
                2,
                'ORDER BY w.name ASC, count_access DESC',
            ],
            [
                [
                    'countAccess' => 'asc',
                    'name' => 'desc',
                ],
                2,
                'ORDER BY count_access ASC, w.name DESC',
            ],
            // status + countAccess
            [
                [
                    'status' => 'asc',
                    'countAccess' => 'asc',
                ],
                2,
                'ORDER BY w.status ASC, count_access ASC',
            ],
            [
                [
                    'status' => 'desc',
                    'countAccess' => 'desc',
                ],
                2,
                'ORDER BY w.status DESC, count_access DESC',
            ],
            [
                [
                    'status' => 'asc',
                    'countAccess' => 'desc',
                ],
                2,
                'ORDER BY w.status ASC, count_access DESC',
            ],
            [
                [
                    'countAccess' => 'asc',
                    'status' => 'desc',
                ],
                2,
                'ORDER BY count_access ASC, w.status DESC',
            ],

            // name + updatedAt
            [
                [
                    'name' => 'asc',
                    'updatedAt' => 'asc',
                ],
                2,
                'ORDER BY w.name ASC, w.updatedAt ASC',
            ],
            [
                [
                    'name' => 'desc',
                    'updatedAt' => 'desc',
                ],
                2,
                'ORDER BY w.name DESC, w.updatedAt DESC',
            ],
            [
                [
                    'name' => 'asc',
                    'updatedAt' => 'desc',
                ],
                2,
                'ORDER BY w.name ASC, w.updatedAt DESC',
            ],
            [
                [
                    'updatedAt' => 'asc',
                    'name' => 'desc',
                ],
                2,
                'ORDER BY w.updatedAt ASC, w.name DESC',
            ],

            // status + updatedAt
            [
                [
                    'status' => 'asc',
                    'updatedAt' => 'asc',
                ],
                2,
                'ORDER BY w.status ASC, w.updatedAt ASC',
            ],
            [
                [
                    'status' => 'desc',
                    'updatedAt' => 'desc',
                ],
                2,
                'ORDER BY w.status DESC, w.updatedAt DESC',
            ],
            [
                [
                    'status' => 'asc',
                    'updatedAt' => 'desc',
                ],
                2,
                'ORDER BY w.status ASC, w.updatedAt DESC',
            ],
            [
                [
                    'updatedAt' => 'asc',
                    'status' => 'desc',
                ],
                2,
                'ORDER BY w.updatedAt ASC, w.status DESC',
            ],

            // countAccess + updatedAt
            [
                [
                    'countAccess' => 'asc',
                    'updatedAt' => 'asc',
                ],
                2,
                'ORDER BY count_access ASC, w.updatedAt ASC',
            ],
            [
                [
                    'countAccess' => 'desc',
                    'updatedAt' => 'desc',
                ],
                2,
                'ORDER BY count_access DESC, w.updatedAt DESC',
            ],
            [
                [
                    'countAccess' => 'asc',
                    'updatedAt' => 'desc',
                ],
                2,
                'ORDER BY count_access ASC, w.updatedAt DESC',
            ],
            [
                [
                    'updatedAt' => 'asc',
                    'countAccess' => 'desc',
                ],
                2,
                'ORDER BY w.updatedAt ASC, count_access DESC',
            ],

            // === 3 FIELDS COMBINATIONS ===

            // All ASC
            [
                [
                    'name' => 'asc',
                    'status' => 'asc',
                    'countAccess' => 'asc',
                ],
                3,
                'ORDER BY w.name ASC, w.status ASC, count_access ASC',
            ],
            [
                [
                    'name' => 'asc',
                    'status' => 'asc',
                    'updatedAt' => 'asc',
                ],
                3,
                'ORDER BY w.name ASC, w.status ASC, w.updatedAt ASC',
            ],
            [
                [
                    'name' => 'asc',
                    'countAccess' => 'asc',
                    'updatedAt' => 'asc',
                ],
                3,
                'ORDER BY w.name ASC, count_access ASC, w.updatedAt ASC',
            ],
            [
                [
                    'status' => 'asc',
                    'countAccess' => 'asc',
                    'updatedAt' => 'asc',
                ],
                3,
                'ORDER BY w.status ASC, count_access ASC, w.updatedAt ASC',
            ],

            // All DESC
            [
                [
                    'name' => 'desc',
                    'status' => 'desc',
                    'countAccess' => 'desc',
                ],
                3,
                'ORDER BY w.name DESC, w.status DESC, count_access DESC',
            ],
            [
                [
                    'name' => 'desc',
                    'status' => 'desc',
                    'updatedAt' => 'desc',
                ],
                3,
                'ORDER BY w.name DESC, w.status DESC, w.updatedAt DESC',
            ],
            [
                [
                    'name' => 'desc',
                    'countAccess' => 'desc',
                    'updatedAt' => 'desc',
                ],
                3,
                'ORDER BY w.name DESC, count_access DESC, w.updatedAt DESC',
            ],
            [
                [
                    'status' => 'desc',
                    'countAccess' => 'desc',
                    'updatedAt' => 'desc',
                ],
                3,
                'ORDER BY w.status DESC, count_access DESC, w.updatedAt DESC',
            ],

            // Mixed variations
            [
                [
                    'name' => 'asc',
                    'status' => 'desc',
                    'countAccess' => 'asc',
                ],
                3,
                'ORDER BY w.name ASC, w.status DESC, count_access ASC',
            ],
            [
                [
                    'name' => 'desc',
                    'status' => 'asc',
                    'countAccess' => 'desc',
                ],
                3,
                'ORDER BY w.name DESC, w.status ASC, count_access DESC',
            ],
            [
                [
                    'name' => 'asc',
                    'status' => 'asc',
                    'countAccess' => 'desc',
                ],
                3,
                'ORDER BY w.name ASC, w.status ASC, count_access DESC',
            ],
            [
                [
                    'name' => 'desc',
                    'status' => 'desc',
                    'countAccess' => 'asc',
                ],
                3,
                'ORDER BY w.name DESC, w.status DESC, count_access ASC',
            ],
            [
                [
                    'name' => 'asc',
                    'status' => 'desc',
                    'updatedAt' => 'asc',
                ],
                3,
                'ORDER BY w.name ASC, w.status DESC, w.updatedAt ASC',
            ],
            [
                [
                    'name' => 'desc',
                    'status' => 'asc',
                    'updatedAt' => 'desc',
                ],
                3,
                'ORDER BY w.name DESC, w.status ASC, w.updatedAt DESC',
            ],
            [
                [
                    'name' => 'asc',
                    'updatedAt' => 'asc',
                    'countAccess' => 'desc',
                ],
                3,
                'ORDER BY w.name ASC, w.updatedAt ASC, count_access DESC',
            ],
            [
                [
                    'updatedAt' => 'desc',
                    'name' => 'asc',
                    'countAccess' => 'desc',
                ],
                3,
                'ORDER BY w.updatedAt DESC, w.name ASC, count_access DESC',
            ],
            [
                [
                    'countAccess' => 'asc',
                    'updatedAt' => 'desc',
                    'status' => 'asc',
                ],
                3,
                'ORDER BY count_access ASC, w.updatedAt DESC, w.status ASC',
            ],
            [
                [
                    'updatedAt' => 'asc',
                    'countAccess' => 'desc',
                    'status' => 'desc',
                ],
                3,
                'ORDER BY w.updatedAt ASC, count_access DESC, w.status DESC',
            ],
        ];
    }

    public function testApplyUserFavoritesOrderWhenUserIsLoggedIn(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $user = new User('user', 'user1@example.com', [], 'User 1');
        $context = [
            'filters' => [
                'sort' => [
                    'name' => 'asc',
                ],
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context,
        );

        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount(2, $orderByPart);
        $this->assertStringContainsString('ORDER BY is_favorite DESC, w.name ASC', $this->queryBuilder->getDQL());

        $joinPart = $this->queryBuilder->getDQLPart('join');
        $this->assertTrue(is_countable($joinPart));
        $this->assertCount(1, $joinPart);
        $this->assertStringContainsString(
            '(CASE WHEN uf.id IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN is_favorite',
            $this->queryBuilder->getDQL()
        );
    }

    public function testApplyDefaultOrderForAnonymousUser(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $context = [
            'filters' => [
                'sort' => [],
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context,
        );

        // Default ordering should be applied
        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount(2, $orderByPart);
        $this->assertStringContainsString('ORDER BY normalized_name ASC, w.id ASC', $this->queryBuilder->getDQL());
    }

    public function testApplyDefaultOrderForLoggedUserWithoutExistingOrder(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $context = [
            'filters' => [
                'sort' => [],
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context
        );

        $orderByPart = $this->queryBuilder->getDQLPart('orderBy');
        $this->assertTrue(is_countable($orderByPart));
        $this->assertCount(3, $orderByPart);
        $this->assertStringContainsString(
            'ORDER BY is_favorite DESC, normalized_name ASC, w.id ASC',
            $this->queryBuilder->getDQL()
        );

        $joinPart = $this->queryBuilder->getDQLPart('join');
        $this->assertTrue(is_countable($joinPart));
        $this->assertCount(1, $joinPart);
        $this->assertStringContainsString(
            '(CASE WHEN uf.id IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN is_favorite',
            $this->queryBuilder->getDQL(),
        );
    }

    public function testDefaultOrderUsesUnaccentOnName(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildFilter();
        $context = [
            'filters' => [
                'sort' => [],
            ],
        ];

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->filter->apply(
            $this->queryBuilder,
            $this->queryNameGenerator,
            WatchFile::class,
            new Get(),
            $context,
        );

        $dql = $this->queryBuilder->getDQL();
        $this->assertStringContainsString('UNACCENT(w.name)', $dql, 'Default order should use UNACCENT on name');
    }
}
