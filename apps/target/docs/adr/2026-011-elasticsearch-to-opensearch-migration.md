# ADR-2026-011: Elasticsearch to OpenSearch Migration

## Status

**Accepted** — Chosen approach: **composer patch from the upstream PR**

## Context

The Basil project currently uses **Elasticsearch 9.1** via the PHP client `elasticsearch/elasticsearch` (v8.19) and the **API Platform Elasticsearch** integration (v4.1).

For licensing and infrastructure compatibility reasons (OVH, our hosting provider, offers managed OpenSearch), we are migrating to **OpenSearch**.

An **upstream PR is being prepared** on API Platform by one of our developers to add native OpenSearch support (not yet opened):

- **Branch**: https://github.com/api-platform/core/compare/main...hotfix31:api-platform-core:feat/opensearch-support

## Decision

**We apply the upstream PR diff as a composer patch** via `cweagans/composer-patches`. This approach:

- Applies the exact PR code without manual reimplementation
- Avoids introducing custom code (decorators, compiler pass) that would need maintenance
- Makes post-merge cleanup trivial: remove the patch and update the package

---

## Current State

### PHP Dependencies (composer.json)

| Package                       | Version | Role                                                                       |
| ----------------------------- | ------- | -------------------------------------------------------------------------- |
| `api-platform/elasticsearch`  | ^4.1    | Elasticsearch integration in API Platform (providers, filters, extensions) |
| `elasticsearch/elasticsearch` | ^8.19   | Official Elasticsearch PHP client                                          |

### Docker Services (compose.yaml)

- Image: `docker.elastic.co/elasticsearch/elasticsearch:9.1.3`
- Volume: `elasticsearch_data`
- Port: `9200` (configurable via `ELASTICSEARCH_PORT`)

### Impacted Application Code

| Path                                                                                | Description                                            |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------ |
| `api/config/packages/api_platform.yaml`                                             | Elasticsearch configuration (hosts, SSL, enabled)      |
| `api/config/services/elasticsearch.yaml`                                            | `Elastic\Elasticsearch\Client` alias                   |
| `api/src/Kernel.php`                                                                | `ElasticsearchClientHandlerCompilerPass` registration  |
| `api/src/Infrastructure/Symfony/ElasticsearchClientHandlerCompilerPass.php`         | ES client SSL configuration                            |
| `api/src/Infrastructure/Elasticsearch/State/CollectionProviderWithAggregations.php` | Custom provider with aggregations (type-hint `Client`) |
| `api/src/Infrastructure/Elasticsearch/`                                             | Filters, Extensions, Migrations (15 files)             |
| `api/src/Infrastructure/Document/DocumentElasticSearchGateway.php`                  | Document gateway to ES                                 |
| `api/src/Infrastructure/WatchFileEvent/WatchFileEventElasticsearchGateway.php`      | Event gateway to ES                                    |
| `api/migrations/elasticsearch/`                                                     | ES index migrations (10 files)                         |

---

## Main Technical Problem

API Platform providers (`CollectionProvider`, `ItemProvider`) have two issues for OpenSearch support:

### 1. Constructor Type-hint

```php
// vendor/api-platform/elasticsearch/State/CollectionProvider.php (and ItemProvider.php)
public function __construct(
    private readonly V7Client|Client $client, // does not support OpenSearchClient
    // ...
)
```

### 2. 404 Exception Handling

The providers catch `V7Missing404Exception` and `ClientResponseException` (Elasticsearch classes). OpenSearch has its own exceptions (`OpenSearch\Common\Exceptions\Missing404Exception`) which are not caught:

```php
// Current CollectionProvider.php
catch (V7Missing404Exception $e) { /* ... */ }         // ES v7
catch (ClientResponseException $e) { /* ... */ }        // ES v8

// Current ItemProvider.php
catch (V7Missing404Exception) { return null; }          // ES v7
catch (ClientResponseException $e) { /* ... */ }        // ES v8
// No catch for OpenSearch\Common\Exceptions\Missing404Exception
```

The upstream PR solves both issues. The composer patch applies them directly in the vendor.

---

## Upstream PR Content (Reference)

We are carrying a PR to bring the following changes to API Platform core:

| File                          | Change                                                                                              |
| ----------------------------- | --------------------------------------------------------------------------------------------------- |
| `CollectionProvider.php`      | Add `OpenSearchClient` to type-hint + catch `OpenSearchMissing404Exception`                         |
| `ItemProvider.php`            | Same                                                                                                |
| `ApiPlatformExtension.php`    | Select `ClientBuilder` based on `elasticsearch.client` config (`"elasticsearch"` or `"opensearch"`) |
| `ElasticsearchClientPass.php` | Same on the compiler pass side                                                                      |
| `Configuration.php`           | Add an enum `client` node with `opensearch-php` package validation                                  |

