<?php

namespace App\Application\WatchFile\Share;

use App\Domain\Url\FrontendUrlGeneratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ShareWatchFileNotificationFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly FrontendUrlGeneratorInterface $frontendUrlGenerator,
    ) {
    }

    public function makeAddedNotification(
        WatchFileUser $watchFileUser,
        User $addedBy,
        \DateTimeImmutable $addedAt,
    ): ShareWatchFileNotification {
        return $this->createNotification(
            ShareWatchFileNotification::SHARE_ADDED,
            'share_notification/added',
            'watchfile.share_notification.added_subject',
            $watchFileUser,
            $addedBy,
            $addedAt,
        );
    }

    public function makeUpdatedNotification(
        WatchFileUser $watchFileUser,
        User $updatedBy,
        \DateTimeImmutable $updatedAt,
    ): ShareWatchFileNotification {
        return $this->createNotification(
            ShareWatchFileNotification::SHARE_UPDATED,
            'share_notification/updated',
            'watchfile.share_notification.updated_subject',
            $watchFileUser,
            $updatedBy,
            $updatedAt,
        );
    }

    public function makeRemovedNotification(
        WatchFileUser $watchFileUser,
        User $removedBy,
        \DateTimeImmutable $removedAt,
    ): ShareWatchFileNotification {
        return $this->createNotification(
            ShareWatchFileNotification::SHARE_REMOVED,
            'share_notification/removed',
            'watchfile.share_notification.removed_subject',
            $watchFileUser,
            $removedBy,
            $removedAt,
        );
    }

    /**
     * @param ShareWatchFileNotification::SHARE_* $type
     */
    private function createNotification(
        string $type,
        string $templatePath,
        string $subjectTranslationKey,
        WatchFileUser $watchFileUser,
        User $actionBy,
        \DateTimeImmutable $actionAt,
    ): ShareWatchFileNotification {
        $subject = $this->translator->trans($subjectTranslationKey, domain: 'email');

        $watchFileId = $watchFileUser->getWatchFile()?->getId();
        $watchFileUrl = null !== $watchFileId
            ? $this->frontendUrlGenerator->generateWatchFileUrl((string) $watchFileId)
            : '';

        $content = $this->twig->render(
            \sprintf('emails/watchfile/%s.%s.md.twig', $templatePath, $this->translator->getLocale()),
            [
                'watchFileUser' => $watchFileUser,
                'actionBy' => $actionBy,
                'actionAt' => $actionAt,
                'type' => $type,
                'watchFileUrl' => $watchFileUrl,
            ]
        );

        return new ShareWatchFileNotification($subject, $content);
    }
}
