<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\Share\ShareWatchFileNotification;
use App\Application\WatchFile\Share\ShareWatchFileNotificationFactory;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileUser;
use App\Infrastructure\Url\FrontendUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DummyNotificationFactory extends ShareWatchFileNotificationFactory
{
    /**
     * @var list<ShareWatchFileNotification>
     */
    public array $added = [];

    /**
     * @var list<ShareWatchFileNotification>
     */
    public array $updated = [];

    /**
     * @var list<ShareWatchFileNotification>
     */
    public array $removed = [];

    public function __construct(
        ?Environment $twig = null,
        ?TranslatorInterface $translator = null,
        ?FrontendUrlGenerator $frontendUrlGenerator = null,
    ) {
        // Create minimal mock instances for parent constructor
        $twig ??= new Environment(new ArrayLoader());
        $translator ??= new class implements TranslatorInterface {
            use TranslatorTrait;
        };
        $frontendUrlGenerator ??= self::createMockFrontendUrlGenerator();
        parent::__construct($twig, $translator, $frontendUrlGenerator);
    }

    private static function createMockFrontendUrlGenerator(): FrontendUrlGenerator
    {
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('test.local');

        $urlGenerator = new class($context) implements UrlGeneratorInterface {
            public function __construct(
                private readonly RequestContext $context,
            ) {
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(
                string $name,
                array $parameters = [],
                int $referenceType = self::ABSOLUTE_PATH,
            ): string {
                return '';
            }
        };

        return new FrontendUrlGenerator($urlGenerator);
    }

    public function makeAddedNotification(
        WatchFileUser $watchFileUser,
        User $addedBy,
        \DateTimeImmutable $addedAt,
    ): ShareWatchFileNotification {
        $notification = new ShareWatchFileNotification('Test Added Subject', 'Test added content');

        $this->added[] = $notification;

        return $notification;
    }

    public function makeUpdatedNotification(
        WatchFileUser $watchFileUser,
        User $updatedBy,
        \DateTimeImmutable $updatedAt,
    ): ShareWatchFileNotification {
        $notification = new ShareWatchFileNotification('Test Updated Subject', 'Test updated content');

        $this->updated[] = $notification;

        return $notification;
    }

    public function makeRemovedNotification(
        WatchFileUser $watchFileUser,
        User $removedBy,
        \DateTimeImmutable $removedAt,
    ): ShareWatchFileNotification {
        $notification = new ShareWatchFileNotification('Test Removed Subject', 'Test removed content');

        $this->removed[] = $notification;

        return $notification;
    }
}
