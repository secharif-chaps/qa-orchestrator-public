# Testing Guide

This guide covers testing strategies, setup, and best practices for the Basil project across API (PHPUnit) and Frontend (Vitest) layers.

## 🎯 Testing Philosophy

### Testing Pyramid

1. **Unit Tests (70%)**: Fast, isolated tests for individual components
2. **Integration Tests (30%)**: Tests for component interactions and API endpoints

### Testing Principles

- **Test-Driven Development (TDD)**: Write tests before implementation when possible
- **Behavior-Driven Development (BDD)**: Focus on user behavior and business requirements
- **Fast Feedback**: Tests should run quickly and provide immediate feedback
- **Deterministic**: Tests should produce consistent results
- **Independent**: Tests should not depend on each other

## 🔧 API Testing (PHPUnit)

### Setup

PHPUnit is configured in `api/phpunit.dist.xml` and ready to use with Docker. The project includes both unit tests and integration tests with different setup requirements.

#### Quick Start

```bash
# Setup integration test environment (required for integration tests)
task api:test:integration:setup

# Run all API tests (unit + integration)
task api:test

# Run Unit tests only
task api:test:unit

# Run Integration tests only
task api:test:integration

# Run specific test files
docker compose exec api php vendor/bin/phpunit tests/Integration/UserApiTest.php
docker compose exec api php vendor/bin/phpunit tests/Units/Domain/User/UserTest.php

# Run with coverage
task api:test:unit:coverage
task api:test:integration:coverage
```

#### Integration Test Environment

Integration tests require a separate test database and environment setup:

```bash
# First-time setup or when database schema changes
task api:test:integration:setup

# Reset test environment (clears all test data)
task api:test:integration:reset

# The setup command does:
# 1. Starts test database container (RAM-based for speed)
# 2. Creates test database schema
# 3. Runs all migrations
# 4. Clear and warms up cache
```

### Test Structure

```text
api/tests/
├── Units/                   # Unit tests for domain logic, services
│   ├── Application/         # Application layer (handlers, actions)
│   ├── Domain/              # Domain entities and value objects
│   └── Infrastructure/      # Infrastructure components
├── Integration/             # Full-stack API endpoint tests
│   ├── AbstractApiTestCase.php  # Base class for API tests
│   └── UserApiTest.php      # Example integration test
└── Utils/                   # Test utilities and helpers
    ├── EntityUtilsTrait.php # Database test helpers
    └── ArrayPaginator.php   # Test pagination utilities
```

**Test Data Generation:**

```text
api/src/DataFixtures/
├── Factory/                 # Foundry factories for test data
│   ├── User/               # UserFactory for user generation
│   └── Folder/             # FolderFactory and FolderUserFactory
│       ├── FolderFactory.php
│       └── FolderUserFactory.php  # Factory for folder sharing relationships
├── UserFixtures.php        # Development fixtures
└── FolderFixtures.php      # Development fixtures
```

**Key Factories:**

- **UserFactory**: Creates users with roles, authentication data
- **FolderFactory**: Creates folders with ownership relationships
- **FolderUserFactory**: Creates folder sharing relationships with specific roles (OWNER, EDITOR, VIEWER)

### Unit Testing

Unit tests focus on testing individual components in isolation, ensuring domain logic and business rules work correctly without external dependencies.

#### Testing Philosophy for Unit Tests

**Prefer NullGateway over Mocks**: Instead of using PHPUnit mocks, the project provides dedicated `NullGateway` implementations that make tests more readable and maintainable.

#### Using NullGateway Implementations

The project includes `NullGateway` implementations for all domain gateways, providing in-memory storage for testing:

**Available NullGateways:**

```text
api/tests/Units/Infrastructure/
├── User/
│   └── NullUserGateway.php          # In-memory user storage
├── Folder/
│   ├── NullFolderGateway.php        # In-memory folder storage
│   ├── NullFolderUserGateway.php    # In-memory folder sharing
│   └── NullStrategicQuestionGateway.php
├── Source/
│   └── NullSourceGateway.php
├── Actor/
│   └── NullActorGateway.php
└── Chat/
    └── NullConversationGateway.php
```

#### NullGateway Example

**NullUserGateway Implementation:**

```php
<?php

namespace App\Tests\Units\Infrastructure\User;

use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;

class NullUserGateway implements UserGatewayInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function get(string $id): User
    {
        if (!isset($this->users[$id])) {
            throw new UserNotFoundException('User not found for id ' . $id);
        }

        return $this->users[$id];
    }

    public function getByEmail(string $email): User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        throw new UserNotFoundException();
    }

    public function save(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }
}
```

#### Best Practices for Unit Testing

#### 1. Use NullGateways Instead of Mocks

```php
<?php

namespace App\Tests\Units\Application\User;

use App\Application\User\CreateUserAction;
use App\Application\User\CreateUserHandler;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use PHPUnit\Framework\TestCase;

class CreateUserHandlerTest extends TestCase
{
    private CreateUserHandler $handler;
    private NullUserGateway $userGateway;

    protected function setUp(): void
    {
        $this->userGateway = new NullUserGateway();
        $this->handler = new CreateUserHandler($this->userGateway);
    }

    public function testCreateUserSuccessfully(): void
    {
        // Arrange
        $action = new CreateUserAction(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe'
        );

        // Act
        $user = $this->handler->handle($action);

        // Assert
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());

        // Verify user was saved to gateway
        $savedUser = $this->userGateway->get($user->getId());
        $this->assertSame($user, $savedUser);
    }

    public function testCreateUserWithDuplicateEmail(): void
    {
        // Arrange
        $existingUser = new User(
            id: 'existing-id',
            email: 'test@example.com',
            userName: 'existing'
        );
        $this->userGateway->save($existingUser);

        $action = new CreateUserAction(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe'
        );

        // Act & Assert
        $this->expectException(DuplicateEmailException::class);
        $this->handler->handle($action);
    }
}
```

#### 2. Test Domain Logic Without Infrastructure

```php
<?php

namespace App\Tests\Units\Domain\Folder;

use App\Domain\Folder\Folder;
use App\Domain\Folder\FolderState;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class FolderTest extends TestCase
{
    public function testFolderCreation(): void
    {
        // Arrange
        $user = new User('user-id', 'test@example.com');

        // Act
        $folder = new Folder(
            name: 'Test Folder',
            userObjective: 'Monitor competitors',
            createdBy: $user
        );

        // Assert
        $this->assertEquals('Test Folder', $folder->getName());
        $this->assertEquals('Monitor competitors', $folder->getUserObjective());
        $this->assertEquals(FolderState::NEW, $folder->getState());
        $this->assertSame($user, $folder->getCreatedBy());
    }

    public function testFolderStateTransition(): void
    {
        // Arrange
        $folder = new Folder('Test', 'Objective');
        $analysisResult = new AnalysisResult(/* ... */);

        // Act
        $folder->analyzeNeeds($analysisResult);

        // Assert
        $this->assertEquals(FolderState::NEEDS_ANALYZED, $folder->getState());
        $this->assertTrue($folder->getAnalysisResults()->contains($analysisResult));
    }

    public function testInvalidStateTransition(): void
    {
        // Arrange
        $folder = new Folder('Test', 'Objective');
        // Folder is in NEW state

        // Act & Assert
        $this->expectException(InvalidStateTransitionException::class);
        $folder->generateQuestions([]); // Can't generate questions from NEW state
    }
}
```

