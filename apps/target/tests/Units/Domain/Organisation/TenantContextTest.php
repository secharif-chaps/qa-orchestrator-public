<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Organisation;

use App\Domain\Organisation\NoTenantContextException;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    public function testSetAndGetCurrentOrganisation(): void
    {
        $tenantContext = new TenantContext();
        $organisation = new Organisation('Test Org', 'kc-123');
        $user = new User('user-1', 'user@example.com', [], 'testuser');

        $tenantContext->set($organisation, $user);

        self::assertSame($organisation, $tenantContext->getCurrentOrganisation());
    }

    public function testSetAndGetEffectiveUser(): void
    {
        $tenantContext = new TenantContext();
        $organisation = new Organisation('Test Org', 'kc-123');
        $user = new User('user-1', 'user@example.com', [], 'testuser');

        $tenantContext->set($organisation, $user);

        self::assertSame($user, $tenantContext->getEffectiveUser());
    }

    public function testGetCurrentOrganisationThrowsWhenNotSet(): void
    {
        $tenantContext = new TenantContext();

        $this->expectException(NoTenantContextException::class);
        $tenantContext->getCurrentOrganisation();
    }

    public function testGetEffectiveUserThrowsWhenNotSet(): void
    {
        $tenantContext = new TenantContext();

        $this->expectException(NoTenantContextException::class);
        $tenantContext->getEffectiveUser();
    }

    public function testIsImpersonatingReturnsFalseByDefault(): void
    {
        $tenantContext = new TenantContext();
        $organisation = new Organisation('Test Org', 'kc-123');
        $user = new User('user-1', 'user@example.com', [], 'testuser');

        $tenantContext->set($organisation, $user);

        self::assertFalse($tenantContext->isImpersonating());
        self::assertNull($tenantContext->getImpersonator());
    }

    public function testIsImpersonatingReturnsTrueWhenImpersonatorSet(): void
    {
        $tenantContext = new TenantContext();
        $organisation = new Organisation('Test Org', 'kc-123');
        $user = new User('user-1', 'user@example.com', [], 'testuser');
        $impersonator = new User('admin-1', 'admin@example.com', [], 'admin');

        $tenantContext->set($organisation, $user, $impersonator);

        self::assertTrue($tenantContext->isImpersonating());
        self::assertSame($impersonator, $tenantContext->getImpersonator());
    }

    public function testGetAuditUserReturnsEffectiveUserWhenNotImpersonating(): void
    {
        $tenantContext = new TenantContext();
        $organisation = new Organisation('Test Org', 'kc-123');
        $user = new User('user-1', 'user@example.com', [], 'testuser');

        $tenantContext->set($organisation, $user);

        self::assertSame($user, $tenantContext->getAuditUser());
    }

    public function testGetAuditUserReturnsImpersonatorWhenImpersonating(): void
    {
        $tenantContext = new TenantContext();
        $organisation = new Organisation('Test Org', 'kc-123');
        $user = new User('user-1', 'user@example.com', [], 'testuser');
        $impersonator = new User('admin-1', 'admin@example.com', [], 'admin');

        $tenantContext->set($organisation, $user, $impersonator);

        self::assertSame($impersonator, $tenantContext->getAuditUser());
    }
}
