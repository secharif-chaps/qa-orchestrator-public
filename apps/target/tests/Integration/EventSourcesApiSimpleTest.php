<?php

declare(strict_types=1);

namespace App\Tests\Integration;

class EventSourcesApiSimpleTest extends AbstractApiTestCase
{
    public function testEventSourcesEndpointExists(): void
    {
        $client = $this->createClient();
        $client->request('GET', '/api/watch_files/test-id/timeline/test-event-id/sources');

        // The endpoint should exist (not 404 for missing route)
        // It should return 401 (unauthorized) since we're not authenticated
        $this->assertResponseStatusCodeSame(401);
    }

    public function testEventSourcesEndpointRequiresAuthentication(): void
    {
        $client = $this->createClient();
        $client->request('GET', '/api/watch_files/any-id/timeline/any-event-id/sources');

        $this->assertResponseStatusCodeSame(401);
    }
}