#### 3. Test Application Handlers with Multiple Gateways

```php
<?php

namespace App\Tests\Units\Application\Folder;

use App\Application\Folder\ShareFolderAction;
use App\Application\Folder\ShareFolderHandler;
use App\Tests\Units\Infrastructure\Folder\NullFolderGateway;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Units\Infrastructure\Shared\NullNotifier;
use PHPUnit\Framework\TestCase;

class ShareFolderHandlerTest extends TestCase
{
    private ShareFolderHandler $handler;
    private NullFolderGateway $folderGateway;
    private NullUserGateway $userGateway;
    private NullNotifier $notifier;

    protected function setUp(): void
    {
        $this->folderGateway = new NullFolderGateway();
        $this->userGateway = new NullUserGateway();
        $this->notifier = new NullNotifier();

        $this->handler = new ShareFolderHandler(
            $this->folderGateway,
            $this->userGateway,
            $this->notifier
        );
    }

    public function testShareFolderWithUser(): void
    {
        // Arrange
        $owner = new User('owner-id', 'owner@example.com');
        $shareeUser = new User('sharee-id', 'sharee@example.com');

        $folder = new Folder('Shared Folder', 'Test sharing', $owner);

        $this->userGateway->save($owner);
        $this->userGateway->save($shareeUser);
        $this->folderGateway->save($folder);

        $action = new ShareFolderAction(
            folderId: $folder->getId(),
            userIds: [$shareeUser->getId()],
            role: FolderUserRole::VIEWER
        );

        // Act
        $this->handler->handle($action);

        // Assert
        $updatedFolder = $this->folderGateway->get($folder->getId());
        $this->assertCount(1, $updatedFolder->getFolderUsers());

        $folderUser = $updatedFolder->getFolderUsers()->first();
        $this->assertEquals($shareeUser->getId(), $folderUser->getUser()->getId());
        $this->assertEquals(FolderUserRole::VIEWER, $folderUser->getRole());

        // Verify notification was sent
        $this->assertTrue($this->notifier->wasNotificationSent());
    }
}
```

#### Mocking Final Classes

When testing classes that depend on final classes (like Elasticsearch client classes), the project uses a dedicated PHPUnit extension to automatically enable bypassing of final classes.

#### BypassFinalsPHPUnitExtension Configuration

The project includes a custom PHPUnit extension that automatically handles final class bypassing:

```xml
<!-- phpunit.dist.xml -->
<extensions>
    <bootstrap class="App\Tests\Utils\BypassFinalsPHPUnitExtension">
        <parameter name="bypassReadOnly" value="false"/>
        <parameter name="allowPaths" value="*/vendor/api-platform/elasticsearch/State/CollectionProvider.php,*/vendor/elasticsearch/elasticsearch/src/Client.php"/>
    </bootstrap>
</extensions>
```

**Configuration Parameters:**

- `bypassReadOnly`: Controls whether readonly properties can be bypassed (default: `false`)
- `allowPaths`: Comma-separated list of specific file paths that can be bypassed

**Key Benefits:**

1. **Automatic Setup**: Final class bypassing is enabled automatically for all tests
2. **Centralized Configuration**: All bypass settings are managed in `phpunit.dist.xml`
3. **Security**: Only specifically allowed paths can be bypassed, preventing accidental mocking of core classes
4. **No Manual Setup**: Tests don't need to call `BypassFinals::enable()` manually

**Best Practices for allowPaths Configuration:**

1. **Be Specific**: Use exact file paths rather than broad wildcards
2. **Minimize Scope**: Only include files that absolutely need to be bypassed
3. **Avoid Overly Broad Patterns**: Don't use patterns like `*/vendor/*` that could bypass too much
4. **Document Reasons**: Each path should have a clear justification for bypassing

**Example of Good vs Bad Configurations:**

```xml
<!-- ✅ GOOD: Specific, necessary paths -->
<parameter name="allowPaths" value="*/vendor/elasticsearch/elasticsearch/src/Client.php,*/vendor/api-platform/elasticsearch/State/CollectionProvider.php"/>

<!-- ❌ BAD: Too broad, security risk -->
<parameter name="allowPaths" value="*/vendor/*,*/src/*"/>

<!-- ❌ BAD: Unnecessary wildcards -->
<parameter name="allowPaths" value="*/vendor/elasticsearch/*"/>
```

**When to Add New Paths:**

- Only when you need to mock a specific final class from a third-party library
- When NullGateway implementations aren't feasible
- For testing infrastructure classes that depend on external final classes

**Adding New Allowed Paths:**

When you need to mock a new final class, add its specific path to the `allowPaths` parameter:

```xml
<parameter name="allowPaths" value="existing/path.php,*/vendor/new-library/src/FinalClass.php"/>
```

**Alternative Approaches:**

- Prefer NullGateway implementations when possible
- Use dependency injection to make classes more testable
- Consider using interfaces instead of concrete final classes

#### Best Practices Summary

**✅ DO:**

- Use NullGateway implementations instead of PHPUnit mocks
- Test domain logic in isolation without infrastructure dependencies
- Focus on business rules and state transitions
- Test edge cases and error conditions
- Use descriptive test names that explain the behavior being tested
- Follow the AAA pattern (Arrange, Act, Assert)
- Use `BypassFinals::enable()` when you need to mock final classes

**❌ DON'T:**

- Create complex mock expectations that are hard to maintain
- Test infrastructure concerns in unit tests
- Test multiple behaviors in a single test method
- Skip testing exception scenarios
- Use real database or external services
- Mock final classes without enabling BypassFinals first

#### Creating New NullGateways

When adding new gateways, create corresponding NullGateway implementations:

**Template for New NullGateway:**

```php
<?php

namespace App\Tests\Units\Infrastructure\YourDomain;

use App\Domain\YourDomain\YourEntity;
use App\Domain\YourDomain\YourGatewayInterface;
use App\Domain\YourDomain\YourEntityNotFoundException;

class NullYourGateway implements YourGatewayInterface
{
    /**
     * @var array<string, YourEntity>
     */
    private array $entities = [];

    public function get(string $id): YourEntity
    {
        if (!isset($this->entities[$id])) {
            throw new YourEntityNotFoundException('Entity not found for id ' . $id);
        }

        return $this->entities[$id];
    }

    public function save(YourEntity $entity): void
    {
        $this->entities[$entity->getId()] = $entity;
    }

    // Implement other interface methods as needed
}
```

