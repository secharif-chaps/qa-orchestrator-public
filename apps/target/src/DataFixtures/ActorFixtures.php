<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ActorFixtures extends Fixture implements DependentFixtureInterface
{
    public const ACTOR_REFERENCE = 'actor_';

    public function getDependencies(): array
    {
        return [OrganisationFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $organisation = $this->getReference(OrganisationFixtures::ORGANISATION_REFERENCE, Organisation::class);
        $companies = [
            'Acme Corp',
            'Globex Corporation',
            'Initech',
            'Umbrella Corporation',
            'Wayne Enterprises',
            'Stark Industries',
            'Wonka Industries',
            'Cyberdyne Systems',
            'Oscorp Industries',
            'Aperture Science',
            'Tyrell Corporation',
            'Weyland-Yutani',
            'Massive Dynamic',
            'Soylent Corporation',
            'Virtucon Industries',
            // Pharmacy & Cosmetics sector actors
            'Servier',
            'Groupe Rocher',
            'SVR',
            'Bio Mérieux',
            'Sanofi',
            'ISDIN',
            'Estée Lauder',
            'L\'Oréal',
            'L\'Occitane',
            'Ipsen',
        ];

        $domains = [
            'acme.com',
            'globex.com',
            'initech.com',
            'umbrella.com',
            'wayneenterprises.com',
            'starkindustries.com',
            'wonka.com',
            'cyberdyne.com',
            'oscorp.com',
            'aperture.com',
            'tyrell.com',
            'weylandyutani.com',
            'massivedynamic.com',
            'soylent.com',
            'virtucon.com',
            // Pharmacy & Cosmetics sector domains
            'servier.com',
            'groupe-rocher.com',
            'laboratoiresvr.com',
            'biomerieux.com',
            'sanofi.com',
            'isdin.com',
            'elcompanies.com',
            'loreal.com',
            'loccitane.com',
            'ipsen.com',
        ];

        foreach ($companies as $i => $label) {
            $actor = new Actor($label, $organisation);
            $actor->setPrimaryDomain($domains[$i]);
            $manager->persist($actor);
            $this->addReference(self::ACTOR_REFERENCE . $i, $actor);
        }

        $manager->flush();
    }
}
