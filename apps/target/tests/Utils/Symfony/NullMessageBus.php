<?php

declare(strict_types=1);

namespace App\Tests\Utils\Symfony;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

class NullMessageBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    private array $dispatchedMessages = [];

    /**
     * @param (\Closure(object, list<StampInterface>): mixed)|null $fakeHandler
     */
    public function __construct(
        public ?\Closure $fakeHandler = null,
    ) {
    }

    /**
     * @param list<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatchedMessages[] = $message;

        if (null !== $this->fakeHandler) {
            $result = ($this->fakeHandler)($message, $stamps);
            $handledStamp = new HandledStamp($result, 'fake_handler');

            $stamps = [...$stamps, $handledStamp];
        }

        return new Envelope($message, $stamps);
    }

    /**
     * @return list<object>
     */
    public function getDispatchedMessages(): array
    {
        return $this->dispatchedMessages;
    }

    /**
     * @param class-string<object> $messageClass
     */
    public function hasDispatched(string $messageClass): bool
    {
        foreach ($this->dispatchedMessages as $message) {
            if ($message instanceof $messageClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<object> $messageClass
     */
    public function countDispatched(string $messageClass): int
    {
        $count = 0;
        foreach ($this->dispatchedMessages as $message) {
            if ($message instanceof $messageClass) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $messageClass
     *
     * @return T|null
     */
    public function getFirstDispatched(string $messageClass): ?object
    {
        foreach ($this->dispatchedMessages as $message) {
            if ($message instanceof $messageClass) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $messageClass
     *
     * @return T|null
     */
    public function getLastDispatched(string $messageClass): ?object
    {
        foreach (array_reverse($this->dispatchedMessages) as $message) {
            if ($message instanceof $messageClass) {
                return $message;
            }
        }

        return null;
    }

    public function clear(): void
    {
        $this->dispatchedMessages = [];
    }
}