This approach makes tests more readable, eliminates complex mock setup, and provides a consistent testing experience across the entire codebase.

### Integration Testing (API Endpoints)

Integration tests validate the complete flow from HTTP request to database, ensuring all components work together. Our integration testing setup uses **DAMA Doctrine Test Bundle** for fast database isolation and **Foundry** for realistic test data generation.

#### Setup and Configuration

#### DAMA Doctrine Test Bundle Configuration

The project uses DAMA Doctrine Test Bundle for database isolation without the overhead of full database recreation:

```yaml
# config/packages/dama_doctrine_test_bundle.yaml
dama_doctrine_test_bundle:
  enable_static_connection: true
  enable_static_meta_data_cache: true
  enable_static_query_cache: true
```

This configuration:

- Uses transactions to isolate tests (rollback after each test)
- Enables static caching for better performance
- Works with RAM-based test database for ultra-fast tests

#### Test Database Setup

```bash
# Setup integration test environment
task api:test:integration:setup

# This command automatically:
# 1. Starts test database container
# 2. Creates test database schema
# 3. Runs migrations
# 4. Clear and warms up cache
```

#### AbstractApiTestCase

All integration tests extend `AbstractApiTestCase` which provides authentication, database management, and factory functionality:

```php
<?php

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestAssertionsTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\User\User;
use App\Infrastructure\User\Security\TestAuthenticator;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

abstract class AbstractApiTestCase extends ApiTestCase
{
    use ResetDatabase;  // Automatic database reset between tests
    use Factories;      // Factory auto-completion and helper methods

    protected function createAuthenticatedClient(?User $currentUser = null): Client
    {
        if ($currentUser === null) {
            $currentUser = UserFactory::new()
                ->defaultBasilUser()
                ->create();
        }

        return self::createClient([], [
            'headers' => [
                TestAuthenticator::HEADER_TEST_AUTH_USER_ID => $currentUser->getId(),
            ]
        ]);
    }

    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        if (!isset($defaultOptions['headers'], $defaultOptions['header']['Content-Type'])) {
            $defaultOptions['headers']['Content-Type'] = 'application/ld+json';
        }

        if (!isset($defaultOptions['headers'], $defaultOptions['header']['Accept'])) {
            $defaultOptions['headers']['Accept'] = 'application/ld+json';
        }

        return parent::createClient($kernelOptions, $defaultOptions);
    }
}
```

**Key Features:**

- **ResetDatabase trait**: Automatically handles database cleanup between tests using DAMA transactions
- **Factories trait**: Provides factory auto-completion and helper methods
- **ApiTestAssertionsTrait**: Additional API Platform test assertions
- **Centralized Authentication**: Consistent test user creation and authentication

#### Authentication in Test Mode

#### TestAuthenticator

The project uses a dedicated `TestAuthenticator` for integration tests:

```php
<?php
// src/Infrastructure/User/Security/TestAuthenticator.php

namespace App\Infrastructure\User\Security;

use App\Domain\User\UserGatewayInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TestAuthenticator extends AbstractAuthenticator
{
    public const HEADER_TEST_AUTH_USER_ID = 'X-Test-Auth-User-ID';

    public function __construct(
        private UserGatewayInterface $userGateway,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HEADER_TEST_AUTH_USER_ID);
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $request->headers->get(self::HEADER_TEST_AUTH_USER_ID);

        if (null === $userId) {
            throw new AuthenticationException('No test user ID provided.');
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, fn (string $userIdentifier) => $this->userGateway->findById($userIdentifier))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('Authentication failed.', Response::HTTP_UNAUTHORIZED);
    }
}
```

#### Using Authentication in Tests

```php
public function testProtectedEndpoint(): void
{
    // Create specific user for test
    $user = UserFactory::new()
        ->with(['email' => 'test@example.com'])
        ->create();

    // Create authenticated client with specific user
    $client = $this->createAuthenticatedClient($user);

    // Make authenticated request
    $response = $client->request('GET', '/api/protected-resource');

    $this->assertResponseIsSuccessful();
}

public function testUnauthorizedAccess(): void
{
    // Create unauthenticated client
    $client = self::createClient();

    $client->request('GET', '/api/protected-resource');

    $this->assertResponseStatusCodeSame(401);
}
```

#### Foundry Integration

#### Factory Setup

Foundry provides a powerful factory system for generating test data. All factories extend `PersistentProxyObjectFactory`:

#### Zenstruck Foundry Best Practices

When using Zenstruck Foundry in integration tests, follow these essential best practices to ensure reliable and maintainable tests:

**1. Avoid Using the `_real` Method - Object Proxies Are OK**

```php
// ❌ DON'T: Use _real() method
$user = UserFactory::new()->create()->_real();

// ✅ DO: Use proxy objects directly
$user = UserFactory::new()->create();
```

**Why this matters:**

- Proxy objects provide lazy loading and better performance
- `_real()` forces immediate database queries and can cause issues with auto-refresh
- Proxy objects work seamlessly with Doctrine's lazy loading mechanisms
- Only use `_real()` when you absolutely need the actual entity (rare cases)

#### 2. Avoid Accessing Objects After First Request - Use Temporary Variables

```php
// ❌ DON'T: Access object after first request
$user = UserFactory::new()->create();
$client = $this->createAuthenticatedClient($user);

$response = $client->request('GET', '/api/users');
// ... test logic ...

// This can cause auto-refresh issues:
$userEmail = $user->getEmail(); // Potential auto-refresh problem

// ✅ DO: Store needed data in temporary variables before first request
$user = UserFactory::new()->create();
$userEmail = $user->getEmail(); // Store before first request
$userId = $user->getId();

$client = $this->createAuthenticatedClient($user);
$response = $client->request('GET', '/api/users');

// Use stored variables instead of accessing object:
$this->assertContains($userEmail, $responseData);
```

**Why this matters:**

- After the first database request in a test, accessing proxy objects can trigger auto-refresh
- Auto-refresh can cause unexpected database queries and test failures
- Storing data in variables before the first request ensures consistency

#### 3. Only Declare Objects with Values Being Tested

```php
// ❌ DON'T: Create objects with unnecessary data
$user = UserFactory::new()
    ->with([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => 'john@example.com',
        'userName' => 'johndoe',
        'roles' => [User::ROLE_USER, User::ROLE_ADMIN],
        'createdAt' => new \DateTime(),
        'isActive' => true,
        // ... many more fields
    ])
    ->create();

// ✅ DO: Only specify what you're actually testing
$user = UserFactory::new()
    ->with(['email' => 'john@example.com'])
    ->create();

// Or even better, use defaults when testing general functionality:
$user = UserFactory::new()->create();
```

**Why this matters:**

- Tests should be focused and only test what's relevant
- Unnecessary data makes tests harder to read and maintain
- Factory defaults should handle common scenarios
- Only override factory defaults when testing specific behavior

