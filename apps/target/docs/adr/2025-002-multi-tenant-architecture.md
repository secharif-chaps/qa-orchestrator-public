# ADR-2025-002: Multi-Tenant Architecture for ChapsMind Integration

| Status | Date       | Author                    |
| ------ | ---------- | ------------------------- |
| Draft  | 2025-12-29 | Frédéric Fayard-Le Barzic |
| Draft  | 2026-02-09 | Jacques JOYEUX            |
| Draft  | 2026-03-26 | Aurélien LAUF             |

---

## 1. Context & Requirements

### 1.1 Current State

Target currently operates as a **single-tenant application** where:

- Data isolation is **per-user** via `WatchFileUser` relationships
- No organizational hierarchy exists
- All users share the same data space (Actors, etc.)
- Single Elasticsearch index for all documents
- RabbitMQ queues are not tenant-aware
- N8N workflows process messages without tenant context

### 1.2 Target State (ChapsMind)

> **Glossary — JWT (JSON Web Token):** A compact, signed token used to transmit identity and claims between services. It is self-contained: the recipient can verify its authenticity without querying the issuer, as long as it knows the signing secret or public key. In this architecture, two JWTs are involved: the **Keycloak JWT** (issued to the browser) and the **Internal JWT** (issued by the Global Service to Target).

ChapsMind requires **organizational multi-tenancy** where:

- Each **Organisation** represents a tenant with complete data segregation
- Users belong to **one or more organisations** via Keycloak Organizations feature
- The Global Service validates the Keycloak JWT and forwards organisation context via an **Internal JWT** (HMAC-SHA256)
- Support for **user impersonation** via Keycloak Token Exchange — the `impersonator` claim is extracted by the Global Service and relayed in the Internal JWT
- Compliance with data sovereignty requirements

### 1.3 Constraints

| Constraint          | Description                                                           |
| ------------------- | --------------------------------------------------------------------- |
| **Greenfield**      | No production data - early adopter phase, no migration needed         |
| **OVH PostgreSQL**  | Managed database by OVH (RLS compatibility to be validated)           |
| **OVH OpenSearch**  | Planned migration from Elasticsearch to managed OpenSearch            |
| **Keycloak 26.4.6** | Native Organizations feature fully supported                          |
| **Multi-org users** | A user can belong to multiple organisations (native Keycloak feature) |

### 1.4 Key Requirements

| Requirement          | Priority | Description                                          |
| -------------------- | -------- | ---------------------------------------------------- |
| Data Segregation     | Critical | Complete isolation between organisations             |
| Keycloak Integration | Critical | Use native Keycloak Organizations feature            |
| Multi-Org Users      | Critical | User can belong to N organisations (Keycloak native) |
| Impersonation        | High     | Via Keycloak Token Exchange feature                  |
| Async Context        | High     | Tenant context must flow through Messenger/N8N       |
| Search Isolation     | High     | OpenSearch filtered by tenant routing                |
| Audit Trail          | High     | All actions logged with user + tenant + impersonator |

---

## 2. Keycloak Organizations Feature

### 2.1 Native Support in Keycloak 26.4.6

Keycloak 26 provides **fully supported** Organizations feature for multi-tenancy:

- **Multi-organization membership**: Users can join multiple organizations
- **Organization-specific IdPs**: Automatic federation per organization
- **Token claims**: Organization membership included in JWT tokens
- **Active organization selection**: Authentication flow selects active org

