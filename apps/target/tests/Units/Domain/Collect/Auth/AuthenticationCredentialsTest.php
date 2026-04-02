<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Collect\Auth;

use App\Domain\Collect\Auth\AuthenticationCredentials;
use PHPUnit\Framework\TestCase;

class AuthenticationCredentialsTest extends TestCase
{
    public function testCreateCredentials(): void
    {
        $credentials = new AuthenticationCredentials(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            username: 'test-username',
            password: 'test-password',
            authUrl: 'https://auth.example.com/token'
        );

        $this->assertSame('test-client-id', $credentials->clientId);
        $this->assertSame('test-client-secret', $credentials->clientSecret);
        $this->assertSame('test-username', $credentials->username);
        $this->assertSame('test-password', $credentials->password);
        $this->assertSame('https://auth.example.com/token', $credentials->authUrl);
        $this->assertSame('password', $credentials->grantType);
    }

    public function testCreateCredentialsWithCustomGrantType(): void
    {
        $credentials = new AuthenticationCredentials(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            username: 'test-username',
            password: 'test-password',
            authUrl: 'https://auth.example.com/token',
            grantType: 'client_credentials'
        );

        $this->assertSame('client_credentials', $credentials->grantType);
    }

    public function testEmptyClientIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client ID cannot be empty');

        new AuthenticationCredentials(
            clientId: '',
            clientSecret: 'test-client-secret',
            username: 'test-username',
            password: 'test-password',
            authUrl: 'https://auth.example.com/token'
        );
    }

    public function testEmptyAuthUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Auth URL cannot be empty');

        new AuthenticationCredentials(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            username: 'test-username',
            password: 'test-password',
            authUrl: ''
        );
    }

    public function testGetBasicAuthorizationHeader(): void
    {
        $credentials = new AuthenticationCredentials(
            clientId: 'test-client',
            clientSecret: 'test-secret',
            username: 'test-username',
            password: 'test-password',
            authUrl: 'https://auth.example.com/token'
        );

        $expected = 'Basic ' . base64_encode('test-client:test-secret');
        $this->assertSame($expected, $credentials->getBasicAuthorizationHeader());
    }

    public function testGetAuthPayload(): void
    {
        $credentials = new AuthenticationCredentials(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            username: 'test-user',
            password: 'test-pass',
            authUrl: 'https://auth.example.com/token'
        );

        $payload = $credentials->getAuthPayload();

        $this->assertSame([
            'grant_type' => 'password',
            'username' => 'test-user',
            'password' => 'test-pass',
        ], $payload);
    }

    public function testGetRefreshPayload(): void
    {
        $credentials = new AuthenticationCredentials(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            username: 'test-username',
            password: 'test-password',
            authUrl: 'https://auth.example.com/token'
        );

        $payload = $credentials->getRefreshPayload('test-refresh-token');

        $this->assertSame([
            'grant_type' => 'refresh_token',
            'refresh_token' => 'test-refresh-token',
        ], $payload);
    }
}