**Complete Example Following Best Practices:**

```php
public function testUserSearchByEmail(): void
{
    // Arrange - Only specify what we're testing
    $user = UserFactory::new()
        ->with(['email' => 'specific@example.com'])
        ->create();

    // Store needed data before first request
    $userEmail = $user->getEmail();
    $userId = $user->getId();

    // Act - First request
    $client = $this->createAuthenticatedClient($user);
    $response = $client->request('GET', '/api/users?search=specific');

    // Assert - Use stored variables, not object access
    $this->assertResponseIsSuccessful();
    $data = $response->toArray();

    $foundUser = null;
    foreach ($data['member'] as $member) {
        if ($member['email'] === $userEmail) { // Use stored variable
            $foundUser = $member;
            break;
        }
    }

    $this->assertNotNull($foundUser, 'User should be found in search results');
    $this->assertEquals($userEmail, $foundUser['email']);
}
```

**Additional Best Practices:**

#### 4. Use Factory States for Common Scenarios

```php
// In UserFactory.php
public function withAdminRole(): self
{
    return $this->with(['roles' => [User::ROLE_USER, User::ROLE_ADMIN]]);
}

public function inactive(): self
{
    return $this->with(['isActive' => false]);
}

// In tests
$adminUser = UserFactory::new()->withAdminRole()->create();
$inactiveUser = UserFactory::new()->inactive()->create();
```

#### 5. Create Relationships Efficiently

```php
// Create user with folders in one go
$user = UserFactory::new()
    ->has(FolderFactory::new()->many(3), 'folders')
    ->create();

// Create folder with specific owner
$folder = FolderFactory::new()
    ->withCreatedBy(UserFactory::new()->create())
    ->create();
```

#### 6. Use Sequences for Unique Data

```php
// Create multiple users with unique emails
UserFactory::new()
    ->many(5)
    ->sequence(function() {
        for ($i = 1; $i <= 5; $i++) {
            yield ['email' => "user{$i}@example.com"];
        }
    })
    ->create();
```

**Common Pitfalls to Avoid:**

- **Don't** use `_real()` unless absolutely necessary
- **Don't** access proxy objects after the first database request
- **Don't** create objects with unnecessary data
- **Don't** forget to store needed data before first request
- **Don't** mix real database access with proxy objects in the same test

Following these best practices will make your integration tests more reliable, faster, and easier to maintain.

```php
<?php
// src/DataFixtures/Factory/User/UserFactory.php

namespace App\DataFixtures\Factory\User;

use App\Domain\User\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
class UserFactory extends PersistentProxyObjectFactory
{
    public const BASIL_USER_ID = '116f1d59-c72d-486c-bf55-a5e587d88cfc';

    public static function class(): string
    {
        return User::class;
    }

    public function defaultBasilUser(): self
    {
        return $this->with([
            'id' => self::BASIL_USER_ID,
            'roles' => [User::ROLE_USER],
            'firstName' => 'Basil',
            'lastName' => 'Target',
            'userName' => 'basil',
            'email' => 'basil@chapsvision.com',
        ]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => self::faker()->uuid(),
            'roles' => [User::ROLE_USER],
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'userName' => self::faker()->userName(),
            'email' => self::faker()->email(),
        ];
    }
}
```

#### Foundry Best Practices

1. **Use Named States for Common Scenarios**

   ```php
   public function withAdminRole(): self
   {
       return $this->with(['roles' => [User::ROLE_USER, User::ROLE_ADMIN]]);
   }

   public function withSpecificEmail(string $email): self
   {
       return $this->with(['email' => $email]);
   }
   ```

2. **Create Relationships**

   ```php
   // Create user with folders
   $user = UserFactory::new()
       ->has(FolderFactory::new()->many(3), 'folders')
       ->create();

   // Create folder with specific owner
   $folder = FolderFactory::new()
       ->withCreatedBy(UserFactory::new()->create())
       ->create();
   ```

3. **Use Sequences for Unique Data**

   ```php
   UserFactory::new()
       ->sequence(function() {
           for ($i = 1; $i <= 10; $i++) {
               yield ['email' => "user{$i}@example.com"];
           }
       })
       ->many(10)
       ->create();
   ```

#### Creating New Factories with Symfony Maker

To quickly create new Foundry factories, use the Symfony maker bundle:

```bash
# Generate a new factory for an existing entity
docker compose exec api bin/console make:factory

# Example: Creating a factory for a new entity
docker compose exec api bin/console make:factory YourEntity

# The maker will:
# 1. Detect your entity class automatically
# 2. Generate a factory in src/DataFixtures/Factory/
# 3. Include all entity properties with appropriate faker data
# 4. Create factory methods for common scenarios
```

**Generated Factory Example:**

```php
<?php
// src/DataFixtures/Factory/YourEntityFactory.php

namespace App\DataFixtures\Factory;

use App\Domain\YourEntity\YourEntity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<YourEntity>
 */
class YourEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return YourEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->company(),
            'description' => self::faker()->text(200),
            'createdAt' => self::faker()->dateTimeBetween('-1 year'),
            'isActive' => self::faker()->boolean(80), // 80% chance of true
        ];
    }

    // Add custom states
    public function active(): self
    {
        return $this->with(['isActive' => true]);
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }
}
```

**Best Practices for Factory Creation:**

- Always use descriptive default values with appropriate faker methods
- Create named states for common entity configurations (active, inactive, etc.)
- Include relationships using other factories when needed
- Use meaningful faker data that reflects real-world scenarios

#### Foundry Stories (Not Currently Used)

Foundry Stories provide a way to create complex, pre-defined scenarios for testing and development. While not currently implemented in this project, they can be valuable for:

**What are Stories?**

Stories are predefined sets of related data that represent common application scenarios. They're useful for:

- Consistent test scenarios across different test classes
- Complex data setups that involve multiple entities
- Development fixtures that represent realistic application states
- Demo data for presentations or client reviews

**Example Story Implementation:**

