# Development Guide

This guide outlines the development standards, practices, and conventions for the Basil project.

## 🎯 Development Standards

### Code Quality Principles

1. **Clean Code**: Write self-documenting, readable code
2. **SOLID Principles**: Follow object-oriented design principles
3. **DRY (Don't Repeat Yourself)**: Avoid code duplication
4. **KISS (Keep It Simple, Stupid)**: Prefer simple solutions
5. **YAGNI (You Aren't Gonna Need It)**: Don't over-engineer

### Code Reviews

- All code must be reviewed before merging
- Use descriptive commit messages following [Conventional Commits](https://www.conventionalcommits.org/)
- Keep pull requests focused and small
- Include tests for new features
- Update documentation as needed

## 🏗️ Architecture Guidelines

### API (Symfony + API Platform) - Clean Architecture

The project follows a **Clean Architecture** approach with Domain-Driven Design (DDD) principles. The architecture is layered and strictly enforces dependency rules.

#### Directory Structure

```
api/src/
├── Domain/                 # Core business logic (framework-agnostic)
│   ├── {EntityName}/       # Entity-based organization
│   │   ├── {EntityName}.php                    # Domain entity
│   │   ├── {EntityName}GatewayInterface.php    # Repository contract
│   │   ├── {EntityName}NotFoundException.php   # Domain exceptions
│   │   └── *.php          # Value objects, domain services
│   └── Shared/            # Shared domain concepts
│       ├── DomainException.php
│       ├── NotFoundException.php
│       └── *.php          # Common interfaces, traits, values objects
├── Application/           # Use cases and application logic
│   ├── {UseCase}/         # Use case organization
│   │   ├── *Action.php    # Command/Query objects (suffixed with "Action")
│   │   ├── *Handler.php   # Command/Query handlers (suffixed with "Handler")
│   │   └── *.php          # DTOs, application services
├── Infrastructure/        # External concerns and framework integration
│   ├── {Entity}/          # Entity-based organization
│   │   ├── *DoctrineGateway.php    # Repository implementations
│   │   ├── *Processor.php          # API Platform processors
│   │   ├── *Provider.php           # API Platform providers
│   │   └── *.php                   # External service clients
│   └── Shared/           # Shared infrastructure concerns
└── UserInterface/        # Delivery mechanisms
    ├── Http/             # HTTP controllers
    │   └── *Controller.php
    ├── Command/          # CLI commands
    │   └── *Command.php
    └── Dto/              # Input/Output (could be a API ressource) DTOs for API
```

#### Layer Responsibilities and Dependencies

**Domain Layer** (`api/src/Domain/`)

- **Purpose**: Core business logic, rules, and models (framework-agnostic)
- **Contains**: Entities, Value Objects, Domain Events, Gateway Interfaces, Domain Services
- **Dependencies**: **MUST NOT** depend on Application or Infrastructure layers
- **Forbidden**: `use App\Application\...` or `use App\Infrastructure\...`

```php
namespace App\Domain\User;

// ✅ Domain Entity with UUID primary key
#[ApiResource(
    operations: [
        new Get(provider: CustomUserProvider::class),
        new Post(processor: CreateUserProcessor::class),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    public function __construct(string $email)
    {
        $this->id = (string) Uuid::v4();
        $this->email = $email;
    }

    public function getId(): string
    {
        return $this->id;
    }

    // Domain methods here
    public function changeEmail(string $newEmail): void
    {
        // Business logic validation
        $this->email = $newEmail;
    }
}

// ✅ Gateway Interface in Domain
interface UserGatewayInterface
{
    public function findById(string $id): ?User;
    public function save(User $user): void;
    public function findByEmail(string $email): ?User;
}
```

**Application Layer** (`api/src/Application/`)

- **Purpose**: Orchestrates use cases, coordinates domain objects, handles application-level logic
- **Contains**: Action classes, Handler classes, Application Services, DTOs
- **Dependencies**: CAN depend on Domain layer, SHOULD NOT depend directly on Infrastructure
- **Naming**: Actions suffixed with "Action", Handlers suffixed with "Handler"

```php
namespace App\Application\User;

// ✅ Action (Command/Query object)
final readonly class CreateUserAction
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
    ) {}
}

// ✅ Handler
final readonly class CreateUserHandler
{
    public function __construct(
        private UserGatewayInterface $userGateway,
        private EventBusInterface $eventBus,
    ) {}

    public function handle(CreateUserAction $action): User
    {
        // Check business rules
        if ($this->userGateway->findByEmail($action->email)) {
            throw new UserAlreadyExistsException();
        }

        $user = new User($action->email, $action->firstName, $action->lastName);
        $this->userGateway->save($user);

        $this->eventBus->dispatch(new UserCreatedEvent($user));

        return $user;
    }
}
```

**Infrastructure Layer** (`api/src/Infrastructure/`)

- **Purpose**: External concerns, database persistence, framework integration
- **Contains**: Gateway implementations, API Platform Processors/Providers, Event Subscribers
- **Dependencies**: CAN depend on Domain and Application layers
- **Naming**: Gateway implementations suffixed with "DoctrineGateway"

```php
namespace App\Infrastructure\User;

// ✅ Repository Implementation
final readonly class UserDoctrineGateway implements UserGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function findById(string $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}

// ✅ API Platform Processor
final readonly class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateUserHandler $handler,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        assert($data instanceof CreateUserAction);
        return $this->handler->handle($data);
    }
}
```

**UserInterface Layer** (`api/src/UserInterface/`)

- **Purpose**: Delivery mechanisms (HTTP controllers, CLI commands)
- **Contains**: Controllers, Commands, Input/Output DTOs
- **Dependencies**: Can depend on Application layer (to dispatch actions)

```php
namespace App\UserInterface\Http;

// ✅ Thin Controller
class GetLogoController extends AbstractController
{
    public function __construct(
        private GetLogoHandler $handler,
    ) {}

    #[Route('/api/logo', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $action = new GetLogoAction();
        $logo = $this->handler->handle($action);

        return $this->json(['url' => $logo->getUrl()]);
    }
}
```

#### API Platform Integration

**Pragmatic Approach**: While following Clean Architecture principles, the project allows some pragmatic compromises with API Platform for efficiency:

- **API Platform attributes** can be defined directly on Domain entities
- **Doctrine ORM attributes** can be used in Domain entities when necessary
- **State Providers/Processors** are placed in the Infrastructure layer
- **Input/Output DTOs** are preferred for complex operations to decouple API contract from Domain model

```php
// ✅ API Platform Resource configuration on Domain Entity
#[ApiResource(
    operations: [
        new Get(provider: CustomUserProvider::class),
        new Post(
            input: CreateUserDto::class,
            processor: CreateUserProcessor::class
        ),
    ]
)]
class User
{
    // Domain entity implementation
}
```

#### Validation Strategy

- Use **Symfony Validator constraints** on DTOs (preferred) or Domain entities
- **Domain validation** for business rules within entities
- **Input validation** at the UserInterface layer

### Frontend (Nuxt.js + Vue 3)

#### Directory Structure

```
pwa/
├── components/         # Vue components
│   ├── Base/          # Base/generic components
│   ├── Form/          # Form-related components
│   ├── Layout/        # Layout components
│   └── Business/      # Business-specific components
├── composables/       # Vue composables
├── layouts/           # Nuxt layouts
├── middleware/        # Route middleware
├── pages/             # Application pages
├── plugins/           # Nuxt plugins
├── stores/            # Pinia stores
├── types/             # TypeScript type definitions
└── utils/             # Utility functions
```

#### Component Guidelines

**Naming Conventions**

- Use PascalCase for component names
- Use descriptive, specific names
- Prefix base components with "Base"

**Component Structure**

```vue
<template>
  <div class="user-profile">
    <!-- Template content -->
  </div>
</template>

<script setup lang="ts">
// Imports
import type { User } from '~/types/user'

// Props and emits
interface Props {
  user: User
  readonly?: boolean
}

interface Emits {
  update: [user: User]
  delete: [id: string]
}

const props = withDefaults(defineProps<Props>(), {
  readonly: false,
})

const emit = defineEmits<Emits>()

// Composables and reactive data
const { $api } = useNuxtApp()
const userStore = useUserStore()

// Computed properties
const isEditable = computed(() => !props.readonly && userStore.canEdit)

// Methods
const handleUpdate = (updatedUser: User) => {
  emit('update', updatedUser)
}

// Lifecycle hooks
onMounted(() => {
  // Component initialization
})
</script>

<style scoped>
.user-profile {
  /* Component-specific styles */
}
</style>
```

## 🧪 Testing Standards

### API Testing (PHPUnit)

- Write unit tests for Domain services and Application handlers
- Write integration tests for Infrastructure gateways
- Write functional tests for API endpoints
- Use data fixtures for consistent test data
- Aim for 80%+ code coverage

```php
// ✅ Unit test for Application Handler
class CreateUserHandlerTest extends TestCase
{
    public function testCreateUserSuccessfully(): void
    {
        $userGateway = $this->createMock(UserGatewayInterface::class);
        $userGateway->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $handler = new CreateUserHandler($userGateway, $this->createMock(EventBusInterface::class));
        $action = new CreateUserAction('test@example.com', 'John', 'Doe');

        $user = $handler->handle($action);

        $this->assertEquals('test@example.com', $user->getEmail());
    }
}

// ✅ Functional test for API endpoint
class UserApiTest extends ApiTestCase
{
    public function testCreateUser(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/users', [
            'json' => [
                'email' => 'test@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ]
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'email' => 'test@example.com'
        ]);
    }
}
```

### Frontend Testing (Vitest)

- Write unit tests for composables and utilities
- Write component tests for complex components
- Mock external dependencies

```typescript
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import UserProfile from '~/components/UserProfile.vue'

describe('UserProfile', () => {
  it('displays user information correctly', () => {
    const user = {
      id: '1',
      email: 'test@example.com',
      firstName: 'John',
      lastName: 'Doe',
    }

    const wrapper = mount(UserProfile, {
      props: { user },
    })

    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('test@example.com')
  })
})
```

### E2E Testing (Playwright)

- Write end-to-end tests for critical user journeys
- Test cross-browser compatibility
- Include visual regression testing when applicable

## 🎨 Code Style

### PHP (ECS - Easy Coding Standard)

- Follow PSR-12 coding standard
- Use strict types declaration (`declare(strict_types=1);`)
- Prefer composition over inheritance
- Use dependency injection extensively
- Use readonly properties when possible (PHP 8.1+)
- Leverage PHP 8.4+ features (typed properties, attributes, etc.)
- **Avoid `final` classes**: While the application is in early development phase, avoid using `final` keyword on classes to maintain testing flexibility and reduce complexity in unit/integration tests (except for migration doctrine/elastic).

### TypeScript/JavaScript (ESLint + Prettier)

- Use TypeScript for all new code
- Prefer functional programming patterns
- Use descriptive variable names
- Avoid `any` type, use proper typing

### CSS/SCSS (Stylelint)

- Use Tailwind CSS utility classes when possible
- Follow BEM methodology for custom CSS
- Use CSS custom properties for theming
- Prefer logical properties over physical ones

## 🔧 Development Workflow

### Git Workflow

1. Create feature branch from `main`
2. Make atomic commits with descriptive messages
3. Write/update tests
4. Update documentation
5. Create pull request
6. Code review and approval
7. Merge to `main`

### Commit Message Format

```
type(scope): description

[optional body]

[optional footer]
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

Examples:

```
feat(user): add user registration use case
fix(folder): correct search query validation
docs(architecture): update clean architecture guide
refactor(auth): extract authentication logic to domain service
```

### Branch Naming

- `feature/feature-name` - New features
- `fix/bug-description` - Bug fixes
- `refactor/component-name` - Code refactoring
- `docs/section-name` - Documentation updates

## 🔄 Continuous Integration

### Pre-commit Hooks

- Code formatting (Prettier, ECS)
- Linting (ESLint, Stylelint, PHPStan)
- Static analysis (PHPStan level 8+)
- Basic tests

### CI Pipeline

1. Install dependencies
2. Run linters and static analysis
3. Run unit tests
4. Run integration tests
5. Build application
6. Deploy to staging (if on main branch)

## 🚀 Performance Guidelines

### API Performance

- Use database indexes appropriately
- Implement proper pagination with API Platform
- Use eager loading to avoid N+1 queries
- Cache expensive operations at Infrastructure layer
- Consider using State Providers for complex queries

### Frontend Performance

- Use lazy loading for routes and components
- Optimize images and assets
- Minimize bundle size
- Implement proper caching strategies

## 🔒 Security Considerations

### API Security

- Validate all input data in DTOs and Domain entities
- Use proper authentication and authorization
- Sanitize output data
- Implement rate limiting
- Use HTTPS in production
- Apply domain-driven validation rules

### Frontend Security

- Validate user input
- Sanitize HTML content
- Use Content Security Policy
- Implement proper CORS configuration

## 📦 Dependency Management

### API Dependencies

- Keep Symfony and API Platform updated
- Review security advisories regularly
- Use Composer for dependency management
- Lock dependency versions in production
- Respect layer dependencies (Domain → Application → Infrastructure → UserInterface)

### Frontend Dependencies

- Use exact versions for critical dependencies
- Regular dependency audits
- Use Yarn for consistent installations
- Monitor for security vulnerabilities

## 🔍 Code Documentation

### API Documentation

- Use OpenAPI/Swagger annotations on API Platform resources
- Document all public endpoints
- Include request/response examples
- Document Domain concepts and business rules
- Maintain up-to-date API documentation

### Frontend Documentation

- Document complex components
- Use JSDoc for TypeScript functions
- Maintain component library documentation
- Document composables and utilities

## 🏛️ Architecture Decision Records (ADRs)

Document significant architectural decisions, especially:

- Changes to layer boundaries
- Introduction of new Domain concepts
- API Platform integration patterns
- Performance optimization strategies

## 📝 Clean Architecture Best Practices

### Do's

- ✅ Keep Domain entities framework-agnostic (except minimal ORM attributes)
- ✅ Use Gateway interfaces in Domain, implement in Infrastructure
- ✅ Name Actions and Handlers consistently with suffixes
- ✅ Keep Controllers thin - delegate to Application layer
- ✅ Use DTOs for complex API input/output
- ✅ Apply business rules in Domain entities and services
- ✅ Handle cross-cutting concerns in Infrastructure layer

### Don'ts

- ❌ Import Infrastructure or Application in Domain layer
- ❌ Put business logic in Controllers or Processors
- ❌ Directly use Doctrine EntityManager in Application layer
- ❌ Mix framework concerns with Domain logic
- ❌ Create circular dependencies between layers
- ❌ Bypass Handler pattern for complex operations
- ❌ Use `final` keyword on classes (current project policy for testing flexibility)

This development guide should be updated as the project evolves and new practices are adopted.