Reference: [Keycloak Organizations Announcement](https://www.keycloak.org/2024/06/announcement-keycloak-organizations)

### 2.2 Membership Types

| Type          | Description                                                   |
| ------------- | ------------------------------------------------------------- |
| **Managed**   | User identity is owned by this organization (max 1 per user)  |
| **Unmanaged** | User was added to organization but identity managed elsewhere |

### 2.3 Authentication Flow

The diagram below shows the full journey from user login to Target receiving an Internal JWT. The key insight: **Target never talks to Keycloak directly** — the Global Service acts as a gateway, validates the Keycloak JWT, and issues a simpler Internal JWT that Target trusts.

```
┌─────────────────────────────────────────────────────────────────┐
│  User Login                                                     │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Keycloak Authentication                                        │
│  - Validate credentials                                         │
│  - Check organization memberships                               │
└──────────────────────────────┬──────────────────────────────────┘
                               │
              ┌────────────────┴────────────────┐
              │                                 │
              ▼                                 ▼
┌─────────────────────────┐       ┌─────────────────────────┐
│  Single Org Member      │       │  Multi-Org Member       │
│  → Direct token issue   │       │  → Org selection screen │
└─────────────────────────┘       └────────────┬────────────┘
                                               │
                                               ▼
                                  ┌─────────────────────────┐
                                  │  User selects org       │
                                  │  → Keycloak JWT issued  │
                                  │    with org claim       │
                                  └────────────┬────────────┘
                                               │
                               ┌───────────────┘
                               │  Frontend sends Keycloak JWT
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Global Service (API Gateway)                                   │
│  - Validates Keycloak JWT                                       │
│  - Checks OrganizationModule (Target enabled?)                  │
│  - Issues Internal JWT (HMAC-SHA256) with user + org context    │
└──────────────────────────────┬──────────────────────────────────┘
                               │  Internal JWT
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Target API (Symfony)                                           │
│  - InternalJwtAuthenticator verifies HMAC-SHA256 signature      │
│  - No network call to Keycloak                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.4 JWT Token Structures

Two tokens circulate in the system. The key fields to understand:

- **`sub`**: the unique identifier of the user (UUID from Keycloak)
- **`organization`**: the organisation the user is currently acting in (the active tenant)
- **`org_memberships`**: all organisations the user belongs to, with their role in each
- **`impersonator`**: present only during impersonation — identifies the admin who initiated the session
- **`exp`**: token expiry timestamp (Unix epoch)

Two tokens are involved. Target never sees the Keycloak JWT directly.

**Keycloak JWT** (issued to frontend, validated by Global Service only):

```json
{
  "sub": "user-uuid",
  "email": "user@example.com",
  "organization": {
    "id": "org-uuid",
    "name": "Acme Corp",
    "attributes": {}
  },
  "org_memberships": [
    {
      "id": "org-1-uuid",
      "name": "Acme Corp",
      "roles": ["admin"]
    },
    {
      "id": "org-2-uuid",
      "name": "Beta Inc",
      "roles": ["member"]
    }
  ],
  "impersonator": {
    "id": "admin-user-uuid",
    "username": "admin@example.com"
  }
}
```

**Internal JWT** (issued by Global Service, received by Target — signed HMAC-SHA256):

```json
{
  "sub": "user-uuid",
  "username": "user@example.com",
  "organization": {
    "id": "org-uuid",
    "name": "Acme Corp"
  },
  "org_memberships": [
    {
      "id": "org-1-uuid",
      "name": "Acme Corp",
      "roles": ["admin"]
    },
    {
      "id": "org-2-uuid",
      "name": "Beta Inc",
      "roles": ["member"]
    }
  ],
  "impersonator": {
    "id": "admin-user-uuid",
    "username": "admin@example.com"
  },
  "exp": 1741824000
}
```

### 2.5 Organization Switching

When a multi-org user wants to switch organization:

1. Frontend initiates re-authentication with Keycloak
2. Keycloak presents organization selection
3. New token issued with updated `organization` claim
4. All subsequent API calls use new tenant context

**Note:** This is fully stateless - no server-side session required.

---

## 3. Target Data Model

Multi-tenancy requires adding an `Organisation` entity as the root of all tenant-scoped data. Every piece of data that must be isolated per tenant — WatchFiles, Actors, Conversations — gets an `organisation_id` foreign key. Two new entities are introduced (`Organisation`, `OrganisationUser`) and several existing ones are modified.

### 3.1 Entity Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         Organisation                            │
├─────────────────────────────────────────────────────────────────┤
│ id: UUID (PK)                                                   │
│ name: string                                                    │
│ keycloakId: string (UNIQUE) - Organisation ID in Keycloak       │
│ createdAt: datetime                                             │
└──────────────────────────────┬──────────────────────────────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   WatchFile     │  │     Actor       │  │ OrganisationUser│
├─────────────────┤  ├─────────────────┤  ├─────────────────┤
│ organisation_id │  │ organisation_id │  │ organisation_id │
│ createdBy       │  │ (per-tenant)    │  │ user_id         │
│ ...             │  │ ...             │  │ role (from JWT) │
└────────┬────────┘  └─────────────────┘  │ membershipType  │
         │                                └────────┬────────┘
         │ 1:N                                     │
         ▼                                         │
┌─────────────────┐                                │
│    Document     │                                │
│   Conversation  │◀───────────────────────────────┘
│     Source      │         N:M (synced from Internal JWT)
│      ...        │
└─────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                            User                                 │
├─────────────────────────────────────────────────────────────────┤
│ id: UUID (from Keycloak)                                        │
│ email: string                                                   │
│ roles: array                                                    │
│ organisationUsers: Collection<OrganisationUser> (1:N)           │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Organisation Entity

The `Organisation` entity represents a tenant. It is identified by its Keycloak organisation ID (`keycloakId`), which is the stable external reference. Target does not manage organisations itself — it only mirrors what Keycloak knows.

```php
#[ORM\Entity]
#[ORM\Table(name: 'organisation')]
class Organisation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $keycloakId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: OrganisationUser::class, mappedBy: 'organisation')]
    private Collection $organisationUsers;

    #[ORM\OneToMany(targetEntity: WatchFile::class, mappedBy: 'organisation')]
    private Collection $watchFiles;
}
```

### 3.3 OrganisationUser Entity (Synced from Internal JWT)

> **⚠️ Decision pending — Primary Key strategy:** Two options exist for `OrganisationUser`,
> requiring team discussion before implementation (see options below).
>
> **Current decision: Option A (dedicated UUID)** — consistent with `WatchFileUser` already in
> the project, zero friction with Foundry factories, API Platform, and integration tests.
> Business uniqueness on `(organisation, user)` is enforced separately via `UniqueConstraint`.
>
> **Option B (composite PK)** was considered but rejected: while semantically cleaner, Doctrine
> composite PKs require array-based lookups (`find(['organisation' => ..., 'user' => ...])`),
> complicate API Platform URI design, and add friction to Foundry factory setup. To be discussed
> if the team prefers the semantic clarity of a composite PK.

**Option A — Dedicated UUID (current decision):**

```php
#[ORM\Entity]
#[ORM\Table(name: 'organisation_user')]
#[ORM\UniqueConstraint(name: 'organisation_user_unique', columns: ['organisation_id', 'user_id'])]
class OrganisationUser
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Organisation::class, inversedBy: 'organisationUsers')]
    #[ORM\JoinColumn(nullable: false)]
    private Organisation $organisation;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'organisationUsers')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', enumType: OrganisationRole::class)]
    private OrganisationRole $role;

    #[ORM\Column(type: 'string', enumType: MembershipType::class)]
    private MembershipType $membershipType;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $syncedAt;
}
```

**Option B — Composite PK (rejected):**

```php
#[ORM\Entity]
#[ORM\Table(name: 'organisation_user')]
class OrganisationUser
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Organisation::class, inversedBy: 'organisationUsers')]
    #[ORM\JoinColumn(nullable: false)]
    private Organisation $organisation;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'organisationUsers')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    // ... same remaining fields
}
```

```php
enum OrganisationRole: string
{
    case ADMIN = 'admin';      // Can impersonate, manage members
    case MEMBER = 'member';    // Full access to WatchFiles
    case VIEWER = 'viewer';    // Read-only access
}