```php
<?php
// src/DataFixtures/Story/ProjectCollaborationStory.php

namespace App\DataFixtures\Story;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\Folder\FolderFactory;
use App\DataFixtures\Factory\Folder\FolderUserFactory;
use App\Domain\Folder\FolderUserRole;
use Zenstruck\Foundry\Story;

class ProjectCollaborationStory extends Story
{
    public function build(): void
    {
        // Create project owner
        $owner = UserFactory::new()
            ->with([
                'firstName' => 'Alice',
                'lastName' => 'Manager',
                'email' => 'alice.manager@company.com',
            ])
            ->create();

        // Create team members
        $developer = UserFactory::new()
            ->with([
                'firstName' => 'Bob',
                'lastName' => 'Developer',
                'email' => 'bob.dev@company.com',
            ])
            ->create();

        $designer = UserFactory::new()
            ->with([
                'firstName' => 'Carol',
                'lastName' => 'Designer',
                'email' => 'carol.design@company.com',
            ])
            ->create();

        // Create project folder
        $projectFolder = FolderFactory::new()
            ->with([
                'name' => 'Project Alpha - Market Research',
                'userObjective' => 'Analyze competitor strategies and market positioning',
                'createdBy' => $owner,
            ])
            ->create();

        // Set up collaboration
        FolderUserFactory::new()
            ->with([
                'folder' => $projectFolder,
                'user' => $owner,
                'role' => FolderUserRole::OWNER,
            ])
            ->create();

        FolderUserFactory::new()
            ->with([
                'folder' => $projectFolder,
                'user' => $developer,
                'role' => FolderUserRole::EDITOR,
            ])
            ->create();

        FolderUserFactory::new()
            ->with([
                'folder' => $projectFolder,
                'user' => $designer,
                'role' => FolderUserRole::VIEWER,
            ])
            ->create();

        // Store references for use in tests
        $this->addState('owner', $owner);
        $this->addState('developer', $developer);
        $this->addState('designer', $designer);
        $this->addState('projectFolder', $projectFolder);
    }
}
```

**Using Stories in Tests:**

```php
<?php

namespace App\Tests\Integration;

use App\DataFixtures\Story\ProjectCollaborationStory;

class FolderCollaborationApiTest extends AbstractApiTestCase
{
    public function testFolderAccessPermissions(): void
    {
        // Arrange - Load the complete story scenario
        ProjectCollaborationStory::load();

        $owner = ProjectCollaborationStory::get('owner');
        $developer = ProjectCollaborationStory::get('developer');
        $projectFolder = ProjectCollaborationStory::get('projectFolder');

        // Act - Test as developer
        $client = $this->createAuthenticatedClient($developer);
        $response = $client->request('GET', '/api/folders/' . $projectFolder->getId());

        // Assert - Developer should have access
        $this->assertResponseIsSuccessful();
    }

    public function testFolderEditingRights(): void
    {
        // Arrange - Use the same story
        ProjectCollaborationStory::load();

        $designer = ProjectCollaborationStory::get('designer');
        $projectFolder = ProjectCollaborationStory::get('projectFolder');

        // Act - Try to edit as viewer (designer)
        $client = $this->createAuthenticatedClient($designer);
        $response = $client->request('PATCH', '/api/folders/' . $projectFolder->getId(), [
            'json' => ['name' => 'Modified Project Name']
        ]);

        // Assert - Should be forbidden for viewer role
        $this->assertResponseStatusCodeSame(403);
    }
}
```

**When to Consider Stories:**

- When you have complex, multi-entity scenarios used across multiple tests
- For development fixtures that represent complete user workflows
- When you need consistent, realistic data for demos or staging environments
- For testing complex business rules that involve multiple entities

**Story vs Factory Guidelines:**

- **Use Factories** for simple entity creation and most test scenarios
- **Use Stories** for complex, multi-entity scenarios that represent complete business workflows
- **Combine both** when you need the same complex scenario across multiple test classes

#### Essential Foundry Traits

Since `AbstractApiTestCase` already includes the essential traits, your test classes can focus on the test logic:

```php
<?php

namespace App\Tests\Integration;

class UserApiTest extends AbstractApiTestCase
{
    // No need to add traits - inherited from AbstractApiTestCase
    // - ResetDatabase: Automatic database cleanup between tests
    // - Factories: Factory auto-completion and helper methods

    public function testSomething(): void
    {
        // Test implementation
    }
}
```

**Available Factory Features:**

- `UserFactory::createMany(10)` - Create multiple users efficiently
- `UserFactory::new()->with(['email' => 'test@example.com'])` - Override specific fields
- `FolderFactory::new()->withCreatedBy($user)` - Create relationships
- `FolderUserFactory::new()` - Create folder sharing relationships

#### Complete Integration Test Example

```php
<?php

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Folder\FolderFactory;
use App\DataFixtures\Factory\Folder\FolderUserFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\Folder\FolderUserRole;

class UserApiTest extends AbstractApiTestCase
{
    // Traits are inherited from AbstractApiTestCase - no need to declare them

    public function testGetCollectionUsersStructure(): void
    {
        // Arrange - Create test data with Foundry
        UserFactory::new()
            ->with([
                'firstName' => 'John',
                'lastName' => 'Doe',
                'userName' => 'johndoe',
                'email' => 'john.doe@example.com',
            ])
            ->create();

        // Act - Make authenticated API request
        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users');

        // Assert - Validate response structure and data
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThan(0, count($data['member']));

        $foundUser = null;
        foreach ($data['member'] as $member) {
            if ($member['email'] === 'john.doe@example.com') {
                $foundUser = $member;
                break;
            }
        }

        $this->assertNotNull($foundUser, 'Created user should be found in the collection');
        $this->assertArrayHasKey('@id', $foundUser);
        $this->assertArrayHasKey('@type', $foundUser);
        $this->assertEquals('User', $foundUser['@type']);
        $this->assertEquals('john.doe@example.com', $foundUser['email']);
        $this->assertEquals('John Doe', $foundUser['displayName']);
        $this->assertEquals('JD', $foundUser['defaultThumbnail']);
    }

    public function testGetCollectionUsersExcludeCurrentUser(): void
    {
        // Arrange - Create current user and other users
        $currentUser = UserFactory::new()
            ->with(['email' => 'current@example.com'])
            ->create();

        UserFactory::createMany(5); // Creates 5 additional users

        // Act - Request with excludeCurrentUser parameter
        $client = $this->createAuthenticatedClient($currentUser);
        $response = $client->request('GET', '/api/users?excludeCurrentUser=true');

        // Assert - Current user should be excluded from results
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        foreach ($data['member'] as $user) {
            $this->assertNotEquals('current@example.com', $user['email']);
        }
    }

    public function testGetCollectionUsersExcludeFolderSharedUsers(): void
    {
        // Arrange - Create folder owner and shared users
        $folderOwner = UserFactory::new()
            ->with(['email' => 'owner@example.com'])
            ->create();

        // Create a folder with shared users
        $folder = FolderFactory::new()
            ->withCreatedBy($folderOwner)
            ->create();

        // Create folder sharing relationships using FolderUserFactory
        FolderUserFactory::new()
            ->with([
                'folder' => $folder,
                'user' => $folderOwner,
                'role' => FolderUserRole::OWNER,
            ])
            ->create();

        FolderUserFactory::new()
            ->with([
                'folder' => $folder,
                'user' => UserFactory::new()->with(['email' => 'shared1@example.com']),
                'role' => FolderUserRole::EDITOR,
            ])
            ->create();

        FolderUserFactory::new()
            ->with([
                'folder' => $folder,
                'user' => UserFactory::new()->with(['email' => 'shared2@example.com']),
                'role' => FolderUserRole::VIEWER,
            ])
            ->create();

        // Create user not shared with the folder
        UserFactory::new()
            ->with(['email' => 'notshared@example.com'])
            ->create();

        // Act - Request with excludeFolderSharedUsers parameter
        $client = $this->createAuthenticatedClient($folderOwner);
        $response = $client->request(
            'GET',
            '/api/users?excludeFolderSharedUsers=' . $folder->getId() . '&excludeCurrentUser=true'
        );

        // Assert - Only non-shared users should be returned
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(1, $data['member']);
        $this->assertEquals('notshared@example.com', $data['member'][0]['email']);
    }

    public function testMultiFieldSearch(): void
    {
        // Arrange - Create users with specific data for search testing
        UserFactory::new()
            ->with([
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'email' => 'jane.smith@example.com',
            ])
            ->create();

        // Act - Search by first name
        $client = $this->createAuthenticatedClient();
        $response = $client->request('GET', '/api/users?search=Jane');

        // Assert - Should find user by first name
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $foundJane = false;
        foreach ($data['member'] as $user) {
            if ($user['email'] === 'jane.smith@example.com') {
                $foundJane = true;
                break;
            }
        }
        $this->assertTrue($foundJane, 'Should find Jane when searching by first name');
    }
}
```

