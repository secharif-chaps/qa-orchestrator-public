"""Unit tests for per-user rate limiting on chapse chat endpoint."""

import time
from unittest.mock import patch

import pytest
from fastapi import HTTPException

from app.core.rate_limit import UserRateLimiter


class TestUserRateLimiter:
    """Tests for the UserRateLimiter class."""

    def test_allows_requests_under_limit(self):
        """Requests under the limit should pass without error."""
        limiter = UserRateLimiter(max_requests=5, window_seconds=60)
        for _ in range(5):
            limiter.check("user-1")
        # 5 requests allowed — no exception

    def test_blocks_requests_over_limit(self):
        """Requests exceeding the limit should raise 429."""
        limiter = UserRateLimiter(max_requests=3, window_seconds=60)
        for _ in range(3):
            limiter.check("user-1")

        with pytest.raises(HTTPException) as exc_info:
            limiter.check("user-1")

        assert exc_info.value.status_code == 429
        assert "maximum 3 requests" in exc_info.value.detail
        assert "Retry-After" in exc_info.value.headers

    def test_different_users_are_independent(self):
        """One user hitting their limit should not affect another user."""
        limiter = UserRateLimiter(max_requests=2, window_seconds=60)

        # User A exhausts their limit
        limiter.check("user-a")
        limiter.check("user-a")
        with pytest.raises(HTTPException) as exc_info:
            limiter.check("user-a")
        assert exc_info.value.status_code == 429

        # User B should still be able to make requests
        limiter.check("user-b")
        limiter.check("user-b")
        # User B also hits limit
        with pytest.raises(HTTPException):
            limiter.check("user-b")

    def test_window_expires_and_allows_new_requests(self):
        """After the window elapses, user should be able to make requests again."""
        limiter = UserRateLimiter(max_requests=2, window_seconds=1)

        limiter.check("user-1")
        limiter.check("user-1")

        # Should be blocked now
        with pytest.raises(HTTPException):
            limiter.check("user-1")

        # Wait for the window to expire
        time.sleep(1.1)

        # Should be allowed again
        limiter.check("user-1")

    @patch("app.core.rate_limit.time.time")
    def test_sliding_window_behavior(self, mock_time):
        """The window should slide — old requests expire individually."""
        limiter = UserRateLimiter(max_requests=2, window_seconds=60)

        # Request 1 at t=0
        mock_time.return_value = 1000.0
        limiter.check("user-1")

        # Request 2 at t=30
        mock_time.return_value = 1030.0
        limiter.check("user-1")

        # Blocked at t=40 (both requests still in window)
        mock_time.return_value = 1040.0
        with pytest.raises(HTTPException):
            limiter.check("user-1")

        # At t=61 first request expired, second still in window — allows one more
        mock_time.return_value = 1061.0
        limiter.check("user-1")

    def test_retry_after_header_value(self):
        """Retry-After header should indicate reasonable wait time."""
        limiter = UserRateLimiter(max_requests=1, window_seconds=60)
        limiter.check("user-1")

        with pytest.raises(HTTPException) as exc_info:
            limiter.check("user-1")

        retry_after = int(exc_info.value.headers["Retry-After"])
        # Should be between 1 and 60 seconds
        assert 1 <= retry_after <= 60

    def test_detail_message_includes_wait_time(self):
        """The error detail should include the retry wait time."""
        limiter = UserRateLimiter(max_requests=1, window_seconds=30)
        limiter.check("user-1")

        with pytest.raises(HTTPException) as exc_info:
            limiter.check("user-1")

        assert "per 30 seconds" in exc_info.value.detail
        assert "Please retry in" in exc_info.value.detail

    def test_cleanup_triggers_on_large_store(self):
        """Store cleanup should remove expired entries when store grows large."""
        limiter = UserRateLimiter(max_requests=1, window_seconds=1)

        # Fill with many users
        for idx in range(1002):
            limiter.check(f"user-{idx}")

        # Wait for window to expire
        time.sleep(1.1)

        # Trigger cleanup by making a new request (store > 1000)
        limiter.check("new-user")

        # Old entries should be cleaned up
        assert len(limiter._store) < 1002

    def test_exact_limit_boundary(self):
        """Exactly max_requests should be allowed, max_requests+1 should be blocked."""
        limiter = UserRateLimiter(max_requests=10, window_seconds=60)

        for _i in range(10):
            limiter.check("user-1")

        with pytest.raises(HTTPException) as exc_info:
            limiter.check("user-1")
        assert exc_info.value.status_code == 429

    def test_single_request_allowed(self):
        """A limiter with max_requests=1 should allow exactly one request."""
        limiter = UserRateLimiter(max_requests=1, window_seconds=60)
        limiter.check("user-1")

        with pytest.raises(HTTPException):
            limiter.check("user-1")

    @patch("app.core.rate_limit.time.time")
    def test_no_real_time_dependency(self, mock_time):
        """Test rate limiter behavior using mocked time for deterministic results."""
        mock_time.return_value = 1000.0
        limiter = UserRateLimiter(max_requests=2, window_seconds=60)

        limiter.check("user-1")
        mock_time.return_value = 1010.0
        limiter.check("user-1")

        # Should be blocked at t=1020
        mock_time.return_value = 1020.0
        with pytest.raises(HTTPException):
            limiter.check("user-1")

        # First request expires at t=1060, so at t=1060.1 we have 1 req in window
        mock_time.return_value = 1060.1
        limiter.check("user-1")

        # Second original request expires at t=1070
        # Now we have req at 1060.1 in window — should allow one more
        mock_time.return_value = 1070.1
        limiter.check("user-1")
