<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Integration\AbstractApiTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

/**
 * Integration tests for watch file share email notifications.
 *
 * These tests verify that emails are correctly sent when:
 * - A user is added to a watch file (share added)
 * - A user's role is updated on a watch file (share updated)
 * - A user is removed from a watch file (share removed)
 */
class ShareWatchFileNotificationTest extends AbstractApiTestCase
{
    use MailerAssertionsTrait;

    public function testShareWatchFileSendsEmailNotification(): void
    {
        $owner = UserFactory::createOne();
        $userToShare = UserFactory::createOne([
            'email' => 'shared-user@example.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File for Email',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $client->request('POST', \sprintf('/api/watch_files/%s/share', $watchFile->getId()), [
            'json' => [
                'member' => [
                    [
                        'userId' => $userToShare->getId(),
                        'role' => WatchFileUserRole::EDITOR->value,
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify email was sent
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);

        // Verify recipient
        $this->assertEmailAddressContains($email, 'to', 'shared-user@example.com');

        // Verify email contains the watch file URL
        $emailContent = $email->getHtmlBody() ?? $email->getTextBody() ?? '';
        $this->assertStringContainsString('/watch_files/' . $watchFile->getId(), (string) $emailContent);
    }

    public function testUpdateShareRoleSendsEmailNotification(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne([
            'email' => 'updated-user@example.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File for Update',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // First create a share with VIEWER role
        WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $sharedUser,
                'role' => WatchFileUserRole::VIEWER,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Update to EDITOR role
        $client->request('POST', \sprintf('/api/watch_files/%s/share', $watchFile->getId()), [
            'json' => [
                'member' => [
                    [
                        'userId' => $sharedUser->getId(),
                        'role' => WatchFileUserRole::EDITOR->value,
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify email was sent for role update
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);

        $this->assertEmailAddressContains($email, 'to', 'updated-user@example.com');

        // Verify email contains the watch file URL
        $emailContent = $email->getHtmlBody() ?? $email->getTextBody() ?? '';
        $this->assertStringContainsString('/watch_files/' . $watchFile->getId(), (string) $emailContent);
    }

    public function testRemoveShareSendsEmailNotification(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne([
            'email' => 'removed-user@example.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File for Removal',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $watchFileUser = WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $sharedUser,
                'role' => WatchFileUserRole::EDITOR,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $client->request(
            'DELETE',
            \sprintf('/api/watch_files/%s/share/%s', $watchFile->getId(), $watchFileUser->getId())
        );

        $this->assertResponseStatusCodeSame(204);

        // Verify email was sent for removal
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);

        $this->assertEmailAddressContains($email, 'to', 'removed-user@example.com');
    }

    public function testShareOwnerDoesNotSendEmailNotification(): void
    {
        $owner = UserFactory::createOne([
            'email' => 'owner@example.com',
        ]);
        $newOwner = UserFactory::createOne([
            'email' => 'new-owner@example.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File Owner',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Share as owner (should not send email)
        $client->request('POST', \sprintf('/api/watch_files/%s/share', $watchFile->getId()), [
            'json' => [
                'member' => [
                    [
                        'userId' => $newOwner->getId(),
                        'role' => WatchFileUserRole::OWNER->value,
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // No email should be sent when sharing with owner role
        $this->assertEmailCount(0);
    }

    public function testEmailContainsCorrectWatchFileUrl(): void
    {
        $owner = UserFactory::createOne();
        $userToShare = UserFactory::createOne([
            'email' => 'url-test@example.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'URL Test Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $client->request('POST', \sprintf('/api/watch_files/%s/share', $watchFile->getId()), [
            'json' => [
                'member' => [
                    [
                        'userId' => $userToShare->getId(),
                        'role' => WatchFileUserRole::VIEWER->value,
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);

        $emailContent = $email->getHtmlBody() ?? $email->getTextBody() ?? '';

        // Verify the URL format is correct (https://host/watch_files/uuid)
        $expectedUrlPattern = '#https?://[^/]+/watch_files/' . preg_quote((string) $watchFile->getId(), '#') . '#';
        $this->assertMatchesRegularExpression($expectedUrlPattern, (string) $emailContent);
    }
}
