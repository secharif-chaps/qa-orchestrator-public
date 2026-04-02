<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;

/**
 * Simple integration test to verify the EventActors endpoint is working.
 */
class EventActorsApiSimpleTest extends AbstractApiTestCase
{
    public function testEventActorsEndpointExists(): void
    {
        // Create test data
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
        ]);
        $client = $this->createAuthenticatedClient($user);

        // Test with a fake UUID to see if the endpoint is accessible
        $fakeWatchFileUuid = '550e8400-e29b-41d4-a716-446655440000';
        $fakeEventUuid = '550e8400-e29b-41d4-a716-446655440001';

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $fakeWatchFileUuid,
            $fakeEventUuid
        ));

        // Should get 404 (not found) not 500 (server error) - meaning the endpoint exists and works
        $this->assertResponseStatusCodeSame(404);

        $data = $response->toArray(false);
        $this->assertStringContainsString('not found', $data['detail']);
    }

    public function testEventActorsEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $fakeWatchFileUuid = '550e8400-e29b-41d4-a716-446655440000';
        $fakeEventUuid = '550e8400-e29b-41d4-a716-446655440001';

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $fakeWatchFileUuid,
            $fakeEventUuid
        ));

        // Should get 401 (unauthorized) when not authenticated
        $this->assertResponseStatusCodeSame(401);
    }
}
