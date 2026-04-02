<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class EnumConstraint extends Constraint
{
    public string $message = 'validators.enum.invalid_value';
    public string $enumClass;

    public function __construct(
        string $enumClass,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->enumClass = $enumClass;
        $this->message = $message ?? $this->message;

        parent::__construct(groups: $groups, payload: $payload);
    }
}
