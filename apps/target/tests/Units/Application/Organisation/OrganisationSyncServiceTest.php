<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Organisation;

use App\Application\Organisation\OrganisationSyncService;
use App\Domain\Organisation\MembershipType;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\OrganisationRole;
use App\Domain\Organisation\OrganisationUser;
use App\Domain\User\User;
use App\Tests\Units\Infrastructure\Organisation\NullOrganisationGateway;
use App\Tests\Units\Infrastructure\Organisation\NullOrganisationUserGateway;
use PHPUnit\Framework\TestCase;

class OrganisationSyncServiceTest extends TestCase
{
    private NullOrganisationGateway $organisationGateway;
    private NullOrganisationUserGateway $organisationUserGateway;
    private OrganisationSyncService $syncService;

    protected function setUp(): void
    {
        $this->organisationGateway = new NullOrganisationGateway();
        $this->organisationUserGateway = new NullOrganisationUserGateway();
        $this->syncService = new OrganisationSyncService(
            $this->organisationGateway,
            $this->organisationUserGateway,
        );
    }

    public function testSyncCreatesNewOrganisationWhenNotFound(): void
    {
        $user = new User('user-1', 'user@example.com', [], 'testuser');
        $claims = [
            'org_id' => 'kc-org-1',
            'org_name' => 'ChapsVision',
        ];

        $organisation = $this->syncService->syncFromToken($user, $claims);

        self::assertSame('ChapsVision', $organisation->getName());
        self::assertSame('kc-org-1', $organisation->getKeycloakId());
        self::assertCount(1, $this->organisationGateway->organisations);
    }

    public function testSyncReusesExistingOrganisation(): void
    {
        $existingOrg = new Organisation('ChapsVision', 'kc-org-1');
        $this->organisationGateway->save($existingOrg);

        $user = new User('user-1', 'user@example.com', [], 'testuser');
        $claims = [
            'org_id' => 'kc-org-1',
            'org_name' => 'ChapsVision',
        ];

        $organisation = $this->syncService->syncFromToken($user, $claims);

        self::assertSame($existingOrg, $organisation);
        self::assertCount(1, $this->organisationGateway->organisations);
    }

    public function testSyncCreatesMembershipWithDefaultRole(): void
    {
        $user = new User('user-1', 'user@example.com', [], 'testuser');
        $claims = [
            'org_id' => 'kc-org-1',
            'org_name' => 'ChapsVision',
        ];

        $this->syncService->syncFromToken($user, $claims);

        self::assertCount(1, $this->organisationUserGateway->organisationUsers);
        $membership = array_values($this->organisationUserGateway->organisationUsers)[0];
        self::assertSame(OrganisationRole::MEMBER, $membership->getRole());
        self::assertSame(MembershipType::MANAGED, $membership->getMembershipType());
    }

    public function testSyncUpdatesExistingMembershipSyncedAt(): void
    {
        $existingOrg = new Organisation('ChapsVision', 'kc-org-1');
        $this->organisationGateway->save($existingOrg);

        $user = new User('user-1', 'user@example.com', [], 'testuser');
        $existingMembership = new OrganisationUser(
            $existingOrg,
            $user,
            OrganisationRole::MEMBER,
            MembershipType::MANAGED
        );
        $this->organisationUserGateway->save($existingMembership);
        $originalSyncedAt = $existingMembership->getSyncedAt();

        usleep(1000);

        $claims = [
            'org_id' => 'kc-org-1',
            'org_name' => 'ChapsVision',
        ];

        $this->syncService->syncFromToken($user, $claims);

        self::assertCount(1, $this->organisationUserGateway->organisationUsers);
        $membership = array_values($this->organisationUserGateway->organisationUsers)[0];
        self::assertGreaterThanOrEqual($originalSyncedAt, $membership->getSyncedAt());
    }

    public function testSyncPrunesStaleMemberships(): void
    {
        $org1 = new Organisation('Org 1', 'org-1');
        $this->organisationGateway->save($org1);
        $org2 = new Organisation('Org 2', 'org-2');
        $this->organisationGateway->save($org2);

        $user = new User('user-1', 'user@example.com', [], 'testuser');

        $membership1 = new OrganisationUser($org1, $user, OrganisationRole::MEMBER, MembershipType::MANAGED);
        $this->organisationUserGateway->save($membership1);
        $membership2 = new OrganisationUser($org2, $user, OrganisationRole::MEMBER, MembershipType::MANAGED);
        $this->organisationUserGateway->save($membership2);

        $claims = [
            'org_id' => 'org-1',
            'org_name' => 'Org 1',
        ];

        $this->syncService->syncFromToken($user, $claims);

        self::assertCount(1, $this->organisationUserGateway->organisationUsers);
        $remaining = array_values($this->organisationUserGateway->organisationUsers)[0];
        self::assertSame('org-1', $remaining->getOrganisation()->getKeycloakId());
    }
}