---

## Migration Plan

### Phase 1: Application-side OpenSearch Support (composer patch)

#### 1.1 Install cweagans/composer-patches

```bash
composer require cweagans/composer-patches
```

#### 1.2 Retrieve the PR diff

```bash
mkdir -p api/patches
curl -L https://github.com/api-platform/core/compare/main...hotfix31:api-platform-core:feat/opensearch-support.diff -o api/patches/api-platform-opensearch.diff
```

> **Warning**: the diff targets `api-platform/core` but the installed package is `api-platform/elasticsearch`. The paths in the `.diff` file may need to be adapted to match the package structure in `vendor/`.

#### 1.3 Configure the patch in composer.json

```json
{
    "extra": {
        "patches": {
            "api-platform/elasticsearch": {
                "Add OpenSearch support (PR hotfix31)": "patches/api-platform-opensearch.diff"
            }
        }
    }
}
```

#### 1.4 Add the OpenSearch PHP client

```bash
composer require opensearch-project/opensearch-php
```

> **Note**: the PHP client version (`opensearch-php`) and the OpenSearch server version are not necessarily aligned. Check the minimum version constraint specified in the upstream PR diff and adapt if needed (e.g., `opensearch-project/opensearch-php:^2.0`).

#### 1.5 Apply and verify

```bash
composer install
```

Composer will automatically apply the patch during installation. On failure (line conflict), an explicit error message is displayed. **Verify that the patch applies cleanly before continuing.**

#### 1.6 Configure API Platform for OpenSearch

```yaml
# config/packages/api_platform.yaml
api_platform:
    elasticsearch:
        enabled: true
        client: opensearch # node added by the patch
        hosts: ['%env(string:OPENSEARCH_URL)%']
```

#### 1.7 Update the client alias in `elasticsearch.yaml`

The file `api/config/services/elasticsearch.yaml` defines an alias `Elastic\Elasticsearch\Client` pointing to the API Platform auto-configured client. With the migration, replace this alias to point to the OpenSearch client:

```yaml
# api/config/services/elasticsearch.yaml
services:
    # ...

    # Replace the Elasticsearch alias with OpenSearch
    OpenSearch\Client: '@api_platform.elasticsearch.client'
```

Remove the old `Elastic\Elasticsearch\Client` alias if it is no longer used in the application code.

#### 1.8 Adapt the CollectionProviderWithAggregations

Our custom provider is not covered by the patch (it is our code). Update the type-hint:

```php
// api/src/Infrastructure/Elasticsearch/State/CollectionProviderWithAggregations.php

use Elastic\Elasticsearch\Client;
use OpenSearch\Client as OpenSearchClient;

class CollectionProviderWithAggregations implements ProviderInterface
{
    public function __construct(
        private readonly Client|OpenSearchClient $client,
        // ...
    ) {}
}
```

#### 1.9 Remove the ElasticsearchClientHandlerCompilerPass

