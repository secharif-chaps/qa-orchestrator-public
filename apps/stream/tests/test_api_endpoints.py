"""API integration tests using FastAPI TestClient."""

from unittest.mock import AsyncMock, patch

from fastapi.testclient import TestClient

from app.models.delivery import StreamDelivery

from app.adapters.base import DispatchResult


def _stream_payload(**overrides) -> dict:
    defaults = {
        "name": "Test Stream",
        "channel_type": "webhook",
        "channel_config": {"url": "https://example.com/hook", "headers": {}, "secret": None},
        "mode": "live",
        "subscribed_events": ["screen.company.created"],
    }
    defaults.update(overrides)
    return defaults


class TestEventTypesEndpoint:
    def test_list_event_types_no_auth(self, test_client: TestClient):
        """Event types endpoint is public — no auth required. Returns grouped format."""
        response = test_client.get("/api/event-types")
        assert response.status_code == 200
        data = response.json()
        assert isinstance(data, list)
        # Grouped format: list of source groups
        assert len(data) >= 2  # at least screen and target groups
        assert all("source" in group for group in data)
        assert all("events" in group for group in data)

    def test_event_types_structure(self, test_client: TestClient):
        """Each group has source, label, available, and events list."""
        response = test_client.get("/api/event-types")
        group = response.json()[0]
        assert "source" in group
        assert "label" in group
        assert "available" in group
        assert "events" in group
        assert isinstance(group["events"], list)
        # Each event entry has type and label
        event_entry = group["events"][0]
        assert "type" in event_entry
        assert "label" in event_entry


