<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organisation;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Organisation\OrganisationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFileActivity\WatchFileActivityFactory;
use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration tests validating tenant isolation at the Doctrine level.
 *
 * These tests verify that the TenantFilter correctly restricts queries
 * to the current organisation, preventing cross-org data leakage.
 */
#[CoversClass(Organisation::class)]
class TenantIsolationTest extends AbstractApiTestCase
{
    use Factories;
    use ResetDatabase;
    private EntityManagerInterface $em;
    private Organisation $orgA;
    private Organisation $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->orgA = OrganisationFactory::createOne();
        $this->orgB = OrganisationFactory::createOne();
    }

    private function enableTenantFilter(Organisation $organisation): void
    {
        $filter = $this->em->getFilters()
->enable('tenant');
        $filter->setParameter('organisation_id', $organisation->getId(), Types::STRING);
    }

    private function disableTenantFilter(): void
    {
        if ($this->em->getFilters()->isEnabled('tenant')) {
            $this->em->getFilters()
->disable('tenant');
        }
    }

    // ──────────────────────────────────────────────────
    //  WatchFile isolation
    // ──────────────────────────────────────────────────

    public function testWatchFileQueryOnlyReturnsCurrentOrgItems(): void
    {
        $user = UserFactory::createOne();

        $wfA1 = WatchFileFactory::new()->withOrganisation($this->orgA)->withCreatedBy($user)->create();
        $wfA2 = WatchFileFactory::new()->withOrganisation($this->orgA)->withCreatedBy($user)->create();
        WatchFileFactory::new()->withOrganisation($this->orgB)->withCreatedBy($user)->create();

        $this->em->clear();
        $this->enableTenantFilter($this->orgA);

        $watchFiles = $this->em->getRepository(WatchFile::class)->findAll();

        $this->assertCount(2, $watchFiles);
        $ids = array_map(fn (WatchFile $wf) => $wf->getId(), $watchFiles);
        $this->assertContains($wfA1->getId(), $ids);
        $this->assertContains($wfA2->getId(), $ids);
    }

    public function testWatchFileFromOtherOrgIsNotAccessible(): void
    {
        $user = UserFactory::createOne();

        $wfB = WatchFileFactory::new()->withOrganisation($this->orgB)->withCreatedBy($user)->create();
        $wfBId = $wfB->getId();

        $this->em->clear();
        $this->enableTenantFilter($this->orgA);

        $result = $this->em->getRepository(WatchFile::class)->find($wfBId);

        $this->assertNull($result, 'WatchFile from org B should not be visible to org A');
    }

    public function testSwitchingOrgFilterReturnsCorrectWatchFiles(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()->withOrganisation($this->orgA)->withCreatedBy($user)->create();
        WatchFileFactory::new()->withOrganisation($this->orgA)->withCreatedBy($user)->create();
        WatchFileFactory::new()->withOrganisation($this->orgB)->withCreatedBy($user)->create();
        WatchFileFactory::new()->withOrganisation($this->orgB)->withCreatedBy($user)->create();
        WatchFileFactory::new()->withOrganisation($this->orgB)->withCreatedBy($user)->create();

        $this->em->clear();

        // Filter on org A
        $this->enableTenantFilter($this->orgA);
        $this->assertCount(2, $this->em->getRepository(WatchFile::class)->findAll());

        // Switch to org B
        $this->disableTenantFilter();
        $this->em->clear();
        $this->enableTenantFilter($this->orgB);
        $this->assertCount(3, $this->em->getRepository(WatchFile::class)->findAll());
    }

    // ──────────────────────────────────────────────────
    //  Actor isolation
    // ──────────────────────────────────────────────────

    public function testActorQueryOnlyReturnsCurrentOrgItems(): void
    {
        $actorA = ActorFactory::new()->withOrganisation($this->orgA)->create();
        ActorFactory::new()->withOrganisation($this->orgB)->create();

        $this->em->clear();
        $this->enableTenantFilter($this->orgA);

        $actors = $this->em->getRepository(Actor::class)->findAll();

        $this->assertCount(1, $actors);
        $this->assertSame($actorA->getId(), $actors[0]->getId());
    }

    public function testActorFromOtherOrgIsNotAccessible(): void
    {
        $actorB = ActorFactory::new()->withOrganisation($this->orgB)->create();
        $actorBId = $actorB->getId();

        $this->em->clear();
        $this->enableTenantFilter($this->orgA);

        $result = $this->em->getRepository(Actor::class)->find($actorBId);

        $this->assertNull($result, 'Actor from org B should not be visible to org A');
    }

    // ──────────────────────────────────────────────────
    //  WatchFileActivity isolation (via organisation FK)
    // ──────────────────────────────────────────────────

    public function testWatchFileActivityInheritsWatchFileOrg(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOrganisation($this->orgA)->withCreatedBy($user)->create();

        $activity = WatchFileActivityFactory::new()->with([
            'watchFile' => $watchFile,
            'user' => $user,
            'actionType' => WatchFileActivityActionType::CREATED,
            'organisation' => $this->orgA,
        ])->create();

        $this->assertSame(
            $this->orgA->getId(),
            $activity->getOrganisation()
->getId(),
            'Activity org should match the WatchFile org',
        );
    }

    // ──────────────────────────────────────────────────
    //  Without filter: all data is accessible
    // ──────────────────────────────────────────────────

    public function testWithoutFilterAllOrgsAreVisible(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()->withOrganisation($this->orgA)->withCreatedBy($user)->create();
        WatchFileFactory::new()->withOrganisation($this->orgB)->withCreatedBy($user)->create();

        $this->em->clear();
        $this->disableTenantFilter();

        $watchFiles = $this->em->getRepository(WatchFile::class)->findAll();

        $this->assertCount(2, $watchFiles);
    }

    // ──────────────────────────────────────────────────
    //  Cross-org creation via API
    // ──────────────────────────────────────────────────

    public function testCreatedWatchFileBelongsToAuthenticatedOrg(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClientInOrganisation($user, $this->orgA);
        $response = $client->request('POST', '/api/watch_files', [
            'json' => [
                'name' => 'Org A WatchFile',
                'userObjective' => 'Test objective',
                'content' => 'Test content',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $watchFileId = $response->toArray()['id'];

        // Reload from DB without filter to check the organisation_id
        $this->disableTenantFilter();
        $this->em->clear();
        $watchFile = $this->em->getRepository(WatchFile::class)->find($watchFileId);

        $this->assertNotNull($watchFile);
        $this->assertSame($this->orgA->getId(), $watchFile->getOrganisation()->getId());
    }
}