**Key Testing Patterns:**

1. **Factory Relationships**: Use `FolderUserFactory` to create folder sharing relationships with specific roles
2. **Query Parameters**: Test API filters with explicit parameters like `excludeCurrentUser=true`
3. **Complex Scenarios**: Test folder sharing exclusion logic with multiple users and roles
4. **Domain Logic**: Validate computed properties like `displayName` and `defaultThumbnail`

#### Performance Optimization

#### RAM-Based Database

The test database runs entirely in RAM for maximum speed:

```yaml
# compose.override.yml
database-test:
  image: postgres:17-alpine
  profiles: [test]
  tmpfs:
    - /var/lib/postgresql/data # Database stored in RAM
  environment:
    POSTGRES_DB: basil_test
    POSTGRES_USER: basil
    POSTGRES_PASSWORD: basil
```

#### Transaction Isolation

DAMA Doctrine Test Bundle uses database transactions instead of recreating the database:

- Each test runs in a transaction
- Transaction is rolled back after test completion
- No need for manual cleanup
- Tests run 10x faster than full database recreation

#### Factory Optimization

```php
// Good: Create multiple objects efficiently
UserFactory::createMany(10);

// Better: Use sequences for unique data
UserFactory::new()
    ->many(10)
    ->sequence(function() {
        for ($i = 1; $i <= 10; $i++) {
            yield ['email' => "user{$i}@example.com"];
        }
    })
    ->create();

// Best: Create without persisting when testing logic only
$user = UserFactory::new()->withoutPersisting()->create();
```

### Database Testing with Fixtures

#### Creating Test Fixtures

```php
<?php

namespace App\\Tests\\Fixtures;

use App\\Entity\\User;
use Doctrine\\Bundle\\FixturesBundle\\Fixture;
use Doctrine\\Persistence\\ObjectManager;

class UserTestFixture extends Fixture
{
    public const USER_REFERENCE = 'test-user';

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');

        $manager->persist($user);
        $manager->flush();

        $this->addReference(self::USER_REFERENCE, $user);
    }
}
```

#### Using Fixtures in Tests

```php
public function testWithFixtures(): void
{
    $this->loadFixtures([UserTestFixture::class]);

    $user = $this->getReference(UserTestFixture::USER_REFERENCE);

    // Test with fixture data
}
```

#### Importing fixtures

```bash
docker compose exec api php bin/console doctrine:fixtures:load --no-interaction
```

## 🎨 Frontend Testing (Vitest)

### Setup

Vitest is configured in `pwa/vitest.config.ts`:

```bash
# Run all frontend tests
cd pwa && npm run test

# Run with coverage
npm run test:coverage

# Run UI mode
npm run test:ui

# Run in watch mode (default vitest behavior)
npm run test
```

### Test Structure

```text
pwa/tests/
├── unit/              # Unit tests for utilities, composables
├── components/        # Component tests
└── __mocks__/         # Mock files
```

### Unit Testing Composables

#### Testing Composables

```typescript
// tests/unit/composables/useApi.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useApi } from '~/composables/useApi'

// Mock $fetch
const mockFetch = vi.fn()
vi.mock('#app', () => ({
  useNuxtApp: () => ({
    $fetch: mockFetch,
  }),
}))

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('should fetch users successfully', async () => {
    // Arrange
    const mockUsers = [
      { id: 1, email: 'user1@example.com' },
      { id: 2, email: 'user2@example.com' },
    ]
    mockFetch.mockResolvedValue(mockUsers)

    // Act
    const { getUsers } = useApi()
    const result = await getUsers()

    // Assert
    expect(mockFetch).toHaveBeenCalledWith('/api/users')
    expect(result).toEqual(mockUsers)
  })

  it('should handle API errors', async () => {
    // Arrange
    const error = new Error('API Error')
    mockFetch.mockRejectedValue(error)

    // Act & Assert
    const { getUsers } = useApi()
    await expect(getUsers()).rejects.toThrow('API Error')
  })
})
```

### Component Testing

#### Testing Vue Components

```typescript
// tests/components/UserCard.test.ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import UserCard from '~/components/UserCard.vue'
import type { User } from '~/types/user'

describe('UserCard', () => {
  const mockUser: User = {
    id: '1',
    email: 'test@example.com',
    firstName: 'John',
    lastName: 'Doe',
  }

  it('renders user information correctly', () => {
    // Arrange & Act
    const wrapper = mount(UserCard, {
      props: { user: mockUser },
    })

    // Assert
    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('test@example.com')
  })

  it('emits update event when edit button is clicked', async () => {
    // Arrange
    const wrapper = mount(UserCard, {
      props: { user: mockUser, editable: true },
    })

    // Act
    await wrapper.find('[data-testid=\"edit-button\"]').trigger('click')

    // Assert
    expect(wrapper.emitted().edit).toBeTruthy()
    expect(wrapper.emitted().edit[0]).toEqual([mockUser])
  })

  it('does not show edit button when not editable', () => {
    // Arrange & Act
    const wrapper = mount(UserCard, {
      props: { user: mockUser, editable: false },
    })

    // Assert
    expect(wrapper.find('[data-testid=\"edit-button\"]').exists()).toBe(false)
  })
})
```

### Testing Pinia Stores

#### Store Testing

```typescript
// tests/unit/stores/user.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUserStore } from '~/stores/user'

// Mock API calls
vi.mock('~/composables/useApi', () => ({
  useApi: () => ({
    getUsers: vi.fn(() => Promise.resolve([])),
    createUser: vi.fn((user) => Promise.resolve({ id: '1', ...user })),
  }),
}))

describe('User Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes with empty state', () => {
    // Arrange & Act
    const store = useUserStore()

    // Assert
    expect(store.users).toEqual([])
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetches users successfully', async () => {
    // Arrange
    const store = useUserStore()
    const mockUsers = [
      { id: '1', email: 'user1@example.com' },
      { id: '2', email: 'user2@example.com' },
    ]

    // Mock the API response
    const { useApi } = await import('~/composables/useApi')
    const api = useApi()
    vi.mocked(api.getUsers).mockResolvedValue(mockUsers)

    // Act
    await store.fetchUsers()

    // Assert
    expect(store.users).toEqual(mockUsers)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })
})
```

