<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\Chat\MessageFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Chat\MessageStatus;
use App\Domain\WatchFile\WatchFileUserRole;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for Message retry operation security.
 *
 * These tests verify that the retry operation on messages correctly enforces
 * access control based on WatchFile permissions via the Conversation relationship.
 */
class MessageRetrySecurityApiTest extends AbstractApiTestCase
{
    public function testRetryMessageRequiresAuthentication(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
        ]);
        $message->setTextContent('Test message content');

        $client = self::createClient();

        $client->request('POST', '/api/messages/' . $message->getId() . '/retry');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testOwnerCanRetryMessage(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);
        $message->setTextContent('Test message content');

        $client = $this->createAuthenticatedClient($user);

        $client->request('POST', '/api/messages/' . $message->getId() . '/retry');

        $this->assertResponseIsSuccessful();
    }

    public function testEditorCanRetryMessage(): void
    {
        $owner = UserFactory::createOne();
        $editor = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->withUser($editor, WatchFileUserRole::EDITOR)
            ->create();

        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);
        $message->setTextContent('Test message content');

        $client = $this->createAuthenticatedClient($editor);

        $client->request('POST', '/api/messages/' . $message->getId() . '/retry');

        $this->assertResponseIsSuccessful();
    }

    public function testViewerCannotRetryMessage(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->create();

        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);
        $message->setTextContent('Test message content');

        $client = $this->createAuthenticatedClient($viewer);

        $client->request('POST', '/api/messages/' . $message->getId() . '/retry');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUserWithoutAccessCannotRetryMessage(): void
    {
        $owner = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($owner)->create();
        $conversation = ConversationFactory::createOne([
            'watchFile' => $watchFile,
        ]);
        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'status' => MessageStatus::Error,
            'retryCount' => 0,
        ]);
        $message->setTextContent('Test message content');

        $client = $this->createAuthenticatedClient($otherUser);

        $client->request('POST', '/api/messages/' . $message->getId() . '/retry');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