enum MembershipType: string
{
    case MANAGED = 'managed';      // Identity owned by this org
    case UNMANAGED = 'unmanaged';  // Identity managed elsewhere
}
```

---

## 4. Tenant Context Extraction

On every API request, Target needs to know: "which organisation is this request acting on behalf of, and who is the user?" This section describes how that information is extracted from the Internal JWT, stored in a request-scoped service (`TenantContext`), and made available throughout the request lifecycle.

### 4.1 Stateless Context from JWT

The **Internal JWT** is the single source of truth for tenant context. No headers required.

> **⚠️ Design note — Single Responsibility:** `TenantContext` is a pure request-scoped value
> object with **no dependencies on gateways or the database**. It only stores and exposes the
> current tenant state. All find-or-create logic for organisations is the exclusive responsibility
> of `OrganisationSyncService` (see section 9), which is called by the listener before setting the
> context. This avoids duplicating organisation creation logic across two classes and makes
> `TenantContext` trivially easy to units tests.

```php
final class TenantContext
{
    private ?Organisation $currentOrganisation = null;
    private ?User $effectiveUser = null;
    private ?User $impersonator = null;

    public function set(Organisation $organisation, User $user, ?User $impersonator = null): void
    {
        $this->currentOrganisation = $organisation;
        $this->effectiveUser = $user;
        $this->impersonator = $impersonator;
    }

    public function getCurrentOrganisation(): Organisation
    {
        return $this->currentOrganisation
            ?? throw new \LogicException('No tenant context set');
    }

    public function getEffectiveUser(): User
    {
        return $this->effectiveUser
            ?? throw new \LogicException('No tenant context set');
    }

    public function isImpersonating(): bool
    {
        return $this->impersonator !== null;
    }

    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function getAuditUser(): User
    {
        return $this->impersonator ?? $this->effectiveUser;
    }
}
```

### 4.2 Controller Listener

`TenantContextListener` is the Symfony event subscriber responsible for populating `TenantContext` at the start of each request. It runs after authentication is complete, reads the JWT claims, triggers organisation sync, and enables the Doctrine tenant filter — so that all subsequent database queries are automatically scoped to the current organisation.

> **⚠️ Decision note:** The listener subscribes to `kernel.controller` rather than `kernel.request`.
>
> Symfony's `FirewallListener` runs on `kernel.request` at **priority 8**. Using the same event
> at priority 8 creates a race condition: the tenant listener may execute before the JWT has been
> validated and the `TokenStorage` populated, causing `getToken()` to return `null`. In that case
> the Doctrine tenant filter is never enabled and **all organisations' data would be exposed**.
>
> `kernel.controller` is dispatched after the full security chain is complete, guaranteeing the
> token is present for any protected route. Public routes (`/api/docs`, `/api/healthcheck`, etc.)
> simply return early because no `InternalJwtToken` is present — no tenant context is needed there.

```php
class TenantContextListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onKernelController', 10]];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof InternalJwtToken) {
            return; // Public route — no tenant context required
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $claims = $token->getAttributes();

        // OrganisationSyncService is the single owner of find-or-create logic
        $organisation = $this->organisationSyncService->syncFromToken($user, $claims);

        // Handle impersonation claim (relayed from Keycloak Token Exchange via Global Service)
        $impersonator = null;
        if (isset($claims['impersonator'])) {
            $impersonator = $this->userGateway->get($claims['impersonator']['id']);
        }

        // TenantContext only stores the resolved state — no DB access of its own
        $this->tenantContext->set($organisation, $user, $impersonator);

        // Enable Doctrine tenant filter
        $filter = $this->em->getFilters()->enable('tenant');
        $filter->setParameter(
            'organisation_id',
            $organisation->getId(),
            Types::STRING
        );
    }
}
```

### 4.3 Testing the Tenant Filter Activation

Two levels of tests are needed: a unit test verifying the listener logic, and an integration test
verifying that a cross-tenant data leak is impossible.

**Unit test — listener activates the filter after authentication:**

```php
#[CoversClass(TenantContextListener::class)]
class TenantContextListenerTest extends TestCase
{
    public function testEnablesTenantFilterWhenAuthenticated(): void
    {
        $organisation = OrganisationFactory::new()->withoutPersisting()->create();
        $user = UserFactory::new()->withoutPersisting()->create();

        $token = $this->createStub(InternalJwtToken::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getAttributes')->willReturn([
            'organization' => ['id' => $organisation->getKeycloakId(), 'name' => 'Acme'],
        ]);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $tenantContext = $this->createMockWithExpectations(TenantContext::class);
        $tenantContext->expects($this->once())
            ->method('set');

        $filter = $this->createMockWithExpectations(TenantFilter::class);
        $filter->expects($this->once())
            ->method('setParameter')
            ->with('organisation_id', $organisation->getId(), Types::STRING);

        // ... assert filter is enabled
    }

    public function testDoesNothingOnPublicRoute(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null); // No token = public route

        $tenantContext = $this->createMockWithExpectations(TenantContext::class);
        $tenantContext->expects($this->never())->method('set');

        // ... assert filter is NOT enabled
    }
}
```

**Integration test — cross-tenant data leak:**

```php
class TenantIsolationTest extends AbstractApiTestCase
{
    use ResetDatabase;
    use Factories;

