<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Exception;

use App\Domain\Shared\AccessDeniedException;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFoundException;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\WatchFile\Exception\CannotRemoveOwnerException;
use App\Domain\WatchFile\Exception\WatchFileActivationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class DomainExceptionListener
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AsEventListener(event: 'kernel.exception')]
    public function onKernelException(ExceptionEvent $event): void
    {
        $originalException = $event->getThrowable();
        $exception = $this->findInStack($originalException, DomainException::class, 3);

        if ($exception instanceof NotFoundException) {
            $event->setThrowable(new NotFoundHttpException($exception->getMessage(), $originalException));
        } elseif ($exception instanceof AccessDeniedException) {
            $event->setThrowable(new AccessDeniedHttpException($exception->getMessage(), $originalException));
        } elseif ($exception instanceof CannotRemoveOwnerException) {
            $event->setThrowable(new UnprocessableEntityHttpException($exception->getMessage(), $originalException));
        } elseif ($exception instanceof WatchFileActivationException) {
            $translatedMessage = $this->translator->trans($exception->getTranslationKey());

            $event->setThrowable(new UnprocessableEntityHttpException($translatedMessage, $originalException));
        } elseif ($exception instanceof QuotaExceededException) {
            $translatedMessage = $this->translator->trans(
                $exception->getTranslationKey(),
                $exception->getTranslationParameters(),
            );

            $event->setThrowable(
                new TooManyRequestsHttpException(message: $translatedMessage, previous: $originalException)
            );
        }
    }

    /**
     * Recursively searches through the exception stack to find an exception to a specific type.
     *
     * This method traverses the exception chain starting from the original exception,
     * checking each exception and its previous exceptions until it finds one that
     * matches the specified class type or reaches the maximum recursion level.
     *
     * @param \Throwable $original The original exception to start searching from
     * @param string     $needle   The fully qualified class name to search for (e.g., DomainException::class)
     * @param int|null   $level    The maximum recursion depth to search. If null, searches the entire stack
     * @param int        $count    The current recursion level (used internally for tracking)
     *
     * @return \Throwable|null Returns the found exception if it matches the needle class,
     *                         or null if no matching exception is found within the specified level
     *
     * @example
     * // Search for DomainException in the entire stack
     * $exception = $this->findInStack($originalException, DomainException::class);
     *
     * // Search for DomainException only in the first 3 levels
     * $exception = $this->findInStack($originalException, DomainException::class, 3);
     *
     * // Search for NotFoundException in the first 2 levels
     * $exception = $this->findInStack($originalException, NotFoundException::class, 2);
     */
    private function findInStack(\Throwable $original, string $needle, ?int $level = null, int $count = 1): ?\Throwable
    {
        if ($original instanceof $needle) {
            return $original;
        }

        if (null !== $level && $count >= $level) {
            return null;
        }

        if (null !== $original->getPrevious()) {
            return $this->findInStack($original->getPrevious(), $needle, $level, $count + 1);
        }

        return null;
    }
}
