<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile\Voter;

use App\Domain\Shared\HasWatchFileInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileUserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class WatchFileVoterTest extends TestCase
{
    private WatchFileVoter $voter;
    private WatchFileUserGatewayInterface&Stub $watchFileUserGateway;
    private TokenInterface&Stub $token;

    /** @var \ReflectionClass<WatchFileVoter> */
    private \ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->watchFileUserGateway = $this->createStub(WatchFileUserGatewayInterface::class);
        $this->voter = new WatchFileVoter($this->watchFileUserGateway);
        $this->token = $this->createStub(TokenInterface::class);
        $this->reflection = new \ReflectionClass(WatchFileVoter::class);
    }

    public function testSupportsWithValidAttributesAndWatchFile(): void
    {
        $supportsMethod = $this->reflection->getMethod('supports');
        $supportsMethod->setAccessible(true);

        $watchFile = $this->createStub(WatchFile::class);

        $this->assertTrue($supportsMethod->invoke($this->voter, WatchFileVoter::VIEW, $watchFile));
        $this->assertTrue($supportsMethod->invoke($this->voter, WatchFileVoter::EDIT, $watchFile));
    }

    public function testSupportsWithValidAttributesAndHasWatchFileInterface(): void
    {
        $supportsMethod = $this->reflection->getMethod('supports');
        $supportsMethod->setAccessible(true);

        $hasWatchFileInterface = $this->createStub(HasWatchFileInterface::class);

        $this->assertTrue($supportsMethod->invoke($this->voter, WatchFileVoter::VIEW, $hasWatchFileInterface));
        $this->assertTrue($supportsMethod->invoke($this->voter, WatchFileVoter::EDIT, $hasWatchFileInterface));
    }

    public function testSupportsWithInvalidAttribute(): void
    {
        $supportsMethod = $this->reflection->getMethod('supports');
        $supportsMethod->setAccessible(true);

        $watchFile = $this->createStub(WatchFile::class);

        $this->assertFalse($supportsMethod->invoke($this->voter, 'INVALID_ATTRIBUTE', $watchFile));
    }

    public function testSupportsWithInvalidSubject(): void
    {
        $supportsMethod = $this->reflection->getMethod('supports');
        $supportsMethod->setAccessible(true);

        $invalidSubject = new \stdClass();

        $this->assertFalse($supportsMethod->invoke($this->voter, WatchFileVoter::VIEW, $invalidSubject));
        $this->assertFalse($supportsMethod->invoke($this->voter, WatchFileVoter::EDIT, $invalidSubject));
    }

    public function testVoteOnAttributeWithNonUserToken(): void
    {
        $this->token->method('getUser')
            ->willReturn(null);

        $watchFile = $this->createStub(WatchFile::class);
        $vote = new Vote();

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::VIEW], $vote);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteOnAttributeWithNonUserObject(): void
    {
        $nonUser = $this->createStub(UserInterface::class);
        $this->token->method('getUser')
            ->willReturn($nonUser);

        $watchFile = $this->createStub(WatchFile::class);
        $vote = new Vote();

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::VIEW], $vote);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteOnAttributeWithUnsupportedSubject(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $unsupportedSubject = new \stdClass();
        $vote = new Vote();

        $result = $this->voter->vote($this->token, $unsupportedSubject, [WatchFileVoter::VIEW], $vote);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testVoteOnAttributeWithHasWatchFileInterfaceWithoutWatchFile(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $hasWatchFileInterface = $this->createStub(HasWatchFileInterface::class);
        $hasWatchFileInterface->method('getWatchFile')
            ->willReturn(null);

        $vote = new Vote();

        $result = $this->voter->vote($this->token, $hasWatchFileInterface, [WatchFileVoter::VIEW], $vote);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteOnAttributeWithWatchFileUserNotFoundException(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willThrowException(new WatchFileUserNotFoundException('User not found'));

        $vote = new Vote();

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::VIEW], $vote);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteOnAttributeWithViewPermission(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteOnAttributeWithEditPermissionForOwner(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::OWNER);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteOnAttributeWithEditPermissionForEditor(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::EDITOR);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteOnAttributeWithEditPermissionDeniedForViewer(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::VIEWER);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $vote = new Vote();

        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::EDIT], $vote);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteOnAttributeWithHasWatchFileInterface(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $hasWatchFileInterface = $this->createStub(HasWatchFileInterface::class);
        $hasWatchFileInterface->method('getWatchFile')
            ->willReturn($watchFile);

        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::OWNER);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $result = $this->voter->vote($this->token, $hasWatchFileInterface, [WatchFileVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteOnAttributeWithUnknownAttribute(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown attribute: UNKNOWN_ATTRIBUTE');

        // Use reflection to call the protected method directly
        $method = $this->reflection->getMethod('voteOnAttribute');
        $method->setAccessible(true);

        $method->invoke($this->voter, 'UNKNOWN_ATTRIBUTE', $watchFile, $this->token);
    }

    public function testVoteOnAttributeWithoutVoteObject(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::VIEWER);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        // Should not throw any exception when vote is null
        $result = $this->voter->vote($this->token, $watchFile, [WatchFileVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    // Additional tests for voteOnAttribute method directly (like WatchFileActivityVoterTest)
    public function testVoteOnAttributeDirectlyWithViewPermission(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $method = $this->reflection->getMethod('voteOnAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($this->voter, WatchFileVoter::VIEW, $watchFile, $this->token);

        $this->assertTrue($result);
    }

    public function testVoteOnAttributeDirectlyWithEditPermissionForOwner(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::OWNER);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $method = $this->reflection->getMethod('voteOnAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($this->voter, WatchFileVoter::EDIT, $watchFile, $this->token);

        $this->assertTrue($result);
    }

    public function testVoteOnAttributeDirectlyWithEditPermissionDeniedForViewer(): void
    {
        $user = $this->createStub(User::class);
        $this->token->method('getUser')
            ->willReturn($user);

        $watchFile = $this->createStub(WatchFile::class);
        $watchFileUser = $this->createStub(WatchFileUser::class);
        $watchFileUser->method('getRole')
            ->willReturn(WatchFileUserRole::VIEWER);

        $this->watchFileUserGateway->method('getByWatchFileAndUser')
            ->willReturn($watchFileUser);

        $method = $this->reflection->getMethod('voteOnAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($this->voter, WatchFileVoter::EDIT, $watchFile, $this->token);

        $this->assertFalse($result);
    }

    public function testVoteOnAttributeDirectlyWithNonUserToken(): void
    {
        $this->token->method('getUser')
            ->willReturn(null);

        $watchFile = $this->createStub(WatchFile::class);

        $method = $this->reflection->getMethod('voteOnAttribute');
        $method->setAccessible(true);

        $result = $method->invoke($this->voter, WatchFileVoter::VIEW, $watchFile, $this->token);

        $this->assertFalse($result);
    }
}
