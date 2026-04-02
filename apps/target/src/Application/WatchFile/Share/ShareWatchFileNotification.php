<?php

namespace App\Application\WatchFile\Share;

use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class ShareWatchFileNotification extends Notification implements EmailNotificationInterface
{
    public const SHARE_ADDED = 'watchfile.share.added';
    public const SHARE_UPDATED = 'watchfile.share.updated';
    public const SHARE_REMOVED = 'watchfile.share.removed';

    public function __construct(
        string $subject,
        private readonly string $markdownContent,
    ) {
        parent::__construct($subject, ['email']);
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $email = NotificationEmail::asPublicEmail()
            ->to($recipient->getEmail())
            ->subject($this->getSubject())
            ->markdown($this->markdownContent)
        ;

        return new EmailMessage($email);
    }
}
