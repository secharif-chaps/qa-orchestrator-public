<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Shared\Constraint;

use App\Domain\Actor\ActorStatus;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use App\Infrastructure\Shared\Constraint\EnumConstraintValidator;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class EnumConstraintValidatorTest extends TestCase
{
    private EnumConstraintValidator $validator;
    private ExecutionContextInterface&Stub $context;
    private ConstraintViolationBuilderInterface&Stub $violationBuilder;

    protected function setUp(): void
    {
        $this->context = $this->createStub(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $this->buildValidator();
    }

    private function buildValidator(): void
    {
        $this->validator = new EnumConstraintValidator();
        $this->validator->initialize($this->context);
    }

    public function testValidEnumValue(): void
    {
        $contextMock = $this->createMock(ExecutionContextInterface::class);
        $this->context = $contextMock;
        $this->buildValidator();
        $constraint = new EnumConstraint(ActorStatus::class);

        $contextMock->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate('active', $constraint);
    }

    public function testInvalidEnumValue(): void
    {
        $contextMock = $this->createMock(ExecutionContextInterface::class);
        $this->context = $contextMock;
        $violationBuilderMock = $this->createMock(ConstraintViolationBuilderInterface::class);
        $this->violationBuilder = $violationBuilderMock;
        $this->buildValidator();
        $constraint = new EnumConstraint(ActorStatus::class);

        $violationBuilderMock->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();

        $violationBuilderMock->expects($this->once())
            ->method('addViolation');

        $contextMock->expects($this->once())
            ->method('buildViolation')
            ->with('validators.enum.invalid_value')
            ->willReturn($this->violationBuilder);

        $this->validator->validate('invalid_status', $constraint);
    }

    public function testNullValue(): void
    {
        $contextMock = $this->createMock(ExecutionContextInterface::class);
        $this->context = $contextMock;
        $this->buildValidator();
        $constraint = new EnumConstraint(ActorStatus::class);

        $contextMock->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate(null, $constraint);
    }

    public function testEmptyStringValue(): void
    {
        $contextMock = $this->createMock(ExecutionContextInterface::class);
        $this->context = $contextMock;
        $this->buildValidator();
        $constraint = new EnumConstraint(ActorStatus::class);

        $contextMock->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate('', $constraint);
    }

    public function testNonStringValue(): void
    {
        $constraint = new EnumConstraint(ActorStatus::class);

        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(123, $constraint);
    }

    public function testNonEnumClass(): void
    {
        $constraint = new EnumConstraint(\stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "stdClass" is not a backed enum');

        $this->validator->validate('active', $constraint);
    }

    public function testNonExistentClass(): void
    {
        $constraint = new EnumConstraint('NonExistentClass');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Enum class "NonExistentClass" does not exist');

        $this->validator->validate('active', $constraint);
    }
}
