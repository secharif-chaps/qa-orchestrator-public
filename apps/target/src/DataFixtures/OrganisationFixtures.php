<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Organisation\Organisation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class OrganisationFixtures extends Fixture
{
    public const ORGANISATION_REFERENCE = 'organisation_default';

    public function load(ObjectManager $manager): void
    {
        $organisation = new Organisation('ChapsMind Default', 'chapsmind-default-org');
        $manager->persist($organisation);
        $manager->flush();

        $this->addReference(self::ORGANISATION_REFERENCE, $organisation);
    }
}
