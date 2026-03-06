# DTO and Validation Patterns

DTOs (Data Transfer Objects) define the shape of API input/output and validation rules.

## Directory Structure

```
api/src/UserInterface/Dto/
├── {BoundedContext}/
│   ├── Create{Entity}Dto.php
│   ├── Update{Entity}Dto.php
│   └── {Custom}InputDto.php
└── Shared/
    └── PaginationDto.php
```

## Input DTO Pattern

```php
<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\WatchFile;

use App\Domain\WatchFile\WatchFileType;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateWatchFileDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'watchfile.name.not_blank')]
        #[Assert\Length(
            min: 3,
            max: 255,
            minMessage: 'watchfile.name.min_length',
            maxMessage: 'watchfile.name.max_length',
        )]
        public string $name,

        #[Assert\Length(max: 2000)]
        public ?string $description = null,

        #[EnumConstraint(enumClass: WatchFileType::class)]
        public ?string $type = null,
    ) {}
}
```

## Validation Constraints

### Standard Constraints

```php
<?php

declare(strict_types=1);

namespace App\UserInterface\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateUserDto
{
    public function __construct(
        // Required string
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,

        // Valid email
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,

        // Optional URL
        #[Assert\Url]
        public ?string $website = null,

        // UUID format
        #[Assert\Uuid]
        public ?string $referrerId = null,

        // Choice from list
        #[Assert\Choice(choices: ['admin', 'user', 'guest'])]
        public string $role = 'user',

        // Numeric range
        #[Assert\Range(min: 0, max: 100)]
        public int $priority = 0,

        // Date format
        #[Assert\Date]
        public ?string $birthDate = null,

        // Regex pattern
        #[Assert\Regex(pattern: '/^[A-Z]{2,3}$/')]
        public ?string $countryCode = null,
    ) {}
}
```

### Custom EnumConstraint

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class EnumConstraint extends Constraint
{
    public string $message = 'The value "{{ value }}" is not a valid choice. Valid choices are: {{ choices }}.';

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public function __construct(
        public string $enumClass,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }
}
```

### EnumConstraint Validator

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EnumConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EnumConstraint) {
            throw new UnexpectedTypeException($constraint, EnumConstraint::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $enumClass = $constraint->enumClass;
        $cases = array_map(
            fn (\BackedEnum $case) => $case->value,
            $enumClass::cases(),
        );

        if (!in_array($value, $cases, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', (string) $value)
                ->setParameter('{{ choices }}', implode(', ', $cases))
                ->addViolation();
        }
    }
}
```

## Nested DTO Validation

```php
<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\WatchFile;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ShareWatchFileInputDto
{
    /**
     * @param list<ShareTargetDto> $targets
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $watchFileId,

        #[Assert\Valid]
        #[Assert\Count(min: 1, max: 50)]
        public array $targets,
    ) {}
}

readonly class ShareTargetDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,

        #[EnumConstraint(enumClass: WatchFileUserRole::class)]
        public string $role,
    ) {}
}
```

## Chat Message DTO (Real Basil Example)

```php
<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Chat;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

readonly class UserMessageDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 10000)]
        #[Groups(['message:write'])]
        public string $content,
    ) {}
}
```

## Using DTOs in ApiResource

### Input DTO

```php
#[ApiResource(
    operations: [
        new Post(
            input: CreateWatchFileDto::class,
            denormalizationContext: ['groups' => ['watchfile:write']],
            processor: WatchFileProcessor::class,
        ),
    ],
)]
```

### Output DTO

```php
#[ApiResource(
    operations: [
        new Get(
            output: WatchFileOutputDto::class,
            normalizationContext: ['groups' => ['watchfile:read']],
            provider: WatchFileProvider::class,
        ),
    ],
)]
```

### Input and Output

```php
#[ApiResource(
    operations: [
        new Patch(
            input: UpdateWatchFileDto::class,
            output: WatchFileOutputDto::class,
            processor: UpdateWatchFileProcessor::class,
        ),
    ],
)]
```

## Serialization Groups

```php
<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Symfony\Component\Serializer\Attribute\Groups;

class WatchFile
{
    #[Groups(['watchfile:read', 'watchfile:list'])]
    private string $id;

    #[Groups(['watchfile:read', 'watchfile:list', 'watchfile:write'])]
    private string $name;

    #[Groups(['watchfile:read', 'watchfile:write'])]
    private ?string $description = null;

    #[Groups(['watchfile:read'])]
    private \DateTimeImmutable $createdAt;

    // Computed property, read-only
    #[Groups(['watchfile:read'])]
    private bool $isFavorite = false;
}
```

## Validation Error Response

API Platform returns 422 Unprocessable Entity with violations:

```json
{
    "@context": "/api/contexts/ConstraintViolationList",
    "@type": "ConstraintViolationList",
    "violations": [
        {
            "propertyPath": "name",
            "message": "This value should not be blank.",
            "code": "c1051bb4-d103-4f74-8988-acbcafc7fdc3"
        },
        {
            "propertyPath": "type",
            "message": "The value \"invalid\" is not a valid choice. Valid choices are: monitoring, analysis, report."
        }
    ]
}
```

## Key Rules

1. **Readonly DTOs**: All DTOs should be `readonly` classes
2. **Constructor Promotion**: Use constructor property promotion
3. **Assert\Valid for Nested**: Use `#[Assert\Valid]` for nested DTOs
4. **EnumConstraint**: Use custom constraint for enum validation
5. **Groups for Context**: Use serialization groups to control visibility
6. **Translated Messages**: Use translation keys in constraint messages
7. **Nullable Defaults**: Optional fields should have `= null` default
8. **PHPDoc for Arrays**: Document array types with `@param list<Type>`