This compiler pass configures the CA bundle SSL for the ES client. Since PR [api-platform/core#7450](https://github.com/api-platform/core/pull/7450), API Platform supports SSL configuration natively. This compiler pass is therefore no longer needed and can be removed, along with its registration in `Kernel.php`.

#### 1.10 Verify Gateway Compatibility

The gateways (`DocumentElasticSearchGateway`, `WatchFileEventElasticsearchGateway`) use the client directly. Verify method compatibility:

- `$client->search()`: compatible
- `$client->index()`: compatible
- `$client->get()`: compatible
- `$client->delete()`: compatible
- `$client->indices()->create()` / `putMapping()`: verify parameters
- Response format: OpenSearch returns arrays, ES 8 returns `Elasticsearch` objects (the existing `instanceof Elasticsearch` check has no impact as it tests `class_exists` first)

### Phase 2: Docker / Infrastructure Migration

> **OVH Constraint**: OVH will be our production hosting provider. The OpenSearch version **must align with the versions supported by OVH Managed Databases for OpenSearch**.
>
> Check the OVH availability page before locking the version:
> https://docs.ovh.com/fr/databases/opensearch/
>
> At the time of writing, OVH supports OpenSearch **1.3** and **2.x**. Use the latest major version offered by OVH locally to ensure dev/prod parity.

#### 2.1 Replace the Docker Image

In `compose.yaml`, adapt the tag to the exact version supported by OVH:

```yaml
# Before
elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:9.1.3

# After (align the tag with the OVH version)
opensearch:
    image: opensearchproject/opensearch:2.x # replace 2.x with the exact OVH version
    environment:
        - discovery.type=single-node
        - DISABLE_SECURITY_PLUGIN=true # for local dev
        - OPENSEARCH_JAVA_OPTS=-Xms512m -Xmx512m
```

#### 2.2 Environment Variables

Rename/add the variables:

| Old                  | New                   | Notes                        |
| -------------------- | --------------------- | ---------------------------- |
| `ELASTICSEARCH_URL`  | `OPENSEARCH_URL`      | or keep the old one as alias |
| `ELASTICSEARCH_HOST` | `OPENSEARCH_HOST`     |                              |
| `ELASTICSEARCH_PORT` | `OPENSEARCH_PORT`     |                              |
| `ELASTIC_PASSWORD`   | `OPENSEARCH_PASSWORD` |                              |

#### 2.3 Replace Kibana with OpenSearch Dashboards

```yaml
# Before
kibana:
    image: docker.elastic.co/kibana/kibana:9.1.3
    ports:
        - '5601:5601'
    environment:
        - ELASTICSEARCH_HOSTS=http://elasticsearch:9200

# After
opensearch-dashboards:
    image: opensearchproject/opensearch-dashboards:2.x
    ports:
        - '5601:5601'
    environment:
        - OPENSEARCH_HOSTS=["http://opensearch:9200"]
        - DISABLE_SECURITY_DASHBOARDS_PLUGIN=true # for local dev
    depends_on:
        - opensearch
```

Update the Caddy configuration (virtualhost rename):

```
opensearch.basil.local {
    reverse_proxy opensearch-dashboards:5601
}
```

**Actions related to the hostname change**:

- Update the Caddy configuration with the new virtualhost `opensearch.basil.local`
- Regenerate local SSL certificates to include `opensearch.basil.local`
- Add `opensearch.basil.local` to `/etc/hosts` (or equivalent) for local dev
- Remove the old hostname `kibana.basil.local` from the Caddy config and certificates
- Update CLAUDE.md (Access Points section)

Notable differences for development:

| Kibana                | OpenSearch Dashboards    | Notes                                      |
| --------------------- | ------------------------ | ------------------------------------------ |
| Dev Tools (Console)   | Dev Tools (Console)      | Identical syntax, compatible               |
| Discover              | Discover                 | Same exploration interface                 |
| Index Patterns        | Index Patterns           | Identical configuration                    |
| Visualize / Dashboard | Visualize / Dashboard    | Compatible, some advanced types may differ |
| Alerting (X-Pack)     | Alerting (native plugin) | Different API, not used in local dev       |

Impact:

- Access remains on the same port (`5601`)
- Dev Tools work identically
- Index patterns must be recreated manually (no automatic migration)
- Docker volume `kibana_data` is replaced by `opensearch_dashboards_data`

### Phase 3: Index Migrations

The existing Elasticsearch migrations (`api/migrations/elasticsearch/`) use the index API. The API is compatible between ES and OpenSearch for basic operations (index creation, mapping, aliases).

Review each migration for potential incompatibilities on:

- ES-specific field types (e.g., `dense_vector` with HNSW)
- Custom analyzers
- Index-specific parameters

### Phase 4: Project Documentation Update

The migration impacts many Elasticsearch references in the documentation. This phase should be carried out **in parallel with phases 1-3** to keep the documentation consistent with the code.

#### 4.1 CLAUDE.md

Update the references in the root `CLAUDE.md` file:

| Section                                   | Change                                                                         |
| ----------------------------------------- | ------------------------------------------------------------------------------ |
| Tech Stack > Backend                      | Replace `Elasticsearch 9.1` with `OpenSearch 2.x` (OVH version)                |
| Tech Stack > Backend                      | Replace `elasticsearch/elasticsearch` with `opensearch-project/opensearch-php` |
| Tech Stack > Infrastructure               | Replace `Kibana 9.1` with `OpenSearch Dashboards 2.x`                          |
| Access Points                             | Update the Kibana entry (`kibana.basil.local`) if the virtualhost changes      |
| Architecture Overview > Project Structure | Adapt references to ES files if renamed                                        |

#### 4.2 MkDocs Documentation (`docs/`)

| File                                     | Action                                                            |
| ---------------------------------------- | ----------------------------------------------------------------- |
| `docs/elasticsearch-kibana.md`           | Rewrite for OpenSearch Dashboards or archive                      |
| `docs/elasticsearch-api.md`              | Adapt query examples if necessary                                 |
| `docs/elasticsearch-security-setup.md`   | Rewrite for OpenSearch security config                            |
| `docs/elasticsearch-migrations.md`       | Adapt index migration instructions                                |
| `docs/elasticsearch-watchfile-events.md` | Verify example compatibility                                      |
| `docs/getting-started.md`                | Update installation instructions (Docker image, environment vars) |
| `docs/development-guide.md`              | Update ES references if present                                   |
| `docs/troubleshooting.md`                | Adapt ES debugging sections                                       |
| `mkdocs.yml`                             | Rename navigation entries in the "Elasticsearch" section          |

#### 4.3 Code Comments and References

- Search all occurrences of `elasticsearch` / `Elasticsearch` / `ES` in comments, docblocks, and variable names to assess what needs renaming
- Do not rename references to vendor classes (`ApiPlatform\Elasticsearch\*`) which remain unchanged

#### 4.4 Configuration and Ancillary Files

| File                                     | Action                            |
| ---------------------------------------- | --------------------------------- |
| `compose.yaml` / `compose.override.yaml` | Service names, volumes, variables |
| `.env` / `.env.example`                  | Environment variables             |
| `docker/`                                | Caddy config files, volumes       |
| `Taskfile.yaml`                          | ES-related tasks if any           |

### Phase 5: Post-merge Upstream Cleanup

Once the API Platform PR is merged and a new version is released:

1. **Delete** the `api/patches/api-platform-opensearch.diff` file
2. **Remove** the `extra.patches` section from `composer.json`
3. **Update** `api-platform/elasticsearch` to the version that includes OpenSearch support
4. **Verify** that the `elasticsearch.client: opensearch` config is still natively supported
5. **Clean up** the type union in `CollectionProviderWithAggregations` if the upstream provider natively handles both clients
6. **Uninstall** `cweagans/composer-patches` if no other patches are in use

---

## Carrying the Upstream PR to Merge

The PR (hotfix31) is our contribution. We must actively carry it to merge so that the patch remains temporary.

### Actions Required

1. **Respond to reviews**: Apply changes requested by API Platform maintainers
2. **Keep the PR up to date**: Regularly rebase on API Platform `main`
3. **Add tests**: Provide additional coverage if requested, ensure CI passes
4. **Follow code style**: PHP-CS-Fixer, PHPStan, upstream project file structure
5. **Documentation**: Propose an addition to the official docs if maintainers request it

### Follow-up

| Action                                 | Owner    | Frequency                                       |
| -------------------------------------- | -------- | ----------------------------------------------- |
| Check PR status and comments           | hotfix31 | Weekly                                          |
| Rebase on API Platform `main`          | hotfix31 | On each conflict or significant merge on `main` |
| Ping maintainers if no response        | hotfix31 | Every 2 weeks                                   |
| Update this document if the PR evolves | Team     | On each major PR change                         |

### Fallback Plan if the PR Stalls (> 3 months)

- **Fork**: Maintain a fork of `api-platform/elasticsearch` with OpenSearch support, published on a private Composer repository
- **Switch to the decorator approach**: See Appendix A below
- **Contribute differently**: Propose a separate `api-platform/opensearch` package if the rejection is due to an architectural disagreement

---

## Risks and Considerations

| Risk                                                  | Impact                       | Mitigation                                                           |
| ----------------------------------------------------- | ---------------------------- | -------------------------------------------------------------------- |
| Patch breaks after a `composer update` of the package | Build blocked                | Verify patch application in CI; adapt the diff if needed             |
| Diff paths incompatible (core vs elasticsearch)       | Patch rejected               | Manually adapt paths in the `.diff` file                             |
| API incompatibility between ES/OpenSearch clients     | Runtime errors               | Exhaustive integration tests on ES endpoints                         |
| Behavioral differences in responses                   | Incorrect results            | Verify aggregation format and scoring                                |
| Uncaught OpenSearch exceptions in custom code         | 500 instead of 404           | Audit all `catch` blocks on ES classes in our code                   |
| Incompatible index migrations                         | Indexes not created          | Manual review of each migration                                      |
| Upstream PR rejected or modified                      | Workaround becomes permanent | Actively follow the PR, adapt if needed                              |
| Version misalignment with OVH                         | Production incompatibilities | Always check OVH supported versions before locking the local version |

---

## Appendix A: Explored Alternatives

### A1. Manual Provider Decoration (not retained)

This approach consists of creating Symfony decorators around API Platform providers to catch OpenSearch exceptions, combined with a compiler pass to inject the OpenSearch client despite incompatible type-hints.

**Reason for rejection**: introduces custom code to maintain (2 decorators + 1 compiler pass + service config) whereas the composer patch directly applies the PR code without adding anything to our codebase.

**Implementation detail (kept for reference)**:

#### CollectionProvider Decorator

```php
// api/src/Infrastructure/Elasticsearch/State/OpenSearchCollectionProvider.php

namespace App\Infrastructure\Elasticsearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use OpenSearch\Common\Exceptions\Missing404Exception as OpenSearchMissing404Exception;
use ApiPlatform\State\ApiResource\Error;

final class OpenSearchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $inner,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        try {
            return $this->inner->provide($operation, $uriVariables, $context);
        } catch (OpenSearchMissing404Exception $e) {
            throw new Error(
                status: $e->getCode(),
                detail: $e->getMessage(),
                title: $e->getMessage(),
                originalTrace: $e->getTrace()
            );
        }
    }
}
```

#### ItemProvider Decorator

```php
// api/src/Infrastructure/Elasticsearch/State/OpenSearchItemProvider.php

namespace App\Infrastructure\Elasticsearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use OpenSearch\Common\Exceptions\Missing404Exception as OpenSearchMissing404Exception;

final class OpenSearchItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $inner,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        try {
            return $this->inner->provide($operation, $uriVariables, $context);
        } catch (OpenSearchMissing404Exception) {
            return null;
        }
    }
}
```

#### Service Configuration

```yaml
# api/config/services/elasticsearch.yaml

services:
    App\Infrastructure\Elasticsearch\State\OpenSearchCollectionProvider:
        decorates: 'api_platform.elasticsearch.state.collection_provider'

    App\Infrastructure\Elasticsearch\State\OpenSearchItemProvider:
        decorates: 'api_platform.elasticsearch.state.item_provider'
```

#### Compiler Pass to Inject the OpenSearch Client

Symfony's compiled container does not validate PHP type-hints at injection time. We therefore inject an `OpenSearchClient` where an `Elastic\Elasticsearch\Client` is expected.

```php
// api/src/Infrastructure/DependencyInjection/Compiler/OverrideElasticsearchClientPass.php

namespace App\Infrastructure\DependencyInjection\Compiler;

use OpenSearch\Client as OpenSearchClient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class OverrideElasticsearchClientPass implements CompilerPassInterface
{
    private const SERVICES_TO_OVERRIDE = [
        'api_platform.elasticsearch.state.collection_provider',
        'api_platform.elasticsearch.state.item_provider',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SERVICES_TO_OVERRIDE as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            $definition = $container->getDefinition($serviceId);
            $definition->setArgument('$client', new Reference(OpenSearchClient::class));
        }
    }
}
```

Kernel registration:

```php
// api/src/Kernel.php
protected function build(ContainerBuilder $container): void
{
    $container->addCompilerPass(new OverrideElasticsearchClientPass());

    // existing compiler pass (SSL)
    $container->addCompilerPass(
        new ElasticsearchClientHandlerCompilerPass(),
        PassConfig::TYPE_BEFORE_OPTIMIZATION,
        -1,
    );
}
```

> **Summary**: the compiler pass replaces the `$client` argument to bypass the constructor type-hint, and the decoration wraps `provide()` to catch OpenSearch-specific exceptions.

### A2. Package Fork (not retained)

Maintain a fork of `api-platform/elasticsearch` with integrated OpenSearch support, published via a private Composer repository.

**Reason for rejection**: maintenance cost too high for a temporary need. The fork diverges from the official package at each release, requiring regular rebases. Only to be considered if the upstream PR is definitively rejected (see fallback plan).

---

## Useful Links

- [opensearch-php client](https://github.com/opensearch-project/opensearch-php)
- [cweagans/composer-patches](https://github.com/cweagans/composer-patches)
- [API Platform Elasticsearch](https://api-platform.com/docs/core/elasticsearch/)
- [OpenSearch / Elasticsearch API Compatibility](https://opensearch.org/docs/latest/clients/index/)
- [OVH Managed Databases for OpenSearch](https://docs.ovh.com/fr/databases/opensearch/)
- [Upstream API Platform PR (hotfix31)](https://github.com/api-platform/core/compare/main...hotfix31:api-platform-core:feat/opensearch-support)
