<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Organisation\Doctrine;

use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantAwareInterface;
use App\Infrastructure\Organisation\Doctrine\TenantFilter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantFilter::class)]
class TenantFilterTest extends TestCase
{
    public function testAddsConstraintForTenantAwareEntity(): void
    {
        $filter = $this->createTenantFilter();
        $filter->setParameter('organisation_id', 'org-uuid');

        $metadata = $this->createMetadataFor(TenantAwareStub::class);

        $result = $filter->addFilterConstraint($metadata, 't0');

        self::assertSame("t0.organisation_id = 'org-uuid'", $result);
    }

    public function testReturnsEmptyForNonTenantAwareEntity(): void
    {
        $filter = $this->createTenantFilter();

        $metadata = $this->createMetadataFor(NonTenantAwareStub::class);

        $result = $filter->addFilterConstraint($metadata, 't0');

        self::assertSame('', $result);
    }

    private function createTenantFilter(): TenantFilter
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('quote')
->willReturnCallback(static fn (string $value): string => "'" . $value . "'");

        $filterCollection = $this->createStub(FilterCollection::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getFilters')
->willReturn($filterCollection);
        $em->method('getConnection')
->willReturn($connection);

        return new TenantFilter($em);
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function createMetadataFor(string $class): ClassMetadata
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata = new ClassMetadata($class);
        $metadata->reflClass = new \ReflectionClass($class);

        return $metadata;
    }
}

/**
 * @internal
 */
class TenantAwareStub implements TenantAwareInterface
{
    public function getOrganisation(): Organisation
    {
        return new Organisation('test', 'test');
    }
}

/**
 * @internal
 */
class NonTenantAwareStub
{
}
