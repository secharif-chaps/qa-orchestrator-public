<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Organisation;

use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantAwareInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests ensuring all tenant-aware entities are properly scoped to an organisation at creation.
 */
#[CoversClass(WatchFile::class)]
#[CoversClass(Actor::class)]
#[CoversClass(WatchFileActivity::class)]
class TenantAwareEntitiesTest extends TestCase
{
    private Organisation $organisation;

    protected function setUp(): void
    {
        $this->organisation = new Organisation('Acme Corp', 'acme-keycloak-id');
    }

    // ──────────────────────────────────────────────────
    //  WatchFile
    // ──────────────────────────────────────────────────

    public function testWatchFileImplementsTenantAwareInterface(): void
    {
        $watchFile = new WatchFile('Test', 'Objective', $this->organisation);

        $this->assertInstanceOf(TenantAwareInterface::class, $watchFile);
    }

    public function testWatchFileStoresOrganisationAtCreation(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'My objective', $this->organisation);

        $this->assertSame($this->organisation, $watchFile->getOrganisation());
    }

    public function testTwoWatchFilesCanBelongToDifferentOrganisations(): void
    {
        $otherOrg = new Organisation('Other Corp', 'other-keycloak-id');

        $wf1 = new WatchFile('WF Org A', 'Obj A', $this->organisation);
        $wf2 = new WatchFile('WF Org B', 'Obj B', $otherOrg);

        $this->assertNotSame($wf1->getOrganisation(), $wf2->getOrganisation());
        $this->assertSame('Acme Corp', $wf1->getOrganisation()->getName());
        $this->assertSame('Other Corp', $wf2->getOrganisation()->getName());
    }

    // ──────────────────────────────────────────────────
    //  Actor
    // ──────────────────────────────────────────────────

    public function testActorImplementsTenantAwareInterface(): void
    {
        $actor = new Actor('Test Actor', $this->organisation);

        $this->assertInstanceOf(TenantAwareInterface::class, $actor);
    }

    public function testActorStoresOrganisationAtCreation(): void
    {
        $actor = new Actor('ACME Inc.', $this->organisation);

        $this->assertSame($this->organisation, $actor->getOrganisation());
    }

    public function testTwoActorsCanBelongToDifferentOrganisations(): void
    {
        $otherOrg = new Organisation('Other Corp', 'other-keycloak-id');

        $actor1 = new Actor('Actor Org A', $this->organisation);
        $actor2 = new Actor('Actor Org B', $otherOrg);

        $this->assertNotSame($actor1->getOrganisation(), $actor2->getOrganisation());
    }

    // ──────────────────────────────────────────────────
    //  WatchFileActivity
    // ──────────────────────────────────────────────────

    public function testWatchFileActivityStoresOrganisationAtCreation(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', $this->organisation);
        $user = new User('user-id', 'test@example.com');
        $activity = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [
                'watch_file_name' => 'Test',
            ],
            $this->organisation,
        );

        $this->assertSame($this->organisation, $activity->getOrganisation());
    }

    public function testWatchFileActivityStoresImpersonator(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', $this->organisation);
        $user = new User('user-id', 'test@example.com');
        $impersonator = new User('user-id', 'test@example.com');

        $activity = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [
                'watch_file_name' => 'Test',
            ],
            $this->organisation,
            $impersonator,
        );

        $this->assertSame($impersonator, $activity->getImpersonator());
    }

    public function testWatchFileActivityImpersonatorIsNullByDefault(): void
    {
        $watchFile = new WatchFile('Test', 'Obj', $this->organisation);
        $user = new User('user-id', 'test@example.com');

        $activity = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::CREATED,
            [
                'watch_file_name' => 'Test',
            ],
            $this->organisation,
        );

        $this->assertNull($activity->getImpersonator());
    }
}
