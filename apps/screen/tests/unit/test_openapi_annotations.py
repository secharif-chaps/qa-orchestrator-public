"""OpenAPI schema annotation tests.

Verifies that every screen endpoint declares the gateway metadata required
by ADR-0015:

- x-public: true         for unauthenticated endpoints (health, auth, some
                         translation/webhook-like endpoints)
- x-permissions: []      for authenticated endpoints with no specific role
- x-permissions: [role]  when a role gate applies at gateway level
- x-token-cost / x-token-cost-per-item on endpoints that consume tokens

Covers the TAR-1385 acceptance criterion:
    "Le schema OpenAPI (/openapi.json) contient les extensions
     x-permissions, x-token-cost, x-public".
"""

from typing import Any

import pytest
from fastapi.testclient import TestClient

HTTP_METHODS = ("get", "post", "put", "patch", "delete")


@pytest.fixture(scope="module")
def openapi_schema() -> dict[str, Any]:
    from app.main import app

    client = TestClient(app)
    resp = client.get("/openapi.json")
    assert resp.status_code == 200
    return resp.json()


def _iter_operations(schema: dict[str, Any]):
    for path, methods in schema.get("paths", {}).items():
        for method in HTTP_METHODS:
            op = methods.get(method)
            if op is not None:
                yield path, method, op


def test_every_endpoint_has_gateway_annotation(openapi_schema: dict[str, Any]) -> None:
    """Each operation must declare either x-permissions or x-public."""
    missing = [
        f"{method.upper()} {path}"
        for path, method, op in _iter_operations(openapi_schema)
        if "x-permissions" not in op and "x-public" not in op
    ]
    assert missing == [], f"Operations without gateway annotation: {missing}"


def test_public_endpoints_have_no_permissions(openapi_schema: dict[str, Any]) -> None:
    """x-public: true and x-permissions should be mutually exclusive."""
    conflicts = [
        f"{method.upper()} {path}"
        for path, method, op in _iter_operations(openapi_schema)
        if op.get("x-public") is True and "x-permissions" in op
    ]
    assert conflicts == [], f"Operations declare both x-public and x-permissions: {conflicts}"


@pytest.mark.parametrize(
    "path,method,expected",
    [
        ("/api/companies/{company_id}", "put", ["organization.write"]),
        ("/api/companies/{company_id}", "delete", ["company.delete"]),
        ("/api/companies/{company_id}/restore", "post", ["company.delete"]),
        ("/api/companies/{company_id}/refresh", "post", ["company.create"]),
        ("/api/companies/csv/validate", "post", ["company.create"]),
        ("/api/companies/csv/import", "post", ["company.create"]),
        ("/api/admin/tasks/fail-stuck", "post", ["admin.organizations"]),
        ("/api/admin/usage-stats", "get", ["admin.organizations"]),
        ("/api/admin/tasks", "get", ["admin.tasks"]),
        ("/api/admin/tasks/stats", "get", ["admin.tasks"]),
        ("/api/admin/tasks/restart", "post", ["admin.tasks"]),
        ("/api/security/stats", "get", ["admin"]),
        ("/api/cost-analysis/global", "get", ["admin.costs"]),
        ("/api/cost-analysis/by-organization", "get", ["admin.costs"]),
        ("/api/cost-analysis/by-task-type", "get", ["admin.costs"]),
        ("/api/organizations/{organization_id}/feature-flags/{flag}", "patch", ["admin.organizations"]),
        (
            "/api/organizations/{organization_id}/credits/stats",
            "get",
            ["admin.organizations", "organization.manage"],
        ),
        (
            "/api/organizations/{organization_id}/credits/top-users",
            "get",
            ["admin.organizations", "organization.manage"],
        ),
        (
            "/api/organizations/{organization_id}/credits/daily-usage",
            "get",
            ["admin.organizations", "organization.manage"],
        ),
        (
            "/api/organizations/{organization_id}/data-sources/{source}/config",
            "get",
            ["admin.organizations"],
        ),
        (
            "/api/organizations/{organization_id}/data-sources/{source}/config",
            "put",
            ["admin.organizations"],
        ),
    ],
)
def test_permissions_match_runtime(openapi_schema: dict[str, Any], path: str, method: str, expected: list[str]) -> None:
    op = openapi_schema["paths"][path][method]
    assert sorted(op.get("x-permissions", [])) == sorted(expected)


@pytest.mark.parametrize(
    "path,method",
    [
        ("/health/live", "get"),
        ("/health/ready", "get"),
        ("/api/auth/login", "post"),
        ("/api/auth/refresh", "post"),
        ("/api/auth/logout", "post"),
        ("/api/security/health", "get"),
        ("/api/translation/list", "get"),
    ],
)
def test_public_endpoints_are_marked(openapi_schema: dict[str, Any], path: str, method: str) -> None:
    op = openapi_schema["paths"][path][method]
    assert op.get("x-public") is True


@pytest.mark.parametrize(
    "path,method",
    [
        ("/api/companies/", "post"),
        ("/api/companies/{company_id}/refresh", "post"),
        ("/api/companies/csv/import", "post"),
    ],
)
def test_token_consuming_endpoints_declare_cost(openapi_schema: dict[str, Any], path: str, method: str) -> None:
    """Endpoints that debit tokens must declare a cost extension."""
    op = openapi_schema["paths"][path][method]
    assert "x-token-cost" in op or "x-token-cost-per-item" in op, (
        f"Missing token-cost annotation on {method.upper()} {path}"
    )
