<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Collect\Auth;

use App\Domain\Collect\Auth\AccessToken;
use PHPUnit\Framework\TestCase;

class AccessTokenTest extends TestCase
{
    public function testCreateFromApiResponse(): void
    {
        $data = [
            'access_token' => 'test-token',
            'expires_in' => 3600,
            'refresh_token' => 'refresh-token',
            'refresh_expires_in' => 7200,
            'token_type' => 'Bearer',
            'scope' => 'read write',
        ];

        $token = AccessToken::create($data);

        $this->assertSame('test-token', $token->token);
        $this->assertSame('refresh-token', $token->refreshToken);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame('read write', $token->scope);
        $this->assertGreaterThan(time(), $token->expiresAt);
        $this->assertGreaterThan(time(), $token->refreshExpiresAt);
    }

    public function testCreateWithMinimalData(): void
    {
        $data = [
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ];

        $token = AccessToken::create($data);

        $this->assertSame('test-token', $token->token);
        $this->assertNull($token->refreshToken);
        $this->assertNull($token->refreshExpiresAt);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame('', $token->scope);
    }

    public function testIsExpired(): void
    {
        $expiredToken = new AccessToken(token: 'test-token', expiresAt: time() - 100);

        $validToken = new AccessToken(token: 'test-token', expiresAt: time() + 100);

        $this->assertTrue($expiredToken->isExpired());
        $this->assertFalse($validToken->isExpired());
    }

    public function testIsExpiringSoon(): void
    {
        $soonToExpireToken = new AccessToken(
            token: 'test-token',
            expiresAt: time() + 200 // Less than 5 minutes (300 seconds)
        );

        $validToken = new AccessToken(
            token: 'test-token',
            expiresAt: time() + 600 // More than 5 minutes
        );

        $this->assertTrue($soonToExpireToken->isExpiringSoon());
        $this->assertFalse($validToken->isExpiringSoon());
    }

    public function testIsExpiringSoonWithCustomBuffer(): void
    {
        $token = new AccessToken(token: 'test-token', expiresAt: time() + 50);

        $this->assertTrue($token->isExpiringSoon(100)); // 100 second buffer
        $this->assertFalse($token->isExpiringSoon(30)); // 30 second buffer
    }

    public function testHasValidRefreshToken(): void
    {
        $tokenWithValidRefresh = new AccessToken(
            token: 'test-token',
            expiresAt: time() + 3600,
            refreshToken: 'refresh-token',
            refreshExpiresAt: time() + 7200
        );

        $tokenWithExpiredRefresh = new AccessToken(
            token: 'test-token',
            expiresAt: time() + 3600,
            refreshToken: 'refresh-token',
            refreshExpiresAt: time() - 100
        );

        $tokenWithoutRefresh = new AccessToken(token: 'test-token', expiresAt: time() + 3600);

        $this->assertTrue($tokenWithValidRefresh->hasValidRefreshToken());
        $this->assertFalse($tokenWithExpiredRefresh->hasValidRefreshToken());
        $this->assertFalse($tokenWithoutRefresh->hasValidRefreshToken());
    }

    public function testToAuthenticationHeader(): void
    {
        $token = new AccessToken(token: 'test-access-token', expiresAt: time() + 3600, tokenType: 'Bearer');

        $this->assertSame('Bearer test-access-token', $token->toAuthenticationHeader());
    }

    public function testToAuthenticationHeaderWithCustomTokenType(): void
    {
        $token = new AccessToken(token: 'test-access-token', expiresAt: time() + 3600, tokenType: 'Custom');

        $this->assertSame('Custom test-access-token', $token->toAuthenticationHeader());
    }

    public function testCreateWithMissingAccessToken(): void
    {
        $data = [
            'expires_in' => 3600,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('access_token is required and must be a non-empty string');

        AccessToken::create($data);
    }

    public function testCreateWithNullAccessToken(): void
    {
        $data = [
            'access_token' => null,
            'expires_in' => 3600,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('access_token is required and must be a non-empty string');

        AccessToken::create($data);
    }

    public function testCreateWithEmptyAccessToken(): void
    {
        $data = [
            'access_token' => '',
            'expires_in' => 3600,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('access_token is required and must be a non-empty string');

        AccessToken::create($data);
    }

    public function testCreateWithInvalidAccessTokenType(): void
    {
        $data = [
            'access_token' => 123,
            'expires_in' => 3600,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('access_token is required and must be a non-empty string');

        AccessToken::create($data);
    }

    public function testCreateWithMissingExpiresIn(): void
    {
        $data = [
            'access_token' => 'test-token',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expires_in is required and must be a positive integer');

        AccessToken::create($data);
    }

    public function testCreateWithInvalidExpiresIn(): void
    {
        $data = [
            'access_token' => 'test-token',
            'expires_in' => -100,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expires_in is required and must be a positive integer');

        AccessToken::create($data);
    }

    public function testCreateWithZeroExpiresIn(): void
    {
        $data = [
            'access_token' => 'test-token',
            'expires_in' => 0,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expires_in is required and must be a positive integer');

        AccessToken::create($data);
    }
}