## 🔄 Test Automation and CI

### Running Tests in CI

#### GitLab CI Configuration

The project uses GitLab CI with multiple stages: Build, CodingStandards, TestsAndSecurity, Deploy, Cleanup, and Renovate.

Key testing jobs in `.gitlab-ci.yml`:

**API Tests:**

```yaml
phpunit:
  stage: TestsAndSecurity
  variables:
    XDEBUG_MODE: coverage
  script:
    - cd api
    - php bin/phpunit --coverage-text --colors=never --log-junit junit.xml --coverage-cobertura coverage.xml
  coverage: '/^\s*Lines:\s*\d+\.\d+\%/'
  artifacts:
    reports:
      junit: api/junit.xml
      coverage_report:
        coverage_format: cobertura
        path: api/coverage.xml
```

**Code Quality:**

```yaml
ecs:
  stage: CodingStandards
  script:
    - cd api
    - vendor/bin/ecs check --config=ecs.php

phpstan:
  stage: CodingStandards
  script:
    - cd api
    - vendor/bin/phpstan --memory-limit=1G analyse

eslint:
  stage: CodingStandards
  script:
    - cd pwa
    - yarn run build
    - npx eslint .
```

**Security Audits:**

```yaml
composer-audit:
  stage: TestsAndSecurity
  script:
    - cd api
    - composer audit

yarn-front-audit:
  stage: TestsAndSecurity
  script:
    - cd pwa
    - yarn npm audit
```

### Local Test Scripts

**Available Task Commands:**

```bash
# API Testing (using Taskfile)
task api:test                         # Run all PHPUnit tests
task api:test:integration:setup       # Setup integration test environment
task api:test:integration:reset       # Reset integration test environment
task api:test:unit:coverage           # Run unit tests with coverage report
task api:test:integration:coverage    # Run integration tests with coverage report

# Run specific test files
docker compose exec api php vendor/bin/phpunit tests/Integration/UserApiTest.php
docker compose exec api php vendor/bin/phpunit tests/Units/Domain/User/UserTest.php

# Run specific test methods
docker compose exec api php vendor/bin/phpunit --filter=testGetCollectionUsersStructure

# API Code Quality
task api:cs:check               # Check PHP code style
task api:cs:fix                 # Fix PHP code style
task api:phpstan:check          # Run PHPStan analysis
task api:composer:install       # Install dependencies
task api:fixture:load           # Load development fixtures

# PWA Testing (using npm scripts)
cd pwa && npm run test                # Run Vitest tests
cd pwa && npm run test:coverage       # Run tests with coverage
cd pwa && npm run test:ui             # Run tests with UI

# Run specific frontend test files
cd pwa && npm run test -- tests/components/folders/FolderList.test.ts
cd pwa && npm run test -- --run       # Run once without watch mode

# PWA Code Quality (using Task)
task pwa:eslint:check          # Check JavaScript/Vue code style
task pwa:eslint:fix            # Fix JavaScript/Vue code style
```

**Package.json Scripts (PWA):**

```json
{
  "scripts": {
    "test": "vitest",
    "test:coverage": "vitest run --coverage",
    "test:ui": "vitest --ui",
    "build": "nuxt build",
    "dev": "nuxt dev"
  }
}
```

## 📊 Test Coverage

### Coverage Goals

- **API**: Minimum 80% code coverage
- **Frontend**: Minimum 70% code coverage
- **Critical paths**: 100% coverage for authentication and data integrity

### Coverage Reports

#### PHPUnit Coverage

```bash
# Generate HTML coverage report
docker compose exec api php vendor/bin/phpunit --coverage-html var/coverage

# Generate text coverage report
docker compose exec api php vendor/bin/phpunit --coverage-text
```

#### Vitest Coverage

```bash
# Generate coverage report
cd pwa && npm run test:coverage

# View coverage report
open coverage/index.html
```

## 🛠️ Testing Utilities

### Custom Test Helpers

#### API Test Helpers

```php
<?php
// tests/Utils/ApiTestHelper.php

namespace App\\Tests\\Utils;

use ApiPlatform\\Symfony\\Bundle\\Test\\ApiTestCase;
use App\\Entity\\User;
use Doctrine\\ORM\\EntityManagerInterface;

trait ApiTestHelper
{
    protected function createTestUser(array $data = []): User
    {
        $user = new User();
        $user->setEmail($data['email'] ?? 'test@example.com');
        $user->setFirstName($data['firstName'] ?? 'Test');
        $user->setLastName($data['lastName'] ?? 'User');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function authenticateUser(User $user): void
    {
        // Implementation depends on your auth system
    }
}
```

#### Frontend Test Helpers

```typescript
// tests/utils/test-helpers.ts
import { mount, VueWrapper } from '@vue/test-utils'
import { createPinia } from 'pinia'
import type { ComponentMountingOptions } from '@vue/test-utils'

export function createTestWrapper<T>(
  component: T,
  options: ComponentMountingOptions<T> = {},
): VueWrapper {
  return mount(component, {
    global: {
      plugins: [createPinia()],
      ...options.global,
    },
    ...options,
  })
}

export function createMockUser(overrides = {}) {
  return {
    id: '1',
    email: 'test@example.com',
    firstName: 'Test',
    lastName: 'User',
    ...overrides,
  }
}
```

### Mock Services

#### API Mocks

```typescript
// tests/__mocks__/api.ts
export const mockApi = {
  getUsers: vi.fn(() => Promise.resolve([])),
  getUser: vi.fn((id: string) => Promise.resolve({ id, email: 'test@example.com' })),
  createUser: vi.fn((user) => Promise.resolve({ id: '1', ...user })),
  updateUser: vi.fn((id: string, user) => Promise.resolve({ id, ...user })),
  deleteUser: vi.fn(() => Promise.resolve()),
}
```

## 🐛 Debugging Tests

### PHPUnit Debugging

```bash
# Run with verbose output
docker compose exec api php vendor/bin/phpunit --verbose

# Debug specific test
docker compose exec api php vendor/bin/phpunit --filter testMethodName

# Stop on failure
docker compose exec api php vendor/bin/phpunit --stop-on-failure
```

### Vitest Debugging

```bash
# Run in debug mode
cd pwa && npm run test -- --run --reporter=verbose

# Debug specific test
npm run test -- --run tests/specific-test.test.ts

# Run with UI for debugging
npm run test:ui
```

## 📝 Best Practices

### General Testing Best Practices

