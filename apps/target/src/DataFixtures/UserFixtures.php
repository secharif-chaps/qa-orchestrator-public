<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
    public const BASIL_USER_REFERENCE = 'basil-user';

    public function load(ObjectManager $manager): void
    {
        // basil user
        $basil = UserFactory::new()
            ->defaultBasilUser()
            ->create();

        $manager->persist($basil);
        $this->addReference(self::BASIL_USER_REFERENCE, $basil);

        // other users
        $usersData = [
            ['e258ebaa-e0b6-4c59-afc6-d8341698f943', 'Alice', 'Martin', 'alice.martin', 'alice.martin@chapsvision.com'],
            ['b2a2e2c1-2a2b-4c2d-8e2f-2a2b2c2d2e2f', 'Bob', 'Smith', 'bob.smith', 'bob.smith@chapsvision.com'],
            [
                'b3a3e3c2-3a3b-4c3d-8e3f-3a3b3c3d3e3f',
                'Charlie',
                'Johnson',
                'charlie.johnson',
                'charlie.johnson@chapsvision.com',
            ],
            [
                'b4a4e4c3-4a4b-4c4d-8e4f-4a4b4c4d4e4f',
                'Diana',
                'Williams',
                'diana.williams',
                'diana.williams@chapsvision.com',
            ],
            ['b5a5e5c4-5a5b-4c5d-8e5f-5a5b5c5d5e5f', 'Ethan', 'Brown', 'ethan.brown', 'ethan.brown@chapsvision.com'],
            ['b6a6e6c5-6a6b-4c6d-8e6f-6a6b6c6d6e6f', 'Fiona', 'Jones', 'fiona.jones', 'fiona.jones@chapsvision.com'],
            [
                'b7a7e7c6-7a7b-4c7d-8e7f-7a7b7c7d7e7f',
                'George',
                'Garcia',
                'george.garcia',
                'george.garcia@chapsvision.com',
            ],
            [
                'b8a8e8c7-8a8b-4c8d-8e8f-8a8b8c8d8e8f',
                'Hannah',
                'Martinez',
                'hannah.martinez',
                'hannah.martinez@chapsvision.com',
            ],
            ['b9a9e9c8-9a9b-4c9d-8e9f-9a9b9c9d9e9f', 'Ian', 'Lopez', 'ian.lopez', 'ian.lopez@chapsvision.com'],
            [
                'b0b0e0c9-0b0b-4c0d-8e0f-0b0b0c0d0e0f',
                'Julia',
                'Gonzalez',
                'julia.gonzalez',
                'julia.gonzalez@chapsvision.com',
            ],
            ['f47ac10b-58cc-4372-a567-0e02b2c3d479', 'Kevin', 'Clark', 'kevin.clark', 'kevin.clark@chapsvision.com'],
            ['6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'Laura', 'Lewis', 'laura.lewis', 'laura.lewis@chapsvision.com'],
            [
                'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'Michael',
                'Walker',
                'michael.walker',
                'michael.walker@chapsvision.com',
            ],
            ['550e8400-e29b-41d4-a716-446655440000', 'Nina', 'Hall', 'nina.hall', 'nina.hall@chapsvision.com'],
            ['c9bf9e57-1685-4c89-bafb-ff5af830be8a', 'Oscar', 'Allen', 'oscar.allen', 'oscar.allen@chapsvision.com'],
            ['6c4f8b2a-3d1e-4f7a-8b2c-5e9f1a3d6c8b', 'Paula', 'Young', 'paula.young', 'paula.young@chapsvision.com'],
            ['7d5f9c3b-4e2f-4a8b-9c3d-6f0a2b4e7d9c', 'Quentin', 'King', 'quentin.king', 'quentin.king@chapsvision.com'],
            [
                '8e6a0d4c-5f3a-4b9c-0d4e-7a1b3c5f8e0a',
                'Rachel',
                'Wright',
                'rachel.wright',
                'rachel.wright@chapsvision.com',
            ],
            ['9f7b1e5d-6a4b-4c0d-1e5f-8b2c4d6a9f1b', 'Samuel', 'Lopez', 'samuel.lopez', 'samuel.lopez@chapsvision.com'],
            ['0a8c2f6e-7b5c-4d1e-2f6a-9c3d5e7b0a8c', 'Tina', 'Hill', 'tina.hill', 'tina.hill@chapsvision.com'],
            ['1b9d3a7f-8c6d-4e2f-3a7b-0d4e6f8c1b9d', 'Uma', 'Scott', 'uma.scott', 'uma.scott@chapsvision.com'],
            ['2c0e4b8a-9d7e-4f3a-4b8c-1e5f7a9d2c0e', 'Victor', 'Green', 'victor.green', 'victor.green@chapsvision.com'],
            ['3d1f5c9b-0e8f-4a4b-5c9d-2f6a8b0e3d1f', 'Wendy', 'Baker', 'wendy.baker', 'wendy.baker@chapsvision.com'],
            [
                '4e2a6d0c-1f9a-4b5c-6d0e-3a7b9c1f4e2a',
                'Xavier',
                'Nelson',
                'xavier.nelson',
                'xavier.nelson@chapsvision.com',
            ],
            ['5f3b7e1d-2a0b-4c6d-7e1f-4b8c0d2a5f3b', 'Yara', 'Carter', 'yara.carter', 'yara.carter@chapsvision.com'],
            [
                '6a4c8f2e-3b1c-4d7e-8f2a-5c9d1e3b6a4c',
                'Zach',
                'Mitchell',
                'zach.mitchell',
                'zach.mitchell@chapsvision.com',
            ],
            ['7b5d9a3f-4c2d-4e8f-9a3b-6d0e2f4c7b5d', 'Aaron', 'Perez', 'aaron.perez', 'aaron.perez@chapsvision.com'],
            [
                '8c6e0b4a-5d3e-4f9a-0b4c-7e1f3a5d8c6e',
                'Bella',
                'Roberts',
                'bella.roberts',
                'bella.roberts@chapsvision.com',
            ],
            ['9d7f1c5b-6e4f-4a0b-1c5d-8f2a4b6e9d7f', 'Carl', 'Turner', 'carl.turner', 'carl.turner@chapsvision.com'],
            [
                '0e8a2d6c-7f5a-4b1c-2d6e-9a3b5c7f0e8a',
                'Dana',
                'Phillips',
                'dana.phillips',
                'dana.phillips@chapsvision.com',
            ],
            ['1f9b3e7d-8a6b-4c2d-3e7f-0b4c6d8a1f9b', 'Eli', 'Campbell', 'eli.campbell', 'eli.campbell@chapsvision.com'],
            ['2a0c4f8e-9b7c-4d3e-4f8a-1c5d7e9b2a0c', 'Faith', 'Parker', 'faith.parker', 'faith.parker@chapsvision.com'],
            ['3b1d5a9f-0c8d-4e4f-5a9b-2d6e8f0c3b1d', 'Gabe', 'Evans', 'gabe.evans', 'gabe.evans@chapsvision.com'],
            [
                '4c2e6b0a-1d9e-4f5a-6b0c-3e7f9a1d4c2e',
                'Hazel',
                'Edwards',
                'hazel.edwards',
                'hazel.edwards@chapsvision.com',
            ],
            ['5d3f7c1b-2e0f-4a6b-7c1d-4f8a0b2e5d3f', 'Ivan', 'Collins', 'ivan.collins', 'ivan.collins@chapsvision.com'],
            ['6e4a8d2c-3f1a-4b7c-8d2e-5a9b1c3f6e4a', 'Jade', 'Stewart', 'jade.stewart', 'jade.stewart@chapsvision.com'],
            ['7f5b9e3d-4a2b-4c8d-9e3f-6b0c2d4a7f5b', 'Kurt', 'Sanchez', 'kurt.sanchez', 'kurt.sanchez@chapsvision.com'],
            ['8a6c0f4e-5b3c-4d9e-0f4a-7c1d3e5b8a6c', 'Lily', 'Morris', 'lily.morris', 'lily.morris@chapsvision.com'],
            ['9b7d1a5f-6c4d-4e0f-1a5b-8d2e4f6c9b7d', 'Mason', 'Rogers', 'mason.rogers', 'mason.rogers@chapsvision.com'],
            ['0c8e2b6a-7d5e-4f1a-2b6c-9e3f5a7d0c8e', 'Nora', 'Reed', 'nora.reed', 'nora.reed@chapsvision.com'],
            ['1d9f3c7b-8e6f-4a2b-3c7d-0f4a6b8e1d9f', 'Owen', 'Cook', 'owen.cook', 'owen.cook@chapsvision.com'],
            ['2e0a4d8c-9f7a-4b3c-4d8e-1a5b7c9f2e0a', 'Penny', 'Morgan', 'penny.morgan', 'penny.morgan@chapsvision.com'],
            ['3f1b5e9d-0a8b-4c4d-5e9f-2b6c8d0a3f1b', 'Quinn', 'Bell', 'quinn.bell', 'quinn.bell@chapsvision.com'],
            ['4a2c6f0e-1b9c-4d5e-6f0a-3c7d9e1b4a2c', 'Rita', 'Murphy', 'rita.murphy', 'rita.murphy@chapsvision.com'],
            ['5b3d7a1f-2c0d-4e6f-7a1b-4d8e0f2c5b3d', 'Steve', 'Bailey', 'steve.bailey', 'steve.bailey@chapsvision.com'],
            ['6c4e8b2a-3d1e-4f7a-8b2c-5e9f1a3d6c4e', 'Tara', 'Rivera', 'tara.rivera', 'tara.rivera@chapsvision.com'],
            ['7d5f9c3b-4e2f-4a8b-9c3d-6f0a2b4e7d5f', 'Ugo', 'Cooper', 'ugo.cooper', 'ugo.cooper@chapsvision.com'],
            [
                '8e6a0d4c-5f3a-4b9c-0d4e-7a1b3c5f8e6a',
                'Vera',
                'Richardson',
                'vera.richardson',
                'vera.richardson@chapsvision.com',
            ],
            ['9f7b1e5d-6a4b-4c0d-1e5f-8b2c4d6a9f7b', 'Will', 'Cox', 'will.cox', 'will.cox@chapsvision.com'],
            ['2c3d4e5f-6a7b-4789-f456-789012345678', 'Xena', 'Howard', 'xena.howard', 'xena.howard@chapsvision.com'],
            ['f0a1b2c3-d4e5-4890-a567-890123456789', 'Yves', 'Ward', 'yves.ward', 'yves.ward@chapsvision.com'],
            ['ca1b2c3d-4e5f-4901-b678-901234567890', 'Zoey', 'Torres', 'zoey.torres', 'zoey.torres@chapsvision.com'],
        ];
        foreach ($usersData as [$uuid, $firstName, $lastName, $userName, $email]) {
            $user = new User($uuid, $email, ['ROLE_USER'], $userName, $firstName, $lastName);
            $manager->persist($user);
        }
        $manager->flush();
    }
}
