<?php

namespace App\Tests\Units\Infrastructure\User;

use App\Domain\User\User;
use App\Infrastructure\User\Security\UserProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserProviderTest extends TestCase
{
    private NullUserGateway $userGateway;
    private LoggerInterface&Stub $logger;
    private UserProvider $userProvider;

    protected function setUp(): void
    {
        $this->userGateway = new NullUserGateway();
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->buildUserProvider();
    }

    private function buildUserProvider(): void
    {
        $this->userProvider = new UserProvider($this->userGateway, $this->logger);
    }

    public function testLoadUserByIdentifierWithExistingUser(): void
    {
        $identifier = 'test-id';
        $user = new User(id: $identifier, email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier($identifier);

        $this->assertSame($user, $result);
    }

    public function testLoadUserByIdentifierWithExistingUserAndNewAttributes(): void
    {
        $identifier = 'test-id';
        $user = new User(id: $identifier, email: 'old@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        $attributes = [
            'email' => 'new@example.com',
            'roles' => ['ROLE_ADMIN'],
        ];

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $this->assertSame('new@example.com', $result->getEmail());
        $this->assertSame(['ROLE_USER'], $result->getRoles());
    }

    public function testLoadUserByIdentifierWithNonExistingUser(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $identifier = 'test-id';
        $attributes = [
            'sub' => 'test-id',
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ];

        $loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                'User test-id not found, this email is unique, so we can create a new user with it',
                [
                    'identifier' => $identifier,
                    'attributes' => $attributes,
                ]
            );

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test-id', $result->getId());
        $this->assertEquals('test@example.com', $result->getEmail());
        $this->assertEquals(['ROLE_USER'], $result->getRoles());
    }

    public function testLoadUserByIdentifierWithExistingEmail(): void
    {
        $existingUser = new User(id: 'existing-id', email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($existingUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User with email test@example.com already exists.');

        $this->userProvider->loadUserByIdentifier('new-id', [
            'sub' => 'new-id',
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);
    }

    public function testRefreshUser(): void
    {
        $user = new User(id: 'test-id', email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        /** @var User $result */
        $result = $this->userProvider->refreshUser($user);

        $this->assertSame($user, $result);
    }

    public function testRefreshUserWithInvalidUserClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user class');

        $invalidUser = $this->createStub(UserInterface::class);
        $this->userProvider->refreshUser($invalidUser);
    }

    public function testRefreshUserWithNullId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID is null');

        $user = new User(id: null, email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userProvider->refreshUser($user);
    }

    public function testSupportsClass(): void
    {
        $this->assertTrue($this->userProvider->supportsClass(User::class));
        $this->assertFalse($this->userProvider->supportsClass(\stdClass::class));
    }

    public function testCreateUserFromAttributesWithMissingSub(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "sub" claim is required to create a user.');

        $this->userProvider->loadUserByIdentifier('test-id', []);
    }

    public function testCreateUserFromAttributesWithInvalidSubType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "sub" claim must be a string.');

        $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 123,
        ]);
    }

    public function testCreateUserFromAttributesWithEmptySub(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "sub" claim cannot be empty.');

        $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => '',
        ]);
    }

    public function testCreateUserFromAttributesWithInvalidEmailType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "email" claim must be a string.');

        $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 'test-id',
            'email' => 123,
        ]);
    }

    public function testCreateUserFromAttributesWithEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "email" claim cannot be empty.');

        $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 'test-id',
            'email' => '',
        ]);
    }

    public function testCreateUserFromAttributesWithInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "email" claim must be a valid email address.');

        $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 'test-id',
            'email' => 'invalid-email',
        ]);
    }

    public function testCreateUserFromAttributesWithInvalidRolesType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "roles" claim must be an array.');

        $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 'test-id',
            'roles' => 'not-an-array',
        ]);
    }

    public function testCreateUserFromAttributesWithEmptyRoles(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $loggerMock
            ->expects($this->once())
            ->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 'test-id',
            'email' => 'test@example.com',
            'roles' => [],
        ]);

        $this->assertSame(['ROLE_USER'], $result->getRoles());
    }

    public function testCreateUserFromAttributesWithInvalidRoleValues(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $loggerMock
            ->expects($this->once())
            ->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', [
            'sub' => 'test-id',
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER', '', null, 123, 'ROLE_ADMIN'],
        ]);

        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $result->getRoles());
    }

    public function testUpdateLocalUserWithInvalidEmail(): void
    {
        $user = new User(id: 'test-id', email: 'old@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', [
            'email' => 'invalid-email',
        ]);

        $this->assertEquals('old@example.com', $result->getEmail());
    }

    public function testUpdateLocalUserWithEmptyEmail(): void
    {
        $user = new User(id: 'test-id', email: 'old@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', [
            'email' => '',
        ]);

        $this->assertEquals('old@example.com', $result->getEmail());
    }

    public function testUpdateLocalUserWithSameEmail(): void
    {
        $user = new User(id: 'test-id', email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', [
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('test@example.com', $result->getEmail());
    }

    public function testUpdateLocalUserWithFirstNameAndLastName(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $user = new User(id: 'test-id', email: 'test@example.com', roles: [
            'ROLE_USER',
        ], firstName: 'OldFirst', lastName: 'OldLast');
        $this->userGateway->save($user);

        $attributes = [
            'firstName' => 'NewFirst',
            'lastName' => 'NewLast',
        ];

        $loggerMock->expects($this->once())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);

        $this->assertEquals('Newfirst', $result->getFirstName());
        $this->assertEquals('Newlast', $result->getLastName());
    }

    public function testUpdateLocalUserWithSameFirstNameAndLastName(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $user = new User(id: 'test-id', email: 'test@example.com', roles: [
            'ROLE_USER',
        ], firstName: 'SameFirst', lastName: 'SameLast');
        $this->userGateway->save($user);

        $attributes = [
            'firstName' => 'SameFirst',
            'lastName' => 'SameLast',
        ];

        $loggerMock->expects($this->never())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);

        $this->assertEquals('SameFirst', $result->getFirstName());
        $this->assertEquals('SameLast', $result->getLastName());
    }

    public function testUpdateLocalUserWithInvalidFirstNameType(): void
    {
        $user = new User(id: 'test-id', email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        $attributes = [
            'firstName' => 123,
        ];

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);
        $this->assertNull($result->getFirstName());
    }

    public function testUpdateLocalUserWithUserName(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $user = new User(id: 'test-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'oldUser');
        $this->userGateway->save($user);

        $attributes = [
            'username' => 'newUser',
        ];

        $loggerMock->expects($this->once())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);
        $this->assertEquals('newUser', $result->getUserName());
    }

    public function testUpdateLocalUserWithSameUserName(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $user = new User(id: 'test-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'sameUser');
        $this->userGateway->save($user);

        $attributes = [
            'username' => 'sameUser',
        ];

        $loggerMock->expects($this->never())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);
        $this->assertEquals('sameUser', $result->getUserName());
    }

    public function testUpdateLocalUserWithInvalidUserNameType(): void
    {
        $user = new User(id: 'test-id', email: 'test@example.com', roles: ['ROLE_USER']);
        $this->userGateway->save($user);

        $attributes = [
            'username' => 123,
        ];

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);
        $this->assertEquals('123', $result->getUserName());
    }

    public function testCreateUserFromAttributesWithoutEmail(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $attributes = [
            'sub' => 'user-no-email',
            'roles' => ['ROLE_USER'],
        ];

        $loggerMock->expects($this->once())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('user-no-email', $attributes);

        $this->assertInstanceOf(User::class, $result);
        $this->assertNull($result->getEmail());
        $this->assertEquals('user-no-email', $result->getId());
    }

    public function testUpdateLocalUserWithMultipleAttributes(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $user = new User(id: 'test-id', email: 'old@example.com', roles: [
            'ROLE_USER',
        ], userName: 'oldUser', firstName: 'OldFirst', lastName: 'OldLast');
        $this->userGateway->save($user);

        $attributes = [
            'email' => 'new@example.com',
            'username' => 'newUser',
            'firstName' => 'NewFirst',
            'lastName' => 'NewLast',
        ];

        $loggerMock->expects($this->once())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);
        $this->assertEquals('new@example.com', $result->getEmail());
        $this->assertEquals('newUser', $result->getUserName());
        $this->assertEquals('Newfirst', $result->getFirstName());
        $this->assertEquals('Newlast', $result->getLastName());
    }

    public function testUpdateLocalUserWithNoChanges(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->logger = $loggerMock;
        $this->buildUserProvider();
        $user = new User(id: 'test-id', email: 'same@example.com', roles: [
            'ROLE_USER',
        ], userName: 'sameUser', firstName: 'SameFirst', lastName: 'SameLast');
        $this->userGateway->save($user);

        $attributes = [
            'email' => 'same@example.com',
            'username' => 'sameUser',
            'firstName' => 'SameFirst',
            'lastName' => 'SameLast',
        ];

        $loggerMock->expects($this->never())
->method('info');

        /** @var User $result */
        $result = $this->userProvider->loadUserByIdentifier('test-id', $attributes);
        $this->assertEquals('same@example.com', $result->getEmail());
        $this->assertEquals('sameUser', $result->getUserName());
        $this->assertEquals('SameFirst', $result->getFirstName());
        $this->assertEquals('SameLast', $result->getLastName());
    }
}