    public function testUserCannotSeeAnotherOrganisationWatchFiles(): void
    {
        $orgA = OrganisationFactory::createOne();
        $orgB = OrganisationFactory::createOne();

        $userA = UserFactory::createOne();
        OrganisationUserFactory::createOne(['organisation' => $orgA, 'user' => $userA]);

        // WatchFile belonging to org B
        WatchFileFactory::createOne(['organisation' => $orgB]);

        $this->actingAs($userA, organisationId: $orgA->getId());
        $response = $this->get('/api/watch_files');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['hydra:totalItems' => 0]); // org B data not visible
    }

    public function testUserSeesOnlyTheirOrganisationWatchFiles(): void
    {
        $orgA = OrganisationFactory::createOne();
        $orgB = OrganisationFactory::createOne();

        $userA = UserFactory::createOne();
        OrganisationUserFactory::createOne(['organisation' => $orgA, 'user' => $userA]);

        WatchFileFactory::createMany(3, ['organisation' => $orgA]);
        WatchFileFactory::createMany(2, ['organisation' => $orgB]); // Must not appear

        $this->actingAs($userA, organisationId: $orgA->getId());
        $response = $this->get('/api/watch_files');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['hydra:totalItems' => 3]);
    }
}
```

> **Note:** `actingAs()` will need to be extended to accept an `organisationId` parameter so the
> `TestAuthenticator` can inject the correct organisation claim into the test token.

---

## 5. Data Segregation Strategy

The core safety guarantee of multi-tenancy is that organisation A can never read organisation B's data, even accidentally. This section describes how that guarantee is enforced at the database level.

### 5.1 Application-Level Filtering (Primary)

`TenantFilter` is a Doctrine SQL filter: a mechanism that automatically appends a `WHERE organisation_id = ?` clause to every query involving a tenant-aware entity. It is enabled by `TenantContextListener` on every authenticated request, transparent to business code — handlers and repositories do not need to add any manual filter themselves.

```php
class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $meta, string $alias): string
    {
        if (!$meta->reflClass->implementsInterface(TenantAwareInterface::class)) {
            return '';
        }

        return sprintf(
            '%s.organisation_id = %s',
            $alias,
            $this->getParameter('organisation_id')
        );
    }
}
```

**Advantages:**

- ✅ Full control in application code
- ✅ Easy to debug and test
- ✅ Works with existing Doctrine infrastructure
- ✅ No dependency on specific DB features
- ✅ Familiar pattern (already used for `UserAccessibleWatchFileFilter`)

### 5.2 PostgreSQL Row-Level Security (Optional Safety Net)

RLS (Row-Level Security) is a PostgreSQL feature that enforces data access rules directly at the database engine level — independently of the application code. It would act as a second line of defence: even if a bug disabled the Doctrine filter, the database itself would still block cross-tenant reads. This is optional and depends on OVH's managed PostgreSQL support.

RLS can be added as a secondary defense layer if compatible with OVH managed PostgreSQL.

**Action Required:** Validate with OVH support before implementing:

1. Does OVH PostgreSQL support custom session variables?
2. How does connection pooling handle session variables?
3. Is there transaction-level context isolation?

**Recommendation:** Start with application-level filtering. Evaluate RLS in Phase 2.

---

## 6. OpenSearch Multi-Tenancy

Full-text search (documents) also needs to be isolated per tenant. Rather than creating one index per organisation (which would be expensive), the strategy uses a single shared index with **routing keys**: each document is physically stored on a shard determined by its organisation ID. Search queries include the same routing key, so OpenSearch only reads the relevant shard — no cross-tenant data is ever scanned.

### 6.1 Routing Strategy

```php
class ElasticsearchDocumentGateway implements DocumentGatewayInterface
{
    public function index(Document $document): void
    {
        $this->client->index([
            'index' => $this->indexName,
            'id' => $document->getId(),
            'routing' => $document->getWatchFile()->getOrganisation()->getId(),
            'body' => $this->serializer->normalize($document),
        ]);
    }

