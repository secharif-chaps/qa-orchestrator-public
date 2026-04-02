# Usage Limits

## Overview

The usage limit module defines quota foundations for enforcing limits across Target. Limits are expressed through domain value objects to keep the business language explicit, while configuration lives in Symfony service parameters that can be adapted per environment.

## Configuration

Usage limits are configured in `api/config/services/usage_limits.yaml`. Each entry represents the maximum volume allowed for a resource. Setting a value to `null` disables the corresponding quota.

```yaml
usage_limits:
    watchfile:
        max_owned_non_archived: 25
        max_active_per_user: 2
    source:
        max_per_watchfile: 100
        max_active_per_watchfile: 20
    actor:
        max_per_watchfile: 75
    document:
        max_per_watchfile: 10000
```

The configuration is injected into `App\Infrastructure\UsageLimit\Config\UsageLimitConfig`, which implements `App\Domain\UsageLimit\UsageLimitConfigInterface`. Handlers and services should depend on the interface to keep a clean separation between the domain and infrastructure layers.

## Domain Model

- `QuotaLimit`: immutable value object representing an optional quota. `null` indicates an unlimited quota.
- `ResourceCount`: immutable value object describing the current usage for a resource.
- `QuotaType`: enum listing supported quota identifiers.
- `QuotaExceededException`: domain exception containing quota metadata (type, limit, current usage) with named constructors per business scenario.
- `InvalidQuotaConfigException`: domain exception raised when the configuration is missing or invalid.

## API Error Handling

`QuotaExceededException` is mapped to HTTP status `429 Too Many Requests` through API Platform. When a quota is reached, the API returns a JSON payload containing the business message from the exception, enabling client applications to display a meaningful explanation.
