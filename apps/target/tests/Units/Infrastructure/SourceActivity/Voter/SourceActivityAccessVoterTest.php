<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\SourceActivity\Voter;

use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\SourceActivity\Voter\SourceActivityAccessVoter;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(SourceActivityAccessVoter::class)]
class SourceActivityAccessVoterTest extends TestCase
{
    private SourceActivityAccessVoter $voter;
    private RequestStack&Stub $requestStack;
    private NullSourceGateway $sourceGateway;
    private Security&Stub $security;
    private TokenInterface&Stub $token;

    protected function setUp(): void
    {
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->sourceGateway = new NullSourceGateway();
        $this->security = $this->createStub(Security::class);
        $this->token = $this->createStub(TokenInterface::class);

        $this->voter = new SourceActivityAccessVoter($this->requestStack, $this->sourceGateway, $this->security);
    }

    public function testSupportsSourceActivityViewWithNullSubject(): void
    {
        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        // Will be DENIED (not ABSTAIN) because token user is null, but voter supports the attribute
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForUnsupportedAttribute(): void
    {
        $result = $this->voter->vote($this->token, null, ['UNSUPPORTED_ATTRIBUTE']);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForNonNullSubject(): void
    {
        $result = $this->voter->vote($this->token, new \stdClass(), [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testDeniesAccessWhenUserIsNotLoggedIn(): void
    {
        $this->token->method('getUser')
->willReturn(null);

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesAccessWhenUserIsNotAppUser(): void
    {
        $this->token->method('getUser')
->willReturn($this->createStub(UserInterface::class));

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesAccessWhenNoRequest(): void
    {
        $this->token->method('getUser')
->willReturn($this->createStub(User::class));
        $this->requestStack->method('getCurrentRequest')
->willReturn(null);

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesAccessWhenNoSourceIdInRequest(): void
    {
        $this->token->method('getUser')
->willReturn($this->createStub(User::class));

        $request = new Request();
        $this->requestStack->method('getCurrentRequest')
->willReturn($request);

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesAccessWhenSourceNotFound(): void
    {
        $this->token->method('getUser')
->willReturn($this->createStub(User::class));

        $request = new Request();
        $request->attributes->set('sourceId', 'non-existent-id');
        $this->requestStack->method('getCurrentRequest')
->willReturn($request);

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsAccessWhenUserHasWatchFileViewPermission(): void
    {
        $this->token->method('getUser')
->willReturn($this->createStub(User::class));

        $request = new Request();
        $request->attributes->set('sourceId', 'source-id');
        $this->requestStack->method('getCurrentRequest')
->willReturn($request);

        $watchFile = $this->createStub(WatchFile::class);
        $source = $this->createStub(Source::class);
        $source->method('getId')
->willReturn('source-id');
        $source->method('getWatchFile')
->willReturn($watchFile);
        $this->sourceGateway->save($source);

        $this->security->method('isGranted')
            ->willReturn(true);

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesAccessWhenUserLacksWatchFileViewPermission(): void
    {
        $this->token->method('getUser')
->willReturn($this->createStub(User::class));

        $request = new Request();
        $request->attributes->set('sourceId', 'source-id');
        $this->requestStack->method('getCurrentRequest')
->willReturn($request);

        $watchFile = $this->createStub(WatchFile::class);
        $source = $this->createStub(Source::class);
        $source->method('getId')
->willReturn('source-id');
        $source->method('getWatchFile')
->willReturn($watchFile);
        $this->sourceGateway->save($source);

        $this->security->method('isGranted')
            ->willReturn(false);

        $result = $this->voter->vote($this->token, null, [SourceActivityAccessVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }
}