    public function search(SearchCriteria $criteria, TenantContext $tenant): SearchResult
    {
        return $this->client->search([
            'index' => $this->indexName,
            'routing' => $tenant->getCurrentOrganisation()->getId(),
            'body' => $this->buildQuery($criteria),
        ]);
    }
}
```

### 6.2 Index Configuration

```json
{
  "settings": {
    "index": {
      "number_of_shards": 5,
      "number_of_replicas": 1,
      "routing": {
        "required": true
      }
    }
  }
}
```

### 6.3 OVH OpenSearch Constraints

> **⚠️ Unverified** — The limits below (16 shards, 25 GB/shard) originate from the OVH **Logs Data Platform** (Index as a Service) offering, not from the OVH **Public Cloud Databases OpenSearch** offering we plan to use. A support request has been submitted (via Clément Dognon, 2026-03-24) to confirm the actual limits of our target offering. **Do not use these figures for production sizing until validated.**

Based on [OVH Logs Data Platform documentation](https://support.us.ovhcloud.com/hc/en-us/articles/21913096243219-OpenSearch-Capabilities-and-limitations) — **NOT confirmed for Public Cloud Databases OpenSearch**:

- Maximum **16 shards per index** (unverified for our offering)
- Maximum **25 GB per shard** → 400 GB max capacity (unverified for our offering)
- Shard count **cannot be changed after index creation** — this is a hard architectural constraint (OpenSearch-wide, not OVH-specific)

**Pending validation with OVH support (Public Cloud Databases OpenSearch):**

1. Is `index.routing.required: true` supported? Is `index.routing_partition_size` supported?
2. What are the actual limits: max shards per index, max shard size, max shards on the cluster, and do replicas count toward these limits?
3. Are index aliases (including write aliases), ISM policies, and index templates supported?
4. Is there a limit on the total number of indices per cluster?

### 6.4 Performance Risks

Custom routing physically co-locates all documents from the same organisation on the same shard. This is efficient for search isolation but introduces risks at scale:

**Hot shards** — If one organisation has significantly more documents than others, its shard becomes a bottleneck (CPU, I/O, memory). With only 5 shards and a max of 16, rebalancing options are limited.

**Shard size ceiling** — OVH Logs Data Platform caps at 25 GB/shard and 16 shards max = 400 GB total index capacity (⚠️ unverified for Public Cloud Databases, see section 6.3). General OpenSearch best practice targets 10–30 GB per shard for search workloads. Shard count must be planned upfront and cannot be adjusted later without reindexing.

**No automatic rebalancing** — Unlike PostgreSQL partitioning, OpenSearch does not redistribute documents between shards after creation. A wrong initial shard count requires a full reindex.

**Mitigation strategies to consider:**

- For large organisations (e.g. > 10 GB of documents): use a **dedicated index** instead of the shared one, with the same routing-free configuration.
- Use [`index.routing_partition_size`](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-routing-field.html) to spread one organisation across multiple shards — reduces hot shard risk at the cost of partial scatter/gather.
- Monitor shard size per organisation in Kibana and trigger index migration before hitting the ceiling.

**Action Required:** Validate with OVH support:

1. Is `routing.required: true` index setting supported?
2. Are there limitations on routing key cardinality?
3. What is the actual shard/index limit on our plan?

---

## 7. Impersonation via Keycloak Token Exchange

Impersonation allows an admin user to act on behalf of another user within the same organisation — for example, to diagnose an issue or provide support. The admin does not need to know the user's password: Keycloak's **Token Exchange** feature issues a new JWT where the `sub` is the target user but an `impersonator` claim records who initiated the session. All actions performed during impersonation are logged with both identities in the audit trail.

### 7.1 Keycloak Configuration

**Required features in Keycloak 26.4.6:**

```bash
KC_FEATURES=token-exchange,admin-fine-grained-authz
```

**Action Required:** Validate this with the keycloak team:

1. Can we enable these features? Maybe on the next upgrade?
2. What is the limitations?

### 7.2 Token Exchange Flow

```
┌─────────────────────────────────────────────────────────────────┐
│  Admin User (wants to impersonate)                              │
│  Has: OrganisationRole::ADMIN in target organization            │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  POST /realms/{realm}/protocol/openid-connect/token             │
│  grant_type=urn:ietf:params:oauth:grant-type:token-exchange     │
│  subject_token=<admin-token>                                    │
│  requested_subject=<target-user-id>                             │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Keycloak JWT with Impersonation Claims                         │
│  - sub: target-user-uuid                                        │
│  - organization: {id, name}                                     │
│  - impersonator: {id, username}                                 │
│  - exp: +20 minutes (configurable)                              │
└──────────────────────────────┬──────────────────────────────────┘
                               │  Frontend sends impersonation JWT
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Global Service                                                 │
│  - Validates Keycloak JWT                                       │
│  - Extracts impersonator claim                                  │
│  - Issues Internal JWT with impersonator context                │
└──────────────────────────────┬──────────────────────────────────┘
                               │  Internal JWT (includes impersonator)
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  Target API (Symfony)                                           │
│  - TenantContextListener extracts impersonator from claims      │
│  - Audit trail records both effective user and impersonator     │
└─────────────────────────────────────────────────────────────────┘
```

### 7.3 Keycloak Mapper Configuration

Add predefined mappers for impersonator info:

```
Clients → App → Client Scopes → Dedicated → Add Mapper → From Predefined
  → "Impersonator Username"
  → "Impersonator User Id"
```

### 7.4 Security Rules

| Rule                    | Description                                      |
| ----------------------- | ------------------------------------------------ |
| Same-Org Only           | Can only impersonate within shared organizations |
| Role Required           | Requires `OrganisationRole::ADMIN` in target org |
| Audit Required          | All actions logged with impersonator info        |
| No Privilege Escalation | Cannot impersonate user with higher privileges   |
| Timeout                 | **20 minutes** (configurable in Keycloak)        |

### 7.5 Audit Trail

```php
class WatchFileActivity
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;  // Effective user

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $impersonator = null;  // Real user if impersonating

    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Organisation $organisation;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

---

## 8. Async Context Propagation

When an API request dispatches an asynchronous message (via Symfony Messenger — e.g. to trigger an AI workflow), the tenant context is not automatically available in the worker process: there is no HTTP request, no JWT, no `TenantContextListener` running. The organisation and user information must therefore be explicitly serialised into the message envelope and restored on the consumer side before the handler executes.

This is solved with two small classes:

- **`TenantStamp`**: a lightweight envelope attachment carrying only the IDs (organisation, user, optional impersonator)
- **`TenantMiddleware`**: intercepts every message send/receive — attaches the stamp on dispatch, resolves entities and restores `TenantContext` on consumption

### 8.1 TenantStamp for Messenger

`TenantStamp` is intentionally minimal: it carries only string IDs, not full Doctrine entities. Entities cannot be safely serialised across process boundaries.

```php
final readonly class TenantStamp implements StampInterface
{
    public function __construct(
        public string $organisationId,
        public string $effectiveUserId,
        public ?string $impersonatorId = null,
    ) {}
}
```

### 8.2 TenantMiddleware

