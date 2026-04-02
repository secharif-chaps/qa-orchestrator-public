<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class EnumConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EnumConstraint) {
            throw new UnexpectedTypeException($constraint, EnumConstraint::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!class_exists($constraint->enumClass)) {
            throw new \InvalidArgumentException(\sprintf('Enum class "%s" does not exist', $constraint->enumClass));
        }

        if (!is_subclass_of($constraint->enumClass, \BackedEnum::class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" is not a backed enum', $constraint->enumClass));
        }

        try {
            $constraint->enumClass::from($value);
        } catch (\ValueError) {
            $choices = array_map(fn ($case) => $case->value, $constraint->enumClass::cases());
            $choicesString = implode(', ', $choices);
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setParameter('{{ enum_class }}', $constraint->enumClass)
                ->setParameter('{{ choices }}', $choicesString)
                ->addViolation();
        }
    }
}