class TestStreamCRUDEndpoints:
    def test_create_stream(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.post(
            "/api/folders/folder-abc/streams",
            json=_stream_payload(),
            headers=internal_auth_header,
        )
        assert response.status_code == 201
        data = response.json()
        assert data["name"] == "Test Stream"
        assert data["folder_id"] == "folder-abc"
        assert data["status"] == "active"

    def test_create_stream_no_auth(self, test_client: TestClient):
        response = test_client.post(
            "/api/folders/folder-abc/streams",
            json=_stream_payload(),
        )
        assert response.status_code == 401

    def test_create_stream_read_only_role(self, test_client: TestClient, read_only_auth_header: dict):
        response = test_client.post(
            "/api/folders/folder-abc/streams",
            json=_stream_payload(),
            headers=read_only_auth_header,
        )
        assert response.status_code == 403

    def test_list_streams(self, test_client: TestClient, internal_auth_header: dict):
        # Create 2 streams
        test_client.post("/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header)
        test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(name="Stream 2"), headers=internal_auth_header
        )

        response = test_client.get("/api/folders/folder-abc/streams", headers=internal_auth_header)
        assert response.status_code == 200
        data = response.json()
        assert data["meta"]["total"] == 2
        assert len(data["data"]) == 2

    def test_list_streams_pagination(self, test_client: TestClient, internal_auth_header: dict):
        for i in range(5):
            test_client.post(
                "/api/folders/folder-abc/streams", json=_stream_payload(name=f"S{i}"), headers=internal_auth_header
            )

        response = test_client.get("/api/folders/folder-abc/streams?page=1&per_page=2", headers=internal_auth_header)
        data = response.json()
        assert data["meta"]["total"] == 5
        assert len(data["data"]) == 2
        assert data["meta"]["current_page"] == 1
        assert data["meta"]["last_page"] == 3

    def test_get_stream(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        response = test_client.get(f"/api/streams/{stream_id}", headers=internal_auth_header)
        assert response.status_code == 200
        assert response.json()["id"] == stream_id

    def test_get_stream_not_found(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.get("/api/streams/999", headers=internal_auth_header)
        assert response.status_code == 404

    def test_update_stream(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        response = test_client.put(
            f"/api/streams/{stream_id}",
            json={"name": "Updated Name"},
            headers=internal_auth_header,
        )
        assert response.status_code == 200
        assert response.json()["name"] == "Updated Name"

    def test_delete_stream(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        response = test_client.delete(f"/api/streams/{stream_id}", headers=internal_auth_header)
        assert response.status_code == 204

        # Verify deleted
        response = test_client.get(f"/api/streams/{stream_id}", headers=internal_auth_header)
        assert response.status_code == 404

    def test_update_status(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        response = test_client.patch(
            f"/api/streams/{stream_id}/status",
            json={"status": "paused"},
            headers=internal_auth_header,
        )
        assert response.status_code == 200
        assert response.json()["status"] == "paused"

    def test_update_status_invalid_transition(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        # Archive it
        test_client.patch(f"/api/streams/{stream_id}/status", json={"status": "archived"}, headers=internal_auth_header)

        # Try to reactivate — should fail
        response = test_client.patch(
            f"/api/streams/{stream_id}/status",
            json={"status": "active"},
            headers=internal_auth_header,
        )
        assert response.status_code == 400

    def test_list_deliveries(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        response = test_client.get(f"/api/streams/{stream_id}/deliveries", headers=internal_auth_header)
        assert response.status_code == 200
        data = response.json()
        assert data["meta"]["total"] == 0
        assert data["data"] == []


class TestInternalEndpoints:
    def test_ingest_event(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.post(
            "/internal/events/ingest",
            json={
                "event_type": "screen.company.created",
                "folder_id": "folder-abc",
                "payload": {"company_id": 42},
            },
            params={"organization_id": "test-org-123"},
            headers=internal_auth_header,
        )
        assert response.status_code == 201
        data = response.json()
        assert data["event_type"] == "screen.company.created"
        assert data["source"] == "screen"
        assert data["folder_id"] == "folder-abc"

    def test_ingest_event_no_auth(self, test_client: TestClient):
        response = test_client.post(
            "/internal/events/ingest",
            json={"event_type": "screen.company.created", "folder_id": "folder-abc", "payload": {}},
            params={"organization_id": "test-org-123"},
        )
        assert response.status_code == 401

    def test_ingest_creates_deliveries(self, test_client: TestClient, internal_auth_header: dict, db_session):
        # Create a stream via API first
        test_client.post("/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header)

        # Ingest a matching event (same folder_id as the stream)
        response = test_client.post(
            "/internal/events/ingest",
            json={"event_type": "screen.company.created", "folder_id": "folder-abc", "payload": {"company_id": 1}},
            params={"organization_id": "test-org-123"},
            headers=internal_auth_header,
        )
        assert response.status_code == 201

        # Verify delivery was actually created in DB
        event_id = response.json()["id"]
        deliveries = db_session.query(StreamDelivery).filter(StreamDelivery.event_id == event_id).all()
        assert len(deliveries) == 1

    def test_cleanup_events(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.delete(
            "/internal/events/cleanup",
            params={
                "before": "2099-01-01T00:00:00Z",
                "organization_id": "test-org-123",
            },
            headers=internal_auth_header,
        )
        assert response.status_code == 200
        assert "deleted_count" in response.json()

    def test_ingest_event_org_mismatch_returns_403(self, test_client: TestClient, internal_auth_header: dict):
        """JWT org_id must match query param organization_id."""
        response = test_client.post(
            "/internal/events/ingest",
            json={"event_type": "screen.company.created", "folder_id": "folder-abc", "payload": {}},
            params={"organization_id": "other-org-456"},
            headers=internal_auth_header,
        )
        assert response.status_code == 403

    def test_cleanup_events_org_mismatch_returns_403(self, test_client: TestClient, internal_auth_header: dict):
        """JWT org_id must match query param organization_id."""
        response = test_client.delete(
            "/internal/events/cleanup",
            params={"before": "2099-01-01T00:00:00Z", "organization_id": "other-org-456"},
            headers=internal_auth_header,
        )
        assert response.status_code == 403

    def test_internal_routes_not_in_openapi(self, test_client: TestClient):
        """Internal routes should NOT appear in the OpenAPI schema."""
        response = test_client.get("/openapi.json")
        schema = response.json()
        paths = schema.get("paths", {})
        assert "/internal/events/ingest" not in paths
        assert "/internal/events/cleanup" not in paths
        # But API routes should be visible
        assert "/api/event-types" in paths


class TestAuthEdgeCases:
    """Test auth edge cases: expired JWT, invalid signature, cross-org access."""

    def test_expired_jwt_returns_401(self, test_client: TestClient):
        from tests.conftest import _make_internal_token

        expired_token = _make_internal_token(expired=True)
        headers = {"Authorization": f"Internal {expired_token}"}
        response = test_client.get("/api/folders/folder-abc/streams", headers=headers)
        assert response.status_code == 401

    def test_invalid_jwt_signature_returns_401(self, test_client: TestClient):
        import jwt as pyjwt

        from tests.conftest import _JWT_ALGORITHM, _JWT_ISSUER

        from datetime import UTC, datetime, timedelta

        now = datetime.now(UTC)
        payload = {
            "sub": "test-user-123",
            "username": "testuser",
            "org_id": "test-org-123",
            "org_name": "Test Org",
            "roles": ["stream.read", "stream.write"],
            "iss": _JWT_ISSUER,
            "iat": int(now.timestamp()),
            "exp": int((now + timedelta(minutes=5)).timestamp()),
        }
        bad_token = pyjwt.encode(payload, "wrong-secret-key-that-is-long-enough", algorithm=_JWT_ALGORITHM)
        headers = {"Authorization": f"Internal {bad_token}"}
        response = test_client.get("/api/folders/folder-abc/streams", headers=headers)
        assert response.status_code == 401

    def test_bearer_prefix_instead_of_internal_returns_401(self, test_client: TestClient):
        from tests.conftest import _make_internal_token

        token = _make_internal_token()
        headers = {"Authorization": f"Bearer {token}"}
        response = test_client.get("/api/folders/folder-abc/streams", headers=headers)
        assert response.status_code == 401

    def test_cross_org_access_returns_404(self, test_client: TestClient, internal_auth_header: dict):
        """A stream created by org-123 should not be accessible by org-456."""
        from tests.conftest import _make_internal_token

        # Create stream as org-123
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams",
            json=_stream_payload(),
            headers=internal_auth_header,
        )
        stream_id = create_resp.json()["id"]

        # Try to access as org-456
        other_org_token = _make_internal_token(org_id="other-org-456")
        other_org_header = {"Authorization": f"Internal {other_org_token}"}
        response = test_client.get(f"/api/streams/{stream_id}", headers=other_org_header)
        assert response.status_code == 404

    def test_cross_org_list_returns_empty(self, test_client: TestClient, internal_auth_header: dict):
        """Listing streams in a folder from a different org returns empty results."""
        from tests.conftest import _make_internal_token

        # Create stream as org-123
        test_client.post("/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header)

        # List as org-456
        other_org_token = _make_internal_token(org_id="other-org-456")
        other_org_header = {"Authorization": f"Internal {other_org_token}"}
        response = test_client.get("/api/folders/folder-abc/streams", headers=other_org_header)
        assert response.status_code == 200
        assert response.json()["meta"]["total"] == 0


class TestPaginationEdgeCases:
    """Test pagination boundary conditions."""

    def test_page_beyond_last_returns_empty_data(self, test_client: TestClient, internal_auth_header: dict):
        test_client.post("/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header)

        response = test_client.get("/api/folders/folder-abc/streams?page=999", headers=internal_auth_header)
        assert response.status_code == 200
        assert response.json()["data"] == []
        assert response.json()["meta"]["total"] == 1

    def test_page_zero_returns_422(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.get("/api/folders/folder-abc/streams?page=0", headers=internal_auth_header)
        assert response.status_code == 422

    def test_per_page_zero_returns_422(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.get("/api/folders/folder-abc/streams?per_page=0", headers=internal_auth_header)
        assert response.status_code == 422

    def test_per_page_exceeds_max_returns_422(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.get("/api/folders/folder-abc/streams?per_page=101", headers=internal_auth_header)
        assert response.status_code == 422


class TestStatusTransitionEdgeCases:
    """Test all status self-transitions and edge cases."""

    def test_paused_to_paused_fails(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        # Active → Paused
        test_client.patch(f"/api/streams/{stream_id}/status", json={"status": "paused"}, headers=internal_auth_header)

        # Paused → Paused should fail
        response = test_client.patch(
            f"/api/streams/{stream_id}/status", json={"status": "paused"}, headers=internal_auth_header
        )
        assert response.status_code == 400

    def test_update_archived_stream_fails(self, test_client: TestClient, internal_auth_header: dict):
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        # Archive it
        test_client.patch(f"/api/streams/{stream_id}/status", json={"status": "archived"}, headers=internal_auth_header)

        # Try to update — should fail
        response = test_client.put(f"/api/streams/{stream_id}", json={"name": "New Name"}, headers=internal_auth_header)
        assert response.status_code == 400


class TestAuthPermissions:
    def test_no_roles_cannot_access_streams(self, test_client: TestClient, no_roles_auth_header: dict):
        response = test_client.get("/api/folders/folder-abc/streams", headers=no_roles_auth_header)
        assert response.status_code == 403

    def test_read_role_can_list(self, test_client: TestClient, read_only_auth_header: dict):
        response = test_client.get("/api/folders/folder-abc/streams", headers=read_only_auth_header)
        assert response.status_code == 200

    def test_read_role_cannot_create(self, test_client: TestClient, read_only_auth_header: dict):
        response = test_client.post(
            "/api/folders/folder-abc/streams",
            json=_stream_payload(),
            headers=read_only_auth_header,
        )
        assert response.status_code == 403

    def test_read_role_cannot_delete(
        self, test_client: TestClient, read_only_auth_header: dict, internal_auth_header: dict
    ):
        # Create with write perms
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams", json=_stream_payload(), headers=internal_auth_header
        )
        stream_id = create_resp.json()["id"]

        # Try delete with read-only
        response = test_client.delete(f"/api/streams/{stream_id}", headers=read_only_auth_header)
        assert response.status_code == 403


class TestTestConnectionEndpoint:
    @patch("app.services.dispatch_service.get_adapter")
    def test_test_connection_success(self, mock_get_adapter, test_client: TestClient, internal_auth_header: dict):
        mock_adapter = AsyncMock()
        mock_adapter.send = AsyncMock(return_value=DispatchResult(success=True, status_code=200))
        mock_get_adapter.return_value = mock_adapter

        response = test_client.post(
            "/api/test-connection",
            json={
                "channel_type": "webhook",
                "channel_config": {"url": "https://example.com/hook", "headers": {}, "secret": None},
            },
            headers=internal_auth_header,
        )
        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["error"] is None

    @patch("app.services.dispatch_service.get_adapter")
    def test_test_connection_failure(self, mock_get_adapter, test_client: TestClient, internal_auth_header: dict):
        mock_adapter = AsyncMock()
        mock_adapter.send = AsyncMock(return_value=DispatchResult(success=False, error="Connection refused"))
        mock_get_adapter.return_value = mock_adapter

        response = test_client.post(
            "/api/test-connection",
            json={
                "channel_type": "teams",
                "channel_config": {"workflow_url": "https://teams.example.com/hook"},
            },
            headers=internal_auth_header,
        )
        assert response.status_code == 200
        data = response.json()
        assert data["success"] is False
        assert data["error"] == "Connection refused"

    def test_test_connection_invalid_channel_type(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.post(
            "/api/test-connection",
            json={
                "channel_type": "invalid",
                "channel_config": {},
            },
            headers=internal_auth_header,
        )
        assert response.status_code == 422

    def test_test_connection_no_auth(self, test_client: TestClient):
        response = test_client.post(
            "/api/test-connection",
            json={"channel_type": "webhook", "channel_config": {"url": "https://example.com"}},
        )
        assert response.status_code == 401

    def test_test_connection_read_only_role(self, test_client: TestClient, read_only_auth_header: dict):
        response = test_client.post(
            "/api/test-connection",
            json={"channel_type": "webhook", "channel_config": {"url": "https://example.com"}},
            headers=read_only_auth_header,
        )
        assert response.status_code == 403


class TestDispatchEndpoint:
    @patch("app.services.dispatch_service.get_adapter")
    def test_dispatch_stream_success(self, mock_get_adapter, test_client: TestClient, internal_auth_header: dict):
        # Create a stream first
        create_resp = test_client.post(
            "/api/folders/folder-abc/streams",
            json=_stream_payload(),
            headers=internal_auth_header,
        )
        stream_id = create_resp.json()["id"]

        # Mock the adapter
        mock_adapter = AsyncMock()
        mock_adapter.send = AsyncMock(return_value=DispatchResult(success=True, status_code=200))
        mock_get_adapter.return_value = mock_adapter

        response = test_client.post(
            f"/api/streams/{stream_id}/dispatch",
            headers=internal_auth_header,
        )
        assert response.status_code == 200
        data = response.json()
        assert "dispatched_count" in data
        assert "failed_count" in data

    def test_dispatch_stream_not_found(self, test_client: TestClient, internal_auth_header: dict):
        response = test_client.post(
            "/api/streams/999/dispatch",
            headers=internal_auth_header,
        )
        assert response.status_code == 404

    def test_dispatch_stream_no_auth(self, test_client: TestClient):
        response = test_client.post("/api/streams/1/dispatch")
        assert response.status_code == 401

    def test_dispatch_stream_read_only_role(self, test_client: TestClient, read_only_auth_header: dict):
        response = test_client.post(
            "/api/streams/1/dispatch",
            headers=read_only_auth_header,
        )
        assert response.status_code == 403
