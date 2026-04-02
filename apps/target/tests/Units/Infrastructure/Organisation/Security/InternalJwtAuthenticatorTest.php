<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Organisation\Security;

use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;
use App\Infrastructure\Organisation\Security\InternalJwtAuthenticator;
use App\Infrastructure\Organisation\Security\InternalJwtToken;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

#[CoversClass(InternalJwtAuthenticator::class)]
class InternalJwtAuthenticatorTest extends TestCase
{
    private const string JWT_SECRET = 'test-secret-key-for-jwt-minimum-32bytes!';

    /** @var UserGatewayInterface&Stub */
    private UserGatewayInterface $userGateway;
    private InternalJwtAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->userGateway = $this->createStub(UserGatewayInterface::class);
        $this->authenticator = new InternalJwtAuthenticator(
            $this->userGateway,
            self::JWT_SECRET,
            new NullLogger(),
        );
    }

    public function testSupportsInternalAuthorizationHeader(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal some.jwt.token');

        self::assertTrue($this->authenticator->supports($request));
    }

    public function testDoesNotSupportBearerHeader(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer some.jwt.token');

        self::assertFalse($this->authenticator->supports($request));
    }

    public function testDoesNotSupportMissingHeader(): void
    {
        $request = Request::create('/api/test');

        self::assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticateWithValidToken(): void
    {
        $user = new User('user-123', 'test@example.com', [], 'testuser');
        $this->userGateway->method('get')
->willReturn($user);

        $jwt = $this->createValidJwt([
            'sub' => 'user-123',
            'org_id' => 'org-456',
            'org_name' => 'Acme',
        ]);
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $passport = $this->authenticator->authenticate($request);

        self::assertSame($user, $passport->getUser());
    }

    public function testAuthenticateCreatesInternalJwtToken(): void
    {
        $user = new User('user-123', 'test@example.com', [], 'testuser');
        $this->userGateway->method('get')
->willReturn($user);

        $jwt = $this->createValidJwt([
            'sub' => 'user-123',
            'org_id' => 'org-456',
            'org_name' => 'Acme',
        ]);
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $passport = $this->authenticator->authenticate($request);
        $token = $this->authenticator->createToken($passport, 'main');

        self::assertInstanceOf(InternalJwtToken::class, $token);
        self::assertSame('org-456', $token->getClaims()['org_id']);
        self::assertSame('Acme', $token->getClaims()['org_name']);
    }

    public function testAuthenticateRejectsInvalidSignature(): void
    {
        $jwt = JWT::encode(
            [
                'sub' => 'user-123',
                'iss' => 'global-gateway',
                'exp' => time() + 60,
            ],
            'wrong-secret-key-that-does-not-match!!',
            'HS256',
        );
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsWrongIssuer(): void
    {
        $jwt = $this->createValidJwt([
            'sub' => 'user-123',
            'iss' => 'wrong-issuer',
        ]);
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid token issuer.');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsMissingSub(): void
    {
        $jwt = JWT::encode([
            'iss' => 'global-gateway',
            'exp' => time() + 60,
        ], self::JWT_SECRET, 'HS256',);
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Missing subject in token.');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsExpiredToken(): void
    {
        $jwt = JWT::encode(
            [
                'sub' => 'user-123',
                'iss' => 'global-gateway',
                'exp' => time() - 120,
            ],
            self::JWT_SECRET,
            'HS256',
        );
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsUnknownUser(): void
    {
        $this->userGateway->method('get')
            ->willThrowException(new UserNotFoundException('user-unknown'));

        $jwt = $this->createValidJwt([
            'sub' => 'user-unknown',
        ]);
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Internal ' . $jwt);

        $passport = $this->authenticator->authenticate($request);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $passport->getUser();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createValidJwt(array $overrides = []): string
    {
        $payload = array_merge([
            'sub' => 'user-123',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'org_id' => 'org-456',
            'org_name' => 'Test Org',
            'roles' => [],
            'iss' => 'global-gateway',
            'iat' => time(),
            'exp' => time() + 60,
        ], $overrides);

        return JWT::encode($payload, self::JWT_SECRET, 'HS256');
    }
}
