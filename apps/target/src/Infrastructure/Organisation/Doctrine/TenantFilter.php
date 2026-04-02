<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\Doctrine;

use App\Domain\Organisation\TenantAwareInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (null === $targetEntity->reflClass || !$targetEntity->reflClass->implementsInterface(
            TenantAwareInterface::class
        )) {
            return '';
        }

        return \sprintf('%s.organisation_id = %s', $targetTableAlias, $this->getParameter('organisation_id'));
    }
}
