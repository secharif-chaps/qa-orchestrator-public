<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\ActorTypesProvider;
use App\Tests\Utils\MockHelpersTrait;
use App\UserInterface\Dto\Actor\ActorTypesDto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ActorTypesProviderTest extends TestCase
{
    use MockHelpersTrait;
    private NullWatchFileActorGateway $watchFileActorGateway;
    private NullWatchFileGateway $watchFileGateway;
    private Security $security;
    private RequestStack $requestStack;
    private ActorTypesProvider $provider;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->watchFileActorGateway = new NullWatchFileActorGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createStub(Security::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->operation = $this->createStub(Operation::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new ActorTypesProvider(
            $this->watchFileActorGateway,
            $this->watchFileGateway,
            $this->security,
            $this->requestStack
        );
    }

    private function setupWatchFile(string $watchFileId): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));

        // Use reflection to set the ID since it's normally set by the database
        $reflection = new \ReflectionClass($watchFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($watchFile, $watchFileId);

        // Store the watch file in our null gateway
        $this->watchFileGateway->save($watchFile);
    }

    /**
     * @param MockObject&RequestStack $requestStack
     */
    private function mockNoStatusParameter(MockObject $requestStack): void
    {
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);
    }

    public function testProvideReturnsActorTypesDto(): void
    {
        $requestStack = $this->createMockWithExpectations(RequestStack::class);
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->requestStack = $requestStack;
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $expectedTypes = [
            [
                'type' => 'competitor',
                'count' => 5,
            ],
            [
                'type' => 'supplier',
                'count' => 3,
            ],
            [
                'type' => 'partner',
                'count' => 2,
            ],
        ];

        $this->watchFileActorGateway->setActorTypesCounts($watchFileId, $expectedTypes);
        $this->setupWatchFile($watchFileId);
        $this->mockNoStatusParameter($requestStack);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(ActorTypesDto::class, $result);
        $this->assertEquals($expectedTypes, $result->types);
    }

    public function testProvideWithEmptyTypes(): void
    {
        $requestStack = $this->createMockWithExpectations(RequestStack::class);
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->requestStack = $requestStack;
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440001';
        $expectedTypes = [];

        $this->watchFileActorGateway->setActorTypesCounts($watchFileId, $expectedTypes);
        $this->setupWatchFile($watchFileId);
        $this->mockNoStatusParameter($requestStack);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(ActorTypesDto::class, $result);
        $this->assertEquals([], $result->types);
    }

    public function testProvideThrowsExceptionWhenWatchFileIdMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided in the URL');

        $this->provider->provide($this->operation, [], []);
    }

    public function testProvideThrowsExceptionWhenWatchFileIdIsNotString(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $this->provider->provide(
            $this->operation,
            [
                'watchFileId' => 123,
            ], // Integer instead of string
            []
        );
    }

    public function testProvideThrowsExceptionWhenWatchFileIdIsNull(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided in the URL');

        $this->provider->provide($this->operation, [], []);
    }

    public function testProvideThrowsExceptionWhenWatchFileIdIsInteger(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $this->provider->provide($this->operation, [
            'watchFileId' => 123,
        ], []);
    }

    public function testProvideThrowsExceptionWhenWatchFileIdIsArray(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $this->provider->provide($this->operation, [
            'watchFileId' => ['invalid', 'array'],
        ], []);
    }

    public function testProvideWithSingleType(): void
    {
        $requestStack = $this->createMockWithExpectations(RequestStack::class);
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->requestStack = $requestStack;
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440002';
        $expectedTypes = [
            [
                'type' => 'competitor',
                'count' => 1,
            ],
        ];

        $this->watchFileActorGateway->setActorTypesCounts($watchFileId, $expectedTypes);
        $this->setupWatchFile($watchFileId);
        $this->mockNoStatusParameter($requestStack);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(ActorTypesDto::class, $result);
        $this->assertCount(1, $result->types);
        $this->assertEquals('competitor', $result->types[0]['type']);
        $this->assertEquals(1, $result->types[0]['count']);
    }

    public function testProvideWithLargeNumberOfTypes(): void
    {
        $requestStack = $this->createMockWithExpectations(RequestStack::class);
        $securityMock = $this->createMockWithExpectations(Security::class);
        $this->requestStack = $requestStack;
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440003';
        $expectedTypes = [];

        // Generate types using valid ActorType values
        $validTypes = ['competitor', 'partner', 'supplier', 'customer', 'regulator', 'subsidiary', 'parent', 'other'];
        for ($i = 0; $i < 100; ++$i) {
            $expectedTypes[] = [
                'type' => $validTypes[$i % \count($validTypes)],
                'count' => $i + 1,
            ];
        }

        $this->watchFileActorGateway->setActorTypesCounts($watchFileId, $expectedTypes);
        $this->setupWatchFile($watchFileId);
        $this->mockNoStatusParameter($requestStack);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(ActorTypesDto::class, $result);
        $this->assertCount(100, $result->types);
        $this->assertEquals($expectedTypes, $result->types);
    }
}