```php
class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // SEND: Attach current tenant context
        if (!$envelope->last(TenantStamp::class)) {
            try {
                $envelope = $envelope->with(new TenantStamp(
                    organisationId: $this->tenantContext->getCurrentOrganisation()->getId(),
                    effectiveUserId: $this->tenantContext->getEffectiveUser()->getId(),
                    impersonatorId: $this->tenantContext->isImpersonating()
                        ? $this->tenantContext->getImpersonator()->getId()
                        : null,
                ));
            } catch (\LogicException) {
                // No tenant context (CLI command)
            }
        }

        // RECEIVE: Restore context from stamp
        $stamp = $envelope->last(TenantStamp::class);
        if ($stamp instanceof TenantStamp) {
            // TenantStamp only carries IDs — resolve entities here since TenantMiddleware
            // already holds an EntityManager, and TenantContext must stay dependency-free.
            $organisation = $this->organisationRepository->find($stamp->organisationId)
                ?? throw new \RuntimeException('Organisation not found: ' . $stamp->organisationId);
            $effectiveUser = $this->userRepository->find($stamp->effectiveUserId)
                ?? throw new \RuntimeException('User not found: ' . $stamp->effectiveUserId);
            $impersonator = $stamp->impersonatorId
                ? $this->userRepository->find($stamp->impersonatorId)
                : null;

            $this->tenantContext->set($organisation, $effectiveUser, $impersonator);

            // Enable Doctrine filter
            $filter = $this->em->getFilters()->enable('tenant');
            $filter->setParameter('organisation_id', $stamp->organisationId);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
```

### 8.3 N8N Context Forwarding

N8N workflows are triggered by messages published on RabbitMQ. Since N8N runs outside the Symfony process, the tenant context must be forwarded as explicit fields in the message payload — N8N workflows must then relay `organisationId` and `impersonatorId` in their response messages so the handler can restore context when processing the reply.

Update `TriggerAgent` message:

```php
class TriggerAgent
{
    public function __construct(
        public string $name,
        public array $data,
        public string $responseType,
        public ?string $watchFileId = null,
        public ?string $userId = null,
        public ?string $organisationId = null,
        public ?string $impersonatorId = null,
        public \DateTime $triggeredAt = new \DateTime(),
    ) {}
}
```

N8N workflows must forward `organisationId` and `impersonatorId` in response messages.

---

## 9. Organisation Sync from Internal JWT

Target's database stores a local copy of organisation membership data (the `OrganisationUser` table). This is necessary because Doctrine queries need to join against real DB rows — they cannot query Keycloak at runtime. However, Keycloak remains the **source of truth**: Target never modifies memberships, it only mirrors what the Internal JWT says.

This sync happens **on every authenticated request**, not just on login. This ensures the local copy is always up to date with changes made in Keycloak (new member added, role changed, user removed from an org) without requiring any webhook or background job.

### 9.1 Sync Strategy

**Source of Truth:** Keycloak manages organizations and memberships. Target never queries Keycloak directly — it consumes the organisation claims forwarded by the Global Service via the Internal JWT.

**Sync Trigger:** On each authenticated request, from Internal JWT claims.

```php
class OrganisationSyncService
{
    /**
     * Syncs organisation memberships from token claims and returns the active organisation.
     * This is the single owner of find-or-create logic for organisations.
     */
    public function syncFromToken(User $user, array $claims): Organisation
    {
        // Sync current (active) organization — always present in token
        $orgClaim = $claims['organization'];
        $currentOrg = $this->findOrCreateOrganisation($orgClaim);

        // Sync all memberships from token (multi-org users)
        $memberships = $claims['org_memberships'] ?? [$orgClaim];

        foreach ($memberships as $membership) {
            $memberOrg = $this->findOrCreateOrganisation($membership);
            $this->syncMembership(
                user: $user,
                organisation: $memberOrg,
                role: OrganisationRole::from($membership['roles'][0] ?? 'member'),
            );
        }

        // Remove stale memberships no longer present in token (exemple asociation user-organisation delete by an admin)
        $validOrgIds = array_column($memberships, 'id');
        $this->pruneStaleOrgMemberships($user, $validOrgIds);

        return $currentOrg;
    }

    private function findOrCreateOrganisation(array $orgData): Organisation
    {
        $org = $this->orgGateway->findByKeycloakId($orgData['id']);

        if (!$org) {
            $org = new Organisation(
                name: $orgData['name'],
                keycloakId: $orgData['id'],
            );
            $this->orgGateway->save($org);
        }

        return $org;
    }
}
```

### 9.2 Sync Timing

| Event                       | Action                                    |
| --------------------------- | ----------------------------------------- |
| Every authenticated request | Sync memberships from Internal JWT claims |

---

## 10. Entities Impacted

The table below summarises all entities that need to be created or modified. Entities marked "Inherits via WatchFile" do not need a direct `organisation_id` column — their tenant scope is derived through their parent relationship.

| Entity              | Change                                                | Notes                                              |
| ------------------- | ----------------------------------------------------- | -------------------------------------------------- |
| `Organisation`      | **NEW**                                               | Tenant entity                                      |
| `OrganisationUser`  | **NEW**                                               | User ↔ Org relationship (synced from Internal JWT) |
| `WatchFile`         | Add `organisation_id` FK                              | NOT NULL                                           |
| `Actor`             | Add `organisation_id` FK + migrate unique constraints | See section 10.1                                   |
| `Document`          | Inherits via WatchFile                                | OpenSearch routing                                 |
| `Conversation`      | Inherits via WatchFile                                |                                                    |
| `Message`           | Inherits via Conversation                             |                                                    |
| `Source`            | Inherits via WatchFile                                |                                                    |
| `WatchFileActivity` | Add `organisation_id`, `impersonator`                 | Audit                                              |
| `User`              | Add relation to `OrganisationUser`                    | Multi-org                                          |

### 10.1 Actor — Unique Constraint Migration