1. **Write descriptive test names** that explain what is being tested
2. **Follow AAA pattern**: Arrange, Act, Assert
3. **Keep tests independent** and isolated
4. **Mock external dependencies** to ensure test reliability
5. **Test edge cases** and error conditions
6. **Keep tests simple** and focused on single behavior

### Performance Testing

1. **Monitor test execution time** and optimize slow tests
2. **Use parallel execution** when possible
3. **Clean up test data** after each test
4. **Use database transactions** for faster test cleanup

### Maintenance

1. **Update tests** when functionality changes
2. **Remove obsolete tests** that no longer provide value
3. **Refactor tests** to reduce duplication
4. **Review test coverage** regularly

## 🔍 OpenAPI Schema Testing

The project includes comprehensive OpenAPI schema testing to ensure API documentation accuracy and consistency.
These tests validate that our API Platform implementation matches the documented schema.

### OpenAPI Test Components

**OpenApiSchemaLoader (`api/tests/Utils/OpenApiSchemaLoader.php`)**

This utility class provides the foundation for all OpenAPI-based testing:

```php
// Automatically exports and loads the current OpenAPI schema
$schema = OpenApiSchemaLoader::loadSchema(self::bootKernel());

// Discovers all endpoints with specific HTTP methods
foreach (OpenApiSchemaLoader::getEndpoints($schema, ['GET']) as $endpoint) {
    // Test each endpoint
}

// Validates response data against OpenAPI schema
$errors = OpenApiSchemaLoader::validateResponse($schema, $path, $method, $statusCode, $responseData);
```

**Key Features:**

- **Schema Export**: Uses the `api:openapi:export` console command to generate the current schema
- **Endpoint Discovery**: Automatically finds all API endpoints from the schema
- **Response Validation**: Validates actual API responses against documented schemas
- **Caching**: Caches loaded schema for performance during test runs

### OpenAPI Validation Test

**OpenApiValidationTest (`api/tests/Integration/OpenApiValidationTest.php`)**

This test validates that all API endpoints return responses that conform to the OpenAPI schema documentation:

```php
#[DataProvider('getEndpointsProvider')]
public function testEndpointReturnsValidResponse(string $method, string $path, array $operation): void
{
    // Validates:
    // 1. Operation has summary and description
    // 2. Endpoint returns expected HTTP status codes
    // 3. Response structure matches OpenAPI schema
    // 4. JSON-LD format compliance (for API Platform)
    // 5. UUID field format validation
}
```

**What it tests:**

- **Documentation Completeness**: Every endpoint must have summary and description
- **Response Schema Compliance**: Response data matches documented schema
- **Content-Type Headers**: Proper content types are returned
- **Hydra Collections**: API Platform collection format validation
- **UUID Format**: Validates that ID fields contain valid UUIDs
- **JSON-LD Structure**: Ensures `@id`, `@type`, and `@context` properties

### OpenAPI Collection Consistency Test

**OpenApiCollectionConsistencyTest (`api/tests/Integration/OpenApiCollectionConsistencyTest.php`)**

This advanced test automatically discovers collection/item endpoint pairs and validates consistency between collection and individual item representations:

```php
#[DataProvider('collectionEndpointsProvider')]
public function testCollectionItemConsistency(string $collectionPath, string $itemPath): void
{
    // Automatically:
    // 1. Discovers collection endpoints from OpenAPI schema
    // 2. Finds corresponding item endpoints
    // 3. Compares item properties between collection and individual views
    // 4. Validates serialization consistency
}
```

**Auto-Discovery Features:**

- **Schema Analysis**: Analyzes OpenAPI responses to identify Hydra Collections
- **Pattern Matching**: Automatically finds item endpoints (e.g., `/api/users` ↔ `/api/users/{id}`)
- **Smart Detection**: Uses schema properties (`hydra:Collection`, `member`) and operation descriptions
- **Dynamic Test Data**: Creates test entities when collections are empty

**What it validates:**

- **Serialization Consistency**: Items have same properties in collection vs individual views
- **Data Integrity**: Property values are identical between representations
- **API Contract**: Collection items contain all expected fields
- **Hypermedia Compliance**: JSON-LD `@id` extraction and ID resolution

## 📝 API Documentation Best Practices

### Importance of PHP Attributes for API Documentation

**Why Documentation Matters:**

The OpenAPI tests validate that our API documentation is accurate and complete.
To ensure these tests pass and provide value to API consumers, it's crucial to properly document all API endpoints using PHP attributes.

**Essential PHP Attributes for API Platform:**

```php
use OpenApi\Attributes as OA;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    operations: [
        new GetCollection(
            summary: 'Retrieves the collection of WatchFile resources',
            description: 'Returns a paginated list of watchfiles accessible to the current user. Supports filtering by status, search terms, and user access permissions.',
        ),
        new Get(
            summary: 'Retrieves a WatchFile resource',
            description: 'Returns detailed information about a specific watchfile, including its current state, analysis results, and associated metadata.',
        ),
    ]
)]
#[OA\Tag(name: 'WatchFiles')]
class WatchFile
{
    #[OA\Property(
        description: 'The unique identifier of the watchfile',
        example: '550e8400-e29b-41d4-a716-446655440000'
    )]
    private string $id;

    #[OA\Property(
        description: 'The user-friendly name of the watchfile',
        example: 'Competitor Analysis - Q4 2024'
    )]
    private string $name;

    #[OA\Property(
        description: 'Current processing state of the watchfile',
        example: 'NEEDS_ANALYZED'
    )]
    private WatchFileState $state;
}
```

**Required Documentation Elements:**

1. **Operation Summaries**: Brief, clear descriptions of what each endpoint does
2. **Operation Descriptions**: Detailed explanations including behavior, filters, and use cases
3. **Property Descriptions**: Clear explanation of each field's purpose and format
4. **Examples**: Representative example values for better understanding
5. **Tags**: Logical grouping of related endpoints

**OpenAPI Validation Benefits:**

```php
// ✅ GOOD: Well-documented endpoint
#[GetCollection(
    summary: 'Retrieves user collection',
    description: 'Returns a paginated list of users. Supports search by name, email filtering, and role-based access control. Excludes inactive users by default.',
)]

// ❌ BAD: Missing documentation (will fail OpenAPI tests)
#[GetCollection()]
```

**Best Practices:**

- **Be Specific**: Describe exact behavior, not just generic CRUD operations
- **Include Context**: Explain business rules, permissions, and constraints
- **Use Examples**: Provide realistic sample data for complex fields
- **Document Filters**: Explain all available query parameters and their effects
- **Error Scenarios**: Document possible error responses and their causes

**Integration with Tests:**

The OpenAPI validation tests ensure that:

- Every endpoint has a non-empty summary and description
- Response schemas match the documented structure
- Examples in documentation are realistic and valid
- API contracts remain stable across changes

This documentation-driven approach ensures that our API is self-documenting, easy to understand, and maintains high quality standards for both internal development and external API consumers.
