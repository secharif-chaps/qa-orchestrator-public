<?php

namespace App\Tests\Units\Infrastructure\Shared;

use App\Domain\Shared\AccessDeniedException;
use App\Domain\Shared\DomainException;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\User\UserNotFoundException;
use App\Infrastructure\Shared\Exception\DomainExceptionListener;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DomainExceptionListenerTest extends TestCase
{
    use MockHelpersTrait;
    private DomainExceptionListener $listener;
    private TranslatorInterface&Stub $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->buildListener();
    }

    private function buildListener(): void
    {
        $this->listener = new DomainExceptionListener($this->translator);
    }

    public function testOnKernelExceptionWithNotFoundException(): void
    {
        $notFoundException = new UserNotFoundException('User not found');
        $wrappedException = new \Exception('Wrapped', 0, $notFoundException);
        $finalException = new \Exception('Final', 0, $wrappedException);

        $event = $this->createExceptionEvent($finalException);

        $this->listener->onKernelException($event);

        $throwable = $event->getThrowable();
        $this->assertInstanceOf(NotFoundHttpException::class, $throwable);
        $this->assertEquals('User not found', $throwable->getMessage());
        $this->assertSame($finalException, $throwable->getPrevious());
    }

    public function testOnKernelExceptionWithAccessDeniedException(): void
    {
        $accessDeniedException = new AccessDeniedException('Access denied');
        $finalException = new \Exception('Final', 0, $accessDeniedException);

        $event = $this->createExceptionEvent($finalException);

        $this->listener->onKernelException($event);

        $throwable = $event->getThrowable();
        $this->assertInstanceOf(AccessDeniedHttpException::class, $throwable);
        $this->assertEquals('Access denied', $throwable->getMessage());
        $this->assertSame($finalException, $throwable->getPrevious());
    }

    public function testOnKernelExceptionWithQuotaExceededException(): void
    {
        $quotaExceededException = QuotaExceededException::forWatchFileActive(2, 2);
        $wrappedException = new \Exception('Wrapped', 0, $quotaExceededException);
        $finalException = new \Exception('Final', 0, $wrappedException);

        $request = new Request();
        $event = $this->createExceptionEvent($finalException, $request);

        $translator = $this->createMockWithExpectations(TranslatorInterface::class);
        $this->translator = $translator;
        $this->buildListener();

        $expectedMessage = 'You cannot have more than 2 active watchfiles at the same time. Current active watchfiles: 2.';
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('quota.watchfile_max_active_per_user', [
                'limit' => 2,
                'current' => 2,
            ])
            ->willReturn($expectedMessage);

        $this->listener->onKernelException($event);

        $throwable = $event->getThrowable();
        $this->assertInstanceOf(TooManyRequestsHttpException::class, $throwable);

        /** @var TooManyRequestsHttpException $throwable */
        $this->assertEquals($expectedMessage, $throwable->getMessage());
        $this->assertSame($finalException, $throwable->getPrevious());
        $this->assertEquals(429, $throwable->getStatusCode());
    }

    public function testOnKernelExceptionWithNoDomainException(): void
    {
        $originalException = new \RuntimeException('Some error');
        $event = $this->createExceptionEvent($originalException);

        $this->listener->onKernelException($event);

        $throwable = $event->getThrowable();
        $this->assertSame($originalException, $throwable);
    }

    public function testOnKernelExceptionWithDomainExceptionBeyondLimit(): void
    {
        // Create a deep exception chain that goes beyond the 3-level limit
        $userNotFoundException = new UserNotFoundException('User not found');
        $exception1 = new UserNotFoundException('Level 1', 0, $userNotFoundException);
        $exception2 = new UserNotFoundException('Level 2', 0, $exception1);
        $exception3 = new \Exception('Level 3', 0, $exception2);
        $exception4 = new \Exception('Level 4', 0, $exception3);
        $finalException = new \Exception('Final', 0, $exception4);

        $event = $this->createExceptionEvent($finalException);

        $this->listener->onKernelException($event);

        $throwable = $event->getThrowable();

        // Should not be transformed, as the domain exception is too deep
        $this->assertSame($finalException->getMessage(), $throwable->getMessage());
        $this->assertNotInstanceOf(DomainException::class, $throwable);
    }

    private function createExceptionEvent(\Throwable $exception, ?Request $request = null): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request ??= new Request();

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