`Actor` requires special attention because of a subtle schema conflict with multi-tenancy. Today, actor names and domains are globally unique across the entire database — which made sense in a single-tenant world where actors were shared across all users. With multi-tenancy, "Apple" in Org A and "Apple" in Org B must be independent actors: the uniqueness constraint must become **per-organisation**, not global.

**Current state:** `Actor` has two global `UNIQUE` constraints at column level:

```php
#[ORM\Column(length: 255, unique: true)]
private string $label;             // globally unique across all tenants

#[ORM\Column(length: 255, unique: true, nullable: true)]
private ?string $primaryDomain;    // globally unique across all tenants
```

`AddActorHandler` exploits this: it does `findByLabel` then `findByPrimaryDomain` to reuse an
existing actor across all users. With multi-tenancy, two organisations must be able to track the
same actor independently (e.g. "Apple" in org A and "Apple" in org B are distinct objects with
their own sources and statuses).

**Required changes:**

**1. Drop column-level `unique: true`, replace with composite unique constraints:**

```php
#[ORM\Table(name: 'actor')]
#[ORM\UniqueConstraint(name: 'actor_label_org_unique', columns: ['label', 'organisation_id'])]
#[ORM\UniqueConstraint(name: 'actor_primary_domain_org_unique', columns: ['primary_domain', 'organisation_id'])]
class Actor
{
    #[ORM\ManyToOne(targetEntity: Organisation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Organisation $organisation;

    #[ORM\Column(length: 255)]  // unique: true removed
    private string $label;

    #[ORM\Column(length: 255, nullable: true)]  // unique: true removed
    private ?string $primaryDomain = null;
}
```

**2. Update `ActorGatewayInterface` to scope lookups by organisation:**

```php
interface ActorGatewayInterface
{
    public function findByLabel(string $label, Organisation $organisation): ?Actor;
    public function findByPrimaryDomain(string $domain, Organisation $organisation): ?Actor;
    // ...
}
```

**3. Update `AddActorHandler` to pass the organisation from `TenantContext`:**

```php
// Before
$existingActor = $this->actorGateway->findByLabel($label);

// After
$organisation = $this->tenantContext->getCurrentOrganisation();
$existingActor = $this->actorGateway->findByLabel($label, $organisation);
```

**Migration note:** The Doctrine migration must:

1. Add `organisation_id` column (nullable initially)
2. Backfill existing actors with a default organisation if any data exists
3. Add the composite unique constraints
4. Drop the old column-level unique indexes
5. Set `organisation_id` to NOT NULL

---

## 11. Open Questions

### 11.1 OpenSearch Shard Count

> **Glossary — Shard:** An index is physically split into N independent fragments called shards, each stored on a node as a standalone Lucene index. When searching, OpenSearch queries all relevant shards in parallel and merges the results. More shards = more parallelism, but more memory and coordination overhead. General OpenSearch best practice: target **10–30 GB per shard** for search workloads.

> **⚠️ Provisional** — The shard count and sizing below depend on OVH limits that are currently unverified (see section 6.3). Do not finalize until the OVH support response is received.

The index is currently configured with **5 shards** (section 6.2), but this value has not been validated against actual usage projections. Since the shard count cannot be changed after index creation, this decision must be made before Phase 3.

**Volume projections (as of 2026-03-26):**

| Horizon  | Clients | Cumulative docs | Estimated volume (3–5 KB/doc) |
| -------- | ------- | --------------- | ----------------------------- |
| End 2026 | 30      | ~20M            | 60–100 GB                     |
| End 2027 | 30      | ~50M            | 150–250 GB                    |
| End 2028 | 50      | ~110M           | 330–550 GB                    |

These volumes are structuring: if the OVH Public Cloud Databases limits turn out to be similar to the Logs Data Platform (16 shards × 25 GB = 400 GB max), capacity could be reached by end 2027–2028. Depending on the actual limits, mitigation strategies may include:

- **Temporal index rotation** (1 index/year with aliases)
- **Data retention policy** (archiving old documents)
- **Self-hosted OpenSearch** if managed limits are too restrictive

**Questions to answer before Phase 3:**

1. ~~What is the expected document volume per organisation (now and in 2 years)?~~ → See projections above
2. What is the expected number of organisations at launch vs. steady state? → 30 clients end 2026, 50 end 2028
3. Does OVH confirm the 16 shards/index and 25 GB/shard limits on our specific plan? → **Pending OVH support response**
4. Should we start with more shards (e.g. 10-16) to leave room for growth, accepting the overhead?

---

## 12. Risks and Mitigations

| Risk                          | Impact   | Mitigation                                           |
| ----------------------------- | -------- | ---------------------------------------------------- |
| Cross-tenant data leak        | Critical | Application filtering + exhaustive tests             |
| Context loss in async         | High     | TenantStamp mandatory + validation                   |
| Impersonation abuse           | High     | Keycloak controls + audit logging                    |
| OpenSearch routing forgotten  | High     | `routing.required: true`                             |
| OpenSearch hot shard          | Medium   | Monitor shard size; dedicated index for large orgs   |
| OpenSearch shard count locked | Medium   | Plan shard count upfront; reindex required to change |
| OVH RLS incompatibility       | Medium   | Start without RLS, add if validated                  |
| Keycloak sync race conditions | Low      | Idempotent sync operations                           |

---

## 13. Implementation Plan

### Phase 1: Core Multi-Tenancy (Sprint 1-2)

