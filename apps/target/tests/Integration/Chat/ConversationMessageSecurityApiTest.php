<?php

declare(strict_types=1);

namespace App\Tests\Integration\Chat;

use App\DataFixtures\Factory\Chat\ConversationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Integration\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ConversationMessageSecurityApiTest extends AbstractApiTestCase
{
    public function testPostMessageAsOwnerIsNotForbidden(): void
    {
        $owner = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request(
            'POST',
            '/api/conversations/' . $conversationId . '/messages',
            [
                'json' => [
                    'content' => 'Test message from owner',
                ],
            ],
        );

        $statusCode = $response->getStatusCode();
        $this->assertNotSame(
            Response::HTTP_FORBIDDEN,
            $statusCode,
            'Owner should not be forbidden from posting messages'
        );
        $this->assertNotSame(Response::HTTP_UNAUTHORIZED, $statusCode, 'Owner should not be unauthorized');
    }

    public function testPostMessageAsEditorIsNotForbidden(): void
    {
        $owner = UserFactory::new()->create();
        $editor = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        WatchFileUserFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $editor,
            'role' => WatchFileUserRole::EDITOR,
        ]);

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($editor);
        $response = $client->request(
            'POST',
            '/api/conversations/' . $conversationId . '/messages',
            [
                'json' => [
                    'content' => 'Test message from editor',
                ],
            ],
        );

        $statusCode = $response->getStatusCode();
        $this->assertNotSame(
            Response::HTTP_FORBIDDEN,
            $statusCode,
            'Editor should not be forbidden from posting messages'
        );
        $this->assertNotSame(Response::HTTP_UNAUTHORIZED, $statusCode, 'Editor should not be unauthorized');
    }

    public function testPostMessageAsViewerIsForbidden(): void
    {
        $owner = UserFactory::new()->create();
        $viewer = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        WatchFileUserFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $viewer,
            'role' => WatchFileUserRole::VIEWER,
        ]);

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($viewer);
        $client->request(
            'POST',
            '/api/conversations/' . $conversationId . '/messages',
            [
                'json' => [
                    'content' => 'Test message from viewer',
                ],
            ],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPostMessageAsUnrelatedUserIsForbidden(): void
    {
        $owner = UserFactory::new()->create();
        $unrelatedUser = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();

        $client = $this->createAuthenticatedClient($unrelatedUser);
        $client->request(
            'POST',
            '/api/conversations/' . $conversationId . '/messages',
            [
                'json' => [
                    'content' => 'Test message from unrelated user',
                ],
            ],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPostMessageAsUnauthenticatedUserIsUnauthorized(): void
    {
        $owner = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withOwnedBy($owner)
            ->create();

        $conversation = ConversationFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $conversationId = $conversation->getId();

        $client = self::createClient();
        $client->request(
            'POST',
            '/api/conversations/' . $conversationId . '/messages',
            [
                'json' => [
                    'content' => 'Test message from anonymous',
                ],
            ],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testPostMessageToNonExistentConversationReturnsForbidden(): void
    {
        $user = UserFactory::new()->create();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request(
            'POST',
            '/api/conversations/' . $nonExistentId . '/messages',
            [
                'json' => [
                    'content' => 'Test message',
                ],
            ],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