- [ ] Create `Organisation` and `OrganisationUser` entities
- [ ] Implement `TenantContext` service (no gateway dependencies — see section 4.1)
- [ ] Add `TenantFilter` Doctrine filter
- [ ] Modify `WatchFile` with `organisation_id` FK
- [ ] Modify `Actor` with `organisation_id` FK + migrate unique constraints (see section 10.1)
- [ ] Update `ActorGatewayInterface` and `AddActorHandler` to scope by organisation
- [ ] Add Internal JWT claim extraction (`TenantContextListener` on `kernel.controller` — see section 4.2)
- [ ] Implement `OrganisationSyncService` (single owner of find-or-create logic — see section 9)
- [ ] Create database migrations (follow 5-step Actor migration in section 10.1)

### Phase 2: Async & N8N (Sprint 2-3)

- [ ] Implement `TenantStamp` and `TenantMiddleware`
- [ ] Modify `TriggerAgent` with tenant context
- [ ] Update N8N workflows to forward context
- [ ] Async integration tests

### Phase 3: OpenSearch (Sprint 3)

- [ ] Add mandatory routing configuration
- [ ] Update all index/search operations
- [ ] Cross-tenant leak tests
- [ ] Validate configuration with OVH support

### Phase 4: Impersonation (Sprint 3-4)

- [ ] Enable Token Exchange in Keycloak 26.4.6
- [ ] Add impersonator claim extraction
- [ ] Update audit trail entities
- [ ] Frontend impersonation UI

### Phase 5: Validation & Hardening (Sprint 4)

- [ ] Comprehensive security tests
- [ ] Performance testing with tenant filtering
- [ ] Documentation update
- [ ] (Optional) PostgreSQL RLS if OVH validated

---

## 14. Decisions Summary

| Component        | Decision                                                                              | Rationale                                      |
| ---------------- | ------------------------------------------------------------------------------------- | ---------------------------------------------- |
| Data Segregation | Application-level (Doctrine filters)                                                  | OVH compatible, proven pattern                 |
| Tenant Context   | Internal JWT claims (stateless)                                                       | Forwarded by Global Service, no headers needed |
| Multi-Org        | Keycloak Organizations (native)                                                       | Fully supported in KC 26.4.6                   |
| OpenSearch       | Routing keys                                                                          | Single index, efficient, to validate with OVH  |
| Impersonation    | Keycloak Token Exchange                                                               | Native feature, 20min timeout                  |
| Actor Scope      | Per-tenant                                                                            | Complete data isolation                        |
| KC Sync          | On each request, from Internal JWT, because user configuration may change at any time | Simple, always fresh                           |

---

## 15. Resolved Questions

| Question                     | Decision                                                                                                                                 |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| WatchFile visibility model?  | Explicit sharing retained — `TenantFilter` (cross-org) + `UserAccessibleWatchFileFilter` (intra-org) coexist — `WatchFileUser` unchanged |
| Cross-org WatchFile sharing? | Not planned. Public sharing if needed later.                                                                                             |
| Organisation hierarchy?      | Not planned. Potential future enhancement.                                                                                               |
| Impersonation timeout?       | 20 minutes, configurable in Keycloak                                                                                                     |
| Keycloak sync strategy?      | On each request, from Internal JWT claims (Section 9)                                                                                    |
| Multi-org user support?      | Native Keycloak Organizations feature                                                                                                    |
| Organisation switching UI?   | Out of scope — handled entirely by Keycloak, no custom UI                                                                                |

---

## 16. Actions Required Before Implementation

| Action                                                                                                                                                                                                                                                                                     | Owner             | Priority                  |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------- | ------------------------- |
| Enable Organizations in Keycloak 26.4.6                                                                                                                                                                                                                                                    | DevOps            | P0                        |
| Configure org claim mappers in Keycloak                                                                                                                                                                                                                                                    | DevOps            | P0                        |
| **Validate multi-org membership** on our Keycloak 26.4.6 instance: verify that a user can belong to multiple orgs, that the `org_memberships` claim is correctly populated in the token, and that the org selection screen works at login (known issue: keycloak/keycloak#30747)           | DevOps            | P0                        |
| **Decide WatchFile visibility model** (section 11.1)                                                                                                                                                                                                                                       | PO + Architecture | P0                        |
| **Decide OrganisationUser PK strategy** (section 3.3)                                                                                                                                                                                                                                      | Architecture      | P0                        |
| **Validate OpenSearch limits with OVH support** (shard count, shard size, routing support, alias/ISM support) on **Public Cloud Databases OpenSearch** offering — support request submitted via Clément Dognon (2026-03-24). Sections 6.2, 6.3, 6.4, 11.1 are provisional until validated. | DevOps            | P0 — Blocking for Phase 2 |
| Enable Token Exchange in Keycloak                                                                                                                                                                                                                                                          | DevOps            | P2                        |
| Validate PostgreSQL RLS with OVH                                                                                                                                                                                                                                                           | DevOps            | P3                        |
| Review and approve this ADR                                                                                                                                                                                                                                                                | Architecture      | Before impl               |

---

## References

- [Keycloak Organizations Announcement](https://www.keycloak.org/2024/06/announcement-keycloak-organizations)
- [Keycloak Multi-Org Membership Issue](https://github.com/keycloak/keycloak/issues/30747)
- [Keycloak Token Exchange](https://www.keycloak.org/securing-apps/token-exchange)
- [OVH PostgreSQL Capabilities](https://help.ovhcloud.com/csm/en-public-cloud-databases-postgresql-capabilities?id=kb_article_view&sysparm_article=KB0049315)
- [OVH OpenSearch Capabilities](https://support.us.ovhcloud.com/hc/en-us/articles/21913096243219-OpenSearch-Capabilities-and-limitations)
- [OpenSearch Shard Routing](https://docs.opensearch.org/docs/2.15/search-plugins/searching-data/search-shard-routing/)
- [PostgreSQL Row-Level Security](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
